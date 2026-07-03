<?php

namespace App\Models;

use Database\Factories\SkillFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single skill a Member can declare they are willing to use for DMV/ROM (PRD
 * #243) — copywriting, web programming, translation, and so on — grouped under a
 * {@see SkillCategory}. Flat: no description, no proficiency/level. Seed-only this
 * slice, keyed on `code`; `active` retires a skill without deleting rows so
 * historical `member_skill` selections survive.
 */
class Skill extends Model
{
    /** @use HasFactory<SkillFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'category_id',
        'name',
        'active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    /**
     * The category this skill is grouped under.
     *
     * @return BelongsTo<SkillCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(SkillCategory::class, 'category_id');
    }

    /**
     * Limit to skills currently offered — the ones a Member may select. A retired
     * skill drops out of the catalog without losing its `member_skill` history.
     *
     * @param  Builder<Skill>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }
}
