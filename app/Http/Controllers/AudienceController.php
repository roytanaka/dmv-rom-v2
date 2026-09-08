<?php

namespace App\Http\Controllers;

use App\Enums\AudienceKey;
use App\Models\Member;
use App\Support\Audiences\Audience;
use App\Support\Audiences\AudienceContext;
use App\Support\Audiences\AudienceContextFactory;
use App\Support\Audiences\AudienceResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The two read endpoints the composer reads (ADR-0024 §5): an Audience index — the
 * Audiences the actor may pick in a context, each with its label and count — and an
 * Audience show — the resolved rows for one Audience. Both delegate to the
 * {@see AudienceResolver}, so the picker rule, the no-email skip, and the
 * de-duplication live in one place and never in the browser. Data endpoints (JSON,
 * non-localized): the composer sheet fetches them over an already-rendered page.
 *
 * Neither endpoint needs its own page-visibility gate: the picker rule already
 * denies every Audience an actor cannot pick, so an actor who cannot see a context
 * gets an empty index and a 403 on show — no roster leaks. A row never carries an
 * address (§5); the resolver never puts one on a Member.
 */
class AudienceController extends Controller
{
    public function __construct(
        private readonly AudienceResolver $resolver,
        private readonly AudienceContextFactory $contexts,
    ) {}

    /**
     * List the Audiences the actor may pick in the context, each with its resolved
     * count — the count with no edits, so it matches the show endpoint's rows.
     */
    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();
        $context = $this->context($request);

        $audiences = $this->resolver->available($actor, $context)
            ->map(fn (Audience $audience): array => [
                'key' => $audience->key->value,
                'parameter' => $audience->parameter,
                'label' => $audience->label,
                'count' => $this->resolver
                    ->resolve($actor, $context, $audience->key, $audience->parameter)
                    ->recipients->count(),
            ]);

        return response()->json(['audiences' => $audiences->values()]);
    }

    /**
     * Resolve one Audience to its rows — the recipients and the Members skipped for
     * the no-email flag — after the picker rule and the per-Member edits.
     */
    public function show(Request $request, string $audience): JsonResponse
    {
        $key = AudienceKey::tryFrom($audience);

        if ($key === null) {
            abort(404);
        }

        $parameter = $request->query('parameter');

        $resolved = $this->resolver->resolve(
            $request->user(),
            $this->context($request),
            $key,
            is_string($parameter) ? $parameter : null,
            $this->ids($request, 'removed'),
            $this->ids($request, 'added'),
        );

        return response()->json([
            'label' => $resolved->label,
            'edited' => $resolved->edited,
            'count' => $resolved->recipients->count(),
            'recipients' => $resolved->recipients->map(fn (Member $member): array => $this->row($member))->values(),
            'skipped' => $resolved->skipped->map(fn (Member $member): array => $this->row($member))->values(),
        ]);
    }

    /**
     * Build the {@see AudienceContext} from the request's `context` and `subject`,
     * through the shared factory the send path uses too.
     */
    private function context(Request $request): AudienceContext
    {
        return $this->contexts->fromRequest($request);
    }

    /**
     * A recipient row: identity, avatar, and a standing hint — never an address (§5).
     *
     * @return array<string, mixed>
     */
    private function row(Member $member): array
    {
        return [
            'id' => $member->id,
            'first_name' => $member->first_name,
            'last_name' => $member->last_name,
            'photo' => $member->photo_url,
            'standing' => $member->category->value,
        ];
    }

    /**
     * The integer ids posted under a `removed[]` / `added[]` query key.
     *
     * @return list<int>
     */
    private function ids(Request $request, string $key): array
    {
        return collect((array) $request->query($key, []))
            ->map(fn ($id): int => (int) $id)
            ->all();
    }
}
