<?php

namespace App\Http\Requests\Concerns;

/**
 * A validated money field (ADR-0032 §7, §12) as the decimal string the `decimal:2` columns take,
 * or null when the field was cleared. The validator has already checked it is numeric.
 */
trait ReadsMoney
{
    public function validatedMoney(string $key): ?string
    {
        $amount = $this->validated($key);

        return $amount === null ? null : (string) $amount;
    }
}
