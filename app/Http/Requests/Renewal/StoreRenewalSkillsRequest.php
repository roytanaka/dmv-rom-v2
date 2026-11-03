<?php

namespace App\Http\Requests\Renewal;

use App\Http\Requests\Settings\SkillsUpdateRequest;

/**
 * Step 3 of the Renewal (#941, ADR-0038 §9): the Member's skills. The Settings skills request,
 * so the same active-catalog rule applies, and refused to a Member who need not renew, as every
 * {@see RenewalRequest} is.
 */
class StoreRenewalSkillsRequest extends SkillsUpdateRequest
{
    public function authorize(): bool
    {
        return parent::authorize() && $this->user()->renewalEligibility()->eligible();
    }
}
