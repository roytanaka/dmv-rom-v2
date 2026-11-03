<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesReply;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A Guest setting their own Reply (ADR-0034 §10): only the signed-in Member's own row, and
 * only until Closed. The super-tier gets no exception here.
 */
class UpdateReplyRequest extends FormRequest
{
    use ValidatesReply;

    public function authorize(): bool
    {
        return $this->route('invitation')->takesReplyFrom($this->user());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->replyRules($this->route('invitation'));
    }
}
