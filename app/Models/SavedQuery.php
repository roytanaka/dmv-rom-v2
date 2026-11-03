<?php

namespace App\Models;

use App\Policies\SavedQueryPolicy;
use App\Support\SavedQueries\SavedQueryRunner;
use Illuminate\Database\Eloquent\Model;

/**
 * A Saved query (#923, ADR-0038 §7): a named filter over Member data that member administration
 * keeps. Up to ten clauses of source, field, operator and value, joined by one conjunction, with
 * one sort field and the chosen output columns. Run by {@see SavedQueryRunner}; gated by
 * {@see SavedQueryPolicy}.
 *
 * @property list<array{source: string, field: string, operator: string, value: string|null}> $clauses
 * @property list<string> $output_columns
 */
class SavedQuery extends Model
{
    /** The most clauses a query holds, as the Legacy app's ten rows. */
    public const MAX_CLAUSES = 10;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'clauses',
        'conjunction',
        'sort',
        'output_columns',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'clauses' => 'array',
            'output_columns' => 'array',
        ];
    }
}
