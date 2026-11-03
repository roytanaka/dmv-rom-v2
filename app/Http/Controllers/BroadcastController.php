<?php

namespace App\Http\Controllers;

use App\Enums\AudienceKey;
use App\Enums\BroadcastKind;
use App\Enums\ContextType;
use App\Enums\DeliveryKind;
use App\Enums\DeliveryState;
use App\Enums\InvitationState;
use App\Http\Requests\SendBroadcastRequest;
use App\Models\Applicant;
use App\Models\Broadcast;
use App\Models\Delivery;
use App\Models\Member;
use App\Support\Audiences\AudienceContextFactory;
use App\Support\Audiences\AudienceResolver;
use App\Support\Audiences\ResolvedAudience;
use App\Support\Mail\BroadcastAttachmentStorage;
use App\Support\Mail\HtmlSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The Broadcast send path (spec #479, ADR-0024 §4, §6) — the server side of the composer.
 * Nothing sends in the request: a send re-resolves its Audience from the Group model, applies
 * the picker's per-Member edits, drops the no-email flagged, sanitizes the body, holds the
 * attachments on the private disk, and writes the sent record plus one Broadcast
 * {@see Delivery} per recipient and one sender-copy Delivery. The every-minute
 * Drain empties the queue.
 *
 * It returns the queued count and the names skipped for the no-email flag — the sender learns a
 * Member is unreachable only by trying to reach them (§9). A forbidden Audience is a 403 and an
 * out-of-bounds edit a 422, both raised inside {@see AudienceResolver::resolve()}.
 */
class BroadcastController extends Controller
{
    public function __construct(
        private readonly AudienceResolver $resolver,
        private readonly AudienceContextFactory $contexts,
        private readonly HtmlSanitizer $sanitizer,
        private readonly BroadcastAttachmentStorage $attachments,
    ) {}

    public function store(SendBroadcastRequest $request): JsonResponse
    {
        $actor = $request->user();
        $context = $this->contexts->fromRequest($request, 'context_subject');
        $key = AudienceKey::from($request->string('audience')->value());

        $resolved = $this->resolver->resolve(
            $actor,
            $context,
            $key,
            $request->filled('parameter') ? $request->string('parameter')->value() : null,
            $request->input('removed', []),
            $request->input('added', []),
        );

        $kind = match ($context->type) {
            ContextType::Member => BroadcastKind::DirectMessage,
            ContextType::Invitation => BroadcastKind::Invitation,
            default => BroadcastKind::Broadcast,
        };

        // A Direct message has one recipient and no silent-skip: where a Broadcast quietly drops a
        // flagged Member and reports them after the fact, a Direct message to one is refused outright
        // so nothing is written and the sender is told they cannot be reached (ADR-0024 §6, §9).
        if ($kind === BroadcastKind::DirectMessage && $resolved->recipients->isEmpty()) {
            throw ValidationException::withMessages([
                'audience' => __('broadcasts.direct.unreachable', ['name' => $context->subject->fullName()]),
            ]);
        }

        // A Draft's Send is the mail that opens it, and it goes once to every Guest (ADR-0034 §7).
        // Mail to Accepted Guests opens nothing, so it stays free.
        if ($kind === BroadcastKind::Invitation
            && $context->subject->state === InvitationState::Draft
            && ($key === AudienceKey::InvitationNoReply
                || ($key === AudienceKey::InvitationGuests && $request->input('removed', []) !== []))) {
            throw ValidationException::withMessages(['audience' => __('invitations.send.every_guest')]);
        }

        // A Draft opens only when a mail is written (ADR-0034 §7), so a send that reaches no
        // Guest changes nothing.
        if ($kind === BroadcastKind::Invitation && $resolved->recipients->isEmpty()) {
            throw ValidationException::withMessages(['audience' => __('invitations.send.unreachable')]);
        }

        $manifest = $this->attachments->store($this->uploadedFiles($request));

        $broadcast = DB::transaction(function () use ($actor, $context, $key, $kind, $resolved, $request, $manifest): Broadcast {
            $broadcast = Broadcast::create([
                'kind' => $kind,
                'sender_id' => $actor->getKey(),
                'group_id' => $context->owningGroup()?->getKey(),
                'invitation_id' => $kind === BroadcastKind::Invitation ? $context->subject->getKey() : null,
                'audience_key' => $key->value,
                'audience_label' => $resolved->label,
                'edited' => $resolved->edited,
                'recipient_count' => $resolved->recipients->count(),
                'subject' => $request->string('subject')->value(),
                'body' => $this->sanitizer->sanitize($request->string('body')->value()),
                'attachments' => $manifest,
                'queued_at' => now(),
            ]);

            $this->writeRecipientDeliveries($broadcast, $resolved);
            $this->writeSenderCopy($broadcast, $actor);

            // Mailing Accepted Guests is the Host's door tool, and a Draft can hold Accepted
            // Replies, so that send opens nothing (#908).
            if ($kind === BroadcastKind::Invitation && $key !== AudienceKey::InvitationAccepted) {
                $context->subject->open();
            }

            return $broadcast;
        });

        return response()->json([
            'queued' => $broadcast->recipient_count,
            'skipped' => $resolved->skipped->map(fn (Member $member): string => $member->fullName())->values(),
        ]);
    }

    /**
     * One Broadcast Delivery per recipient, snapshotting the address at write time. An address
     * that fails validation here is a permanent failure written failed at once (ADR-0024 §4), so
     * the row still exists to be named in the sender's copy but is never handed to the transport.
     * An Applicant (#929) is no Member: its row carries no Member, and the payload names it for
     * the sender's copy and takes the sender's language for the Drain.
     */
    private function writeRecipientDeliveries(Broadcast $broadcast, ResolvedAudience $resolved): void
    {
        foreach ($resolved->recipients as $recipient) {
            $valid = filter_var($recipient->email, FILTER_VALIDATE_EMAIL) !== false;
            $applicant = $recipient instanceof Applicant;

            $broadcast->deliveries()->create([
                'kind' => $broadcast->kind->deliveryKind(),
                'member_id' => $applicant ? null : $recipient->getKey(),
                'payload' => $applicant ? ['name' => $recipient->fullName(), 'locale' => app()->getLocale()] : null,
                // An imported Applicant or Member may have no address: the row is written failed.
                'email' => $recipient->email ?? '',
                'state' => $valid ? DeliveryState::Pending : DeliveryState::Failed,
                'attempts' => $valid ? 0 : 1,
                'next_attempt_at' => now(),
                'failed_at' => $valid ? null : now(),
                'last_error' => $valid ? null : __('validation.email', ['attribute' => 'email']),
            ]);
        }
    }

    /**
     * The one sender-copy Delivery — the done signal, sent last, only once every sibling is
     * terminal (ADR-0024 §4). It is a peer row on the same Broadcast, its own kind.
     */
    private function writeSenderCopy(Broadcast $broadcast, Member $actor): void
    {
        $broadcast->deliveries()->create([
            'kind' => DeliveryKind::SenderCopy,
            'member_id' => $actor->getKey(),
            'email' => $actor->email,
            'state' => DeliveryState::Pending,
            'next_attempt_at' => now(),
        ]);
    }

    /**
     * The uploaded attachment files, as a plain list.
     *
     * @return list<UploadedFile>
     */
    private function uploadedFiles(SendBroadcastRequest $request): array
    {
        return array_values(array_filter(
            (array) $request->file('attachments', []),
            fn ($file): bool => $file instanceof UploadedFile,
        ));
    }
}
