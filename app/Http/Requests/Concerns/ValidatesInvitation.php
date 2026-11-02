<?php

namespace App\Http\Requests\Concerns;

use App\Rules\OnMinuteGrid;

/**
 * The fields of an Invitation, shared by create and edit (ADR-0034). The Guest limit
 * and the state are not among them: the first is a later ticket, the second is set
 * by its own action.
 */
trait ValidatesInvitation
{
    /**
     * @return array<string, array<mixed>>
     */
    protected function invitationRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i', new OnMinuteGrid],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time', new OnMinuteGrid],
            'description' => ['required', 'string', 'max:10000'],
            'options' => ['present', 'array'],
            'options.*.name' => ['required', 'string', 'max:255'],
        ];
    }
}
