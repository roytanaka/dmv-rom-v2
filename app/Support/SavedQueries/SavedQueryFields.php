<?php

namespace App\Support\SavedQueries;

use App\Enums\Category;
use App\Enums\CategoryGroup;
use App\Enums\DepartureReason;
use App\Enums\MembershipStatus;
use App\Models\CategoryChange;
use App\Models\Group;

/**
 * The fixed allowlist a Saved query reads (#923, ADR-0038 §7): its sources, their fields and each
 * field's type, the operators a type takes, and the output columns. Nothing outside it is ever a
 * filter, a sort or a column. The membership number, the password and every hash or token are
 * deliberately absent: the membership number's last four characters are a sign-in secret.
 *
 * A field's type decides how its value is read: text, number, date, datetime, boolean (yes/no),
 * list (a JSON list, matched as text), or a choice from a fixed set of options.
 */
final class SavedQueryFields
{
    public const OPERATORS = ['is', 'is_not', 'less_than', 'greater_than', 'contains', 'not_contains'];

    public const CONJUNCTIONS = ['and', 'or'];

    /** The Member record's fields, column => type. */
    public const MEMBER = [
        'first_name' => 'text',
        'last_name' => 'text',
        'email' => 'text',
        'phone' => 'text',
        'alternate_phone' => 'text',
        'business_phone' => 'text',
        'address_street' => 'text',
        'address_city' => 'text',
        'address_province' => 'text',
        'address_postal_code' => 'text',
        'address_country' => 'text',
        'category' => 'category',
        'category_group' => 'category_group',
        'departure_reason' => 'departure_reason',
        'status_date' => 'date',
        'membership_expiry' => 'date',
        'membership_lifetime' => 'boolean',
        'amount_paid' => 'number',
        'paid_through_year' => 'number',
        'fee_category' => 'text',
        'entry_year' => 'number',
        'service_awards' => 'list',
        'last_award_year' => 'number',
        'next_award_year' => 'number',
        'other_awards' => 'text',
        'emergency_contact' => 'text',
        'emergency_contact_number' => 'text',
        'newsletter_opt_in' => 'boolean',
        'code_of_conduct_year' => 'number',
        'renewal_year' => 'number',
        'renewal_status' => 'text',
        'renewal_placements' => 'text',
        'renewal_other' => 'text',
        'record_form_placements' => 'text',
        'record_form_other' => 'text',
        'mentor_name' => 'text',
        'volunteer_experience' => 'text',
        'past_experience' => 'text',
        'education' => 'text',
        'notes' => 'text',
        'photo_public' => 'boolean',
        'no_email' => 'boolean',
        'last_sign_in_at' => 'datetime',
    ];

    /**
     * The related sources: each a relation on Member, its fields column => type, and the output
     * column that lists its rows.
     */
    public const RELATED = [
        'membership' => [
            'relation' => 'memberships',
            'fields' => ['group' => 'group', 'status' => 'membership_status'],
            'column' => 'memberships',
        ],
        'board_position' => [
            'relation' => 'boardPositions',
            'fields' => ['position' => 'text', 'years' => 'text'],
            'column' => 'board_positions',
        ],
        'category_history' => [
            'relation' => 'categoryChanges',
            'fields' => ['category' => 'history_category', 'date' => 'date'],
            'column' => 'category_history',
        ],
    ];

    /** The derived Member field that names a Category group; no column stores it. */
    public const CATEGORY_GROUP = 'category_group';

    /** The operators each type takes. */
    private const TYPE_OPERATORS = [
        'text' => self::OPERATORS,
        'number' => ['is', 'is_not', 'less_than', 'greater_than'],
        'date' => ['is', 'is_not', 'less_than', 'greater_than'],
        'datetime' => ['is', 'is_not', 'less_than', 'greater_than'],
        'list' => ['contains', 'not_contains'],
    ];

    /** @return list<string> */
    public static function sources(): array
    {
        return ['member', ...array_keys(self::RELATED)];
    }

