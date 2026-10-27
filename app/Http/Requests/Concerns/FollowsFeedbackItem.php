<?php

namespace App\Http\Requests\Concerns;

use App\Models\FeedbackFollower;
use App\Support\FeedbackIdentity;
use Illuminate\Validation\Rule;

/**
 * The "Email me updates" fields on a Feedback form (#857, spec #856, ADR-0029 §15): the follow
 * flag and the Follower's typed email. The email is required, a valid address, and at most 254
 * characters when the flag is on, and dropped from the validated data when it is off. It is
 * trimmed and lowercased before validation.
 *
 * {@see followerEmail()} is the address to follow with, or null when the Tester did not tick
 * the box. The fields are not item or comment columns, so a store excludes {@see FOLLOW_FIELDS}.
 *
 * {@see authorEmail()} is the address of whoever makes this request, so a new comment skips
 * their own Follower row (#860). It is the one place that says who the author is.
 *
 * On Beta ({@see FeedbackIdentity::usesAccountIdentity()}, #861) the app knows the Tester by
 * their account: any typed email is dropped unvalidated, and both addresses are the signed-in
 * account's email, normalized.
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

    protected function prepareFollowerEmail(): void
    {
        if (is_string($this->input('follower_email'))) {
            $this->merge(['follower_email' => FeedbackFollower::normalize($this->input('follower_email'))]);
        }
    }

    public function followerEmail(): ?string
    {
        if (! $this->boolean('follow')) {
            return null;
        }

        return FeedbackIdentity::usesAccountIdentity() ? $this->accountEmail() : $this->validated('follower_email');
    }

    /**
     * The author's address, for "no email about your own comment" (#860, §15): the Follower
     * email typed on this request, or null when the box is not ticked. On Beta (#861) it is the
     * signed-in account's email, ticked or not.
     */
    public function authorEmail(): ?string
    {
        return FeedbackIdentity::usesAccountIdentity() ? $this->accountEmail() : $this->followerEmail();
    }

    private function accountEmail(): string
    {
        return FeedbackFollower::normalize($this->user()->email);
    }
}
