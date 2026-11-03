<?php

namespace App\Support\SavedQueries;

use App\Enums\SavedQueryFieldType as T;

/**
 * The fixed allowlist a Saved query reads (#923, ADR-0038 §7): its sources, their fields and each
 * field's {@see T type}, and the output columns. Nothing outside it is ever a filter, a sort or a
 * column. The membership number, the password and every hash or token are deliberately absent:
 * the membership number's last four characters are a sign-in secret.
 */
final class SavedQueryFields
{
    public const CONJUNCTIONS = ['and', 'or'];

    /** The Member record's fields, column => type. */
    public const MEMBER = [
        'first_name' => T::Text,
        'last_name' => T::Text,
        'email' => T::Text,
        'phone' => T::Text,
        'alternate_phone' => T::Text,
        'business_phone' => T::Text,
        'address_street' => T::Text,
        'address_city' => T::Text,
        'address_province' => T::Text,
        'address_postal_code' => T::Text,
        'address_country' => T::Text,
        'category' => T::Category,
        'category_group' => T::CategoryGroup,
        'departure_reason' => T::DepartureReason,
        'status_date' => T::Date,
        'membership_expiry' => T::Date,
        'membership_lifetime' => T::Boolean,
        'amount_paid' => T::Number,
        'paid_through_year' => T::Number,
        'fee_category' => T::Text,
        'entry_year' => T::Number,
        'service_awards' => T::List,
        'last_award_year' => T::Number,
        'next_award_year' => T::Number,
        'other_awards' => T::Text,
        'emergency_contact' => T::Text,
        'emergency_contact_number' => T::Text,
        'newsletter_opt_in' => T::Boolean,
        'code_of_conduct_year' => T::Number,
        'renewal_year' => T::Number,
        'renewal_status' => T::Text,
        'renewal_placements' => T::Text,
        'renewal_other' => T::Text,
        'record_form_placements' => T::Text,
        'record_form_other' => T::Text,
        'mentor_name' => T::Text,
        'volunteer_experience' => T::Text,
        'past_experience' => T::Text,
        'education' => T::Text,
        'notes' => T::Text,
        'photo_public' => T::Boolean,
        'no_email' => T::Boolean,
        'last_sign_in_at' => T::Datetime,
    ];

    /**
     * The related sources: each a relation on Member, its fields column => type, and the output
     * column that lists its rows.
     */
    public const RELATED = [
        'membership' => [
            'relation' => 'memberships',
            'fields' => ['group' => T::Group, 'status' => T::MembershipStatus],
            'column' => 'memberships',
        ],
        'board_position' => [
            'relation' => 'boardPositions',
            'fields' => ['position' => T::Text, 'years' => T::Text],
            'column' => 'board_positions',
        ],
        'category_history' => [
            'relation' => 'categoryChanges',
            'fields' => ['category' => T::HistoryCategory, 'date' => T::Date],
            'column' => 'category_history',
        ],
    ];

    /** The derived Member field that names a Category group; no column stores it. */
    public const CATEGORY_GROUP = 'category_group';

    /** @return list<string> */
    public static function sources(): array
    {
        return ['member', ...array_keys(self::RELATED)];
    }

    /** The type of a source's field, or null when the pair is not on the allowlist. */
    public static function type(string $source, string $field): ?T
    {
        if ($source === 'member') {
            return self::MEMBER[$field] ?? null;
        }

        return self::RELATED[$source]['fields'][$field] ?? null;
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

        if (! is_string($operator) || ! in_array($operator, $type->operators(), true)) {
            return 'saved_queries.error.operator';
        }

        if ($value !== null && ! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return 'saved_queries.error.value';
        }

        $value = trim((string) $value);

        if ($value === '') {
            return in_array($operator, ['is', 'is_not'], true) && $type !== T::CategoryGroup ? null : 'saved_queries.error.value';
        }

        $options = $type->options();

        return match (true) {
            $options !== null => array_key_exists($value, $options) ? null : 'saved_queries.error.value',
            $type === T::Number => is_numeric($value) ? null : 'saved_queries.error.number',
            $type->isDate() => self::isDate($value) ? null : 'saved_queries.error.date',
            default => mb_strlen($value) <= 255 ? null : 'saved_queries.error.value',
        };
    }

    private static function isDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
