<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row of the dues table (#917, ADR-0038 §3). Seeded by its migration; no editor exists.
 */
class FeeCategory extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'active' => 'boolean',
        ];
    }
}
