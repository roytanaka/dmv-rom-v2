<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One Service award, 5 to 60 years (#917, ADR-0038 §3). Seeded by its migration; no editor
 * exists. A Member's list holds these codes.
 */
class ServiceAward extends Model
{
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }
}
