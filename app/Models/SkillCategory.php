<?php

namespace App\Models;

use Database\Factories\SkillCategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An org-owned grouping heading for the Skills catalog (PRD #243) — Communications,
 * Business Skills, Digital Knowledge, Leadership Skills, and the like. Seed-only this
 * slice: the vocabulary ships as an idempotent seeder keyed on `code`, and `active`
 * retires a whole category without deleting rows so historical `member_skill`
 * selections survive.
 */
class SkillCategory extends Model
{
    /** @use HasFactory<SkillCategoryFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'display_order',
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
     * The skills grouped under this category.
     *
     * @return HasMany<Skill, $this>
     */
    public function skills(): HasMany
    {
        return $this->hasMany(Skill::class, 'category_id');
    }

    /**
     * Limit to categories currently offered, in the deliberate `display_order` the
     * settings page renders headings in — the single source of the catalog's active,
     * ordered spine (retired categories drop out).
     *
     * @param  Builder<SkillCategory>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('active', true)->orderBy('display_order');
    }
}