    /** The type of a source's field, or null when the pair is not on the allowlist. */
    public static function type(string $source, string $field): ?string
    {
        if ($source === 'member') {
            return self::MEMBER[$field] ?? null;
        }

        return self::RELATED[$source]['fields'][$field] ?? null;
    }

    /** @return list<string> */
    public static function operatorsFor(string $type): array
    {
        return self::TYPE_OPERATORS[$type] ?? ['is', 'is_not'];
    }

    /**
     * The output columns: every Member field but the derived Category group, then one column per
     * related source.
     *
     * @return list<string>
     */
    public static function outputColumns(): array
    {
        return [
            ...array_keys(array_diff_key(self::MEMBER, [self::CATEGORY_GROUP => true])),
            ...array_column(self::RELATED, 'column'),
        ];
    }

    /**
     * The sort fields: the Member's own columns.
     *
     * @return list<string>
     */
    public static function sortFields(): array
    {
        return array_keys(array_diff_key(self::MEMBER, [self::CATEGORY_GROUP => true, 'service_awards' => true]));
    }

    /**
     * The fixed options of a choice type, value => label, or null for a free-text type.
     *
     * @return array<string, string>|null
     */
    public static function options(string $type): ?array
    {
        return match ($type) {
            'boolean' => ['yes' => __('saved_queries.yes'), 'no' => __('saved_queries.no')],
            'category' => self::enumOptions(Category::cases(), 'member.standing'),
            'history_category' => [
                ...self::enumOptions(Category::cases(), 'member.standing'),
                CategoryChange::REINSTATE => __('records.history.reinstate'),
            ],
            'category_group' => self::enumOptions(CategoryGroup::cases(), 'member.category_group'),
            'departure_reason' => self::enumOptions(DepartureReason::cases(), 'member.departure_reason'),
            'membership_status' => self::enumOptions(MembershipStatus::cases(), 'group.standing'),
            'group' => Group::query()->orderBy('name')->pluck('name', 'slug')->all(),
            default => null,
        };
    }

    /** The label of a source's field, in the active locale. */
    public static function fieldLabel(string $source, string $field): string
    {
        $key = $source === 'member' ? $field : "{$source}.{$field}";

        return __("saved_queries.field.{$key}");
    }

    /** The label of an output column, in the active locale. */
    public static function columnLabel(string $column): string
    {
        return array_key_exists($column, self::MEMBER)
            ? __("saved_queries.field.{$column}")
            : __("saved_queries.column.{$column}");
    }

    /**
     * Why a stored or posted clause is not on the allowlist, as a translation key, or null when
     * it is valid.
     *
     * @param  array<string, mixed>  $clause
     */
    public static function clauseError(array $clause): ?string
    {
        $source = $clause['source'] ?? null;
        $field = $clause['field'] ?? null;
        $operator = $clause['operator'] ?? null;
        $value = $clause['value'] ?? null;

        if (! is_string($source) || ! is_string($field) || ($type = self::type($source, $field)) === null) {
            return 'saved_queries.error.field';
        }

        if (! is_string($operator) || ! in_array($operator, self::operatorsFor($type), true)) {
            return 'saved_queries.error.operator';
        }

        if ($value !== null && ! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return 'saved_queries.error.value';
        }

        $value = trim((string) $value);

        if ($value === '') {
            return in_array($operator, ['is', 'is_not'], true) && $type !== 'category_group' ? null : 'saved_queries.error.value';
        }

        $options = self::options($type);

        return match (true) {
            $options !== null => array_key_exists($value, $options) ? null : 'saved_queries.error.value',
            $type === 'number' => is_numeric($value) ? null : 'saved_queries.error.number',
            $type === 'date', $type === 'datetime' => self::isDate($value) ? null : 'saved_queries.error.date',
            default => mb_strlen($value) <= 255 ? null : 'saved_queries.error.value',
        };
    }

    private static function isDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
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
