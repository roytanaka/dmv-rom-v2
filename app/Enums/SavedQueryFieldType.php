<?php

namespace App\Enums;

use App\Models\CategoryChange;
use App\Models\Group;

/**
 * The type of a Saved query field (#923, ADR-0038 §7). It decides the operators a clause may use,
 * the options a choice offers and how a value is read: text, number, date, datetime, boolean
 * (yes/no), list (a JSON list, matched as text), or a choice from a fixed set of options.
 *
 * Backed string enum: the value is the type the query form reads.
 */
enum SavedQueryFieldType: string
{
    case Text = 'text';
    case Number = 'number';
    case Date = 'date';
    case Datetime = 'datetime';
    case Boolean = 'boolean';
    case List = 'list';
    case Category = 'category';
    case CategoryGroup = 'category_group';
    case HistoryCategory = 'history_category';
    case DepartureReason = 'departure_reason';
    case MembershipStatus = 'membership_status';
    case Group = 'group';

    /** Every operator a clause may name. */
    public const OPERATORS = ['is', 'is_not', 'less_than', 'greater_than', 'contains', 'not_contains'];

    /**
     * The operators this type takes.
     *
     * @return list<string>
     */
    public function operators(): array
    {
        return match ($this) {
            self::Text => self::OPERATORS,
            self::Number, self::Date, self::Datetime => ['is', 'is_not', 'less_than', 'greater_than'],
            self::List => ['contains', 'not_contains'],
            default => ['is', 'is_not'],
        };
    }

    /** Whether a blank value also matches an empty string or an empty list. */
    public function isTextual(): bool
    {
        return $this === self::Text || $this === self::List;
    }

    /** Whether the value is a date, compared by day. */
    public function isDate(): bool
    {
        return $this === self::Date || $this === self::Datetime;
    }

    /**
     * The fixed options of a choice type, value => label in the active locale, or null for a
     * free-text type.
     *
     * @return array<string, string>|null
     */
    public function options(): ?array
    {
        return match ($this) {
            self::Boolean => ['yes' => __('saved_queries.yes'), 'no' => __('saved_queries.no')],
            self::Category => self::enumOptions(Category::cases(), 'member.standing'),
            self::HistoryCategory => [
                ...self::enumOptions(Category::cases(), 'member.standing'),
                CategoryChange::REINSTATE => __('records.history.reinstate'),
            ],
            self::CategoryGroup => self::enumOptions(CategoryGroup::cases(), 'member.category_group'),
            self::DepartureReason => self::enumOptions(DepartureReason::cases(), 'member.departure_reason'),
            self::MembershipStatus => self::enumOptions(MembershipStatus::cases(), 'group.standing'),
            self::Group => Group::query()->orderBy('name')->pluck('name', 'slug')->all(),
            default => null,
        };
    }

    /**
     * @param  list<\BackedEnum>  $cases
     * @return array<string, string>
     */
    private static function enumOptions(array $cases, string $prefix): array
    {
        $options = [];

        foreach ($cases as $case) {
            $options[(string) $case->value] = __("{$prefix}.{$case->value}");
        }

        return $options;
    }
}
