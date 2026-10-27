<?php

namespace App\Http\Requests\Concerns;

use App\Models\FeedbackFollower;
use App\Models\FeedbackItem;
use App\Support\FeedbackIdentity;
use Illuminate\Validation\Rule;

/**
 * The "Email me updates" fields on a Feedback form (#857, spec #856, ADR-0029 §15): the follow
 * flag and the Follower's typed email. The email is required, a valid address, and at most 254
 * characters when the flag is on, and dropped from the validated data when it is off.
 *
 * {@see followItem()} makes the Tester a Follower when they ticked the box. The fields are not
 * item or comment columns, so a store excludes {@see FOLLOW_FIELDS}.
 *
 * {@see authorEmail()} is the address of whoever makes this request, so a new comment skips
 * their own Follower row (#860). It is the one place that says who the author is, and the one
 * place that normalizes an address: trimmed and lowercased.
 *
 * On Beta ({@see FeedbackIdentity::usesAccountIdentity()}, #861) the app knows the Tester by
 * their account: any typed email is dropped unvalidated, and the address is the signed-in
 * account's email.
 */
trait FollowsFeedbackItem
{
    public const FOLLOW_FIELDS = ['follow', 'follower_email'];

    /**
     * @return array<string, array<mixed>>
     */
    protected function followRules(): array
    {
        return [
            'follow' => ['boolean'],
            'follower_email' => [Rule::excludeIf(FeedbackIdentity::usesAccountIdentity() || ! $this->boolean('follow')), 'required', 'string', 'email', 'max:254'],
        ];
    }

    /**
     * Follow the item with {@see followerEmail()} in the request's locale, or do nothing when
     * the box is not ticked. Call it inside the store's transaction.
     */
    public function followItem(FeedbackItem $item): ?FeedbackFollower
    {
        $email = $this->followerEmail();

        return $email === null ? null : $item->follow($email, app()->getLocale());
    }

    /**
     * The address to follow with, or null when the Tester did not tick the box.
     */
    public function followerEmail(): ?string
    {
        return $this->boolean('follow') ? $this->authorEmail() : null;
    }

    /**
     * The author's address, for "no email about your own comment" (#860, §15): the Follower
     * email typed on this request, or null when the box is not ticked (the typed email is then
     * excluded from the validated data). On Beta (#861) it is the signed-in account's email,
     * ticked or not.
     */
    public function authorEmail(): ?string
    {
        $email = FeedbackIdentity::usesAccountIdentity() ? $this->user()->email : $this->validated('follower_email');

        return $email === null ? null : mb_strtolower(trim($email));
    }
}
