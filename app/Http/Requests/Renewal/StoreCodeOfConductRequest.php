<?php

namespace App\Http\Requests\Renewal;

/**
 * Step 1 of the Renewal (#939, ADR-0038 §9): the Member ticks that they accept the code of conduct.
 */
class StoreCodeOfConductRequest extends RenewalRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'accept' => ['accepted'],
        ];
    }
}
