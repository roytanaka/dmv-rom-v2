<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesGuestPick;
use App\Http\Requests\Concerns\ValidatesReply;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A Host setting a Reply for someone (ADR-0034 §10): the Invitation's Host or the
 * super-tier, whether or not it is Closed. The Member is a Guest already, who stays
 * answerable even if no longer current, or a current Member who becomes a Guest.
 */
class SetGuestReplyRequest extends FormRequest
{
    use ValidatesGuestPick, ValidatesReply;

    public function authorize(): bool
    {
        return $this->user()->can('manageGuests', $this->route('invitation'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $invitation = $this->route('invitation');

        return [
            'member' => $this->guestPickRules($invitation),
            ...$this->replyRules($invitation),
        ];
    }
}
