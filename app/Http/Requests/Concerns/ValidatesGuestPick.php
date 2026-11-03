<?php

namespace App\Http\Requests\Concerns;

use App\Models\Invitation;
use App\Models\Member;
use Closure;

/**
 * The Member a Host picks for an Invitation (ADR-0034 §3): a current Member, which means the
 * standing that gives a Directory listing. Pass the Invitation to also take someone already on
 * its list, who stays answerable even if no longer current.
 */
trait ValidatesGuestPick
{
    /**
     * @return list<mixed>
     */
    protected function guestPickRules(?Invitation $listedOn = null): array
    {
        return ['required', 'integer', function (string $attribute, mixed $value, Closure $fail) use ($listedOn): void {
            $eligible = $listedOn?->guests()->where('member_id', $value)->exists()
                || Member::query()->inDirectory()->whereKey($value)->exists();

            if (! $eligible) {
                $fail(__('validation.exists', ['attribute' => $attribute]));
            }
        }];
    }
}
