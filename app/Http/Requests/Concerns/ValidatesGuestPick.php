<?php

namespace App\Http\Requests\Concerns;

use App\Enums\Category;
use App\Models\Invitation;
use App\Models\Member;
use Closure;

/**
 * The Member a Host picks for an Invitation (ADR-0034 §3): a Member whose standing lets them be
 * a Guest ({@see Category::canBeGuest()}). Pass the Invitation to also take someone already on
 * its list, who stays answerable even if their standing has changed.
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
                || Member::query()->canBeGuest()->whereKey($value)->exists();

            if (! $eligible) {
                $fail(__('validation.exists', ['attribute' => $attribute]));
            }
        }];
    }
}
