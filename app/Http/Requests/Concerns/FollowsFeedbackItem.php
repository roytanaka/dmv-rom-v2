<?php

namespace App\Http\Requests\Concerns;

use App\Models\FeedbackFollower;
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
            'follower_email' => [Rule::excludeIf(! $this->boolean('follow')), 'required', 'string', 'email', 'max:254'],
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
        return $this->boolean('follow') ? $this->validated('follower_email') : null;
    }

    /**
     * The author's address, for "no email about your own comment" (#860, §15): the Follower
     * email typed on this request, or null when the box is not ticked. On Beta (#861) this is
     * the signed-in account's email.
     */
    public function authorEmail(): ?string
    {
        return $this->followerEmail();
    }
}
