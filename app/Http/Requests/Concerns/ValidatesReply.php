<?php

namespace App\Http\Requests\Concerns;

use App\Enums\Reply;
use App\Models\Invitation;
use Illuminate\Validation\Rule;

/**
 * The fields of a Reply: an Accepted or Declined answer, and chosen Options that must
 * belong to the Invitation on the route.
 */
trait ValidatesReply
{
    /**
     * @return array<string, mixed>
     */
    protected function replyRules(Invitation $invitation): array
    {
        return [
            'reply' => ['required', Rule::in([Reply::Accepted->value, Reply::Declined->value])],
            'options' => ['array'],
            'options.*' => ['integer', 'distinct', Rule::exists('options', 'id')->where('invitation_id', $invitation->id)],
        ];
    }
}
