<?php

namespace App\Support\SavedQueries;

use App\Enums\Category;
use App\Enums\CategoryGroup;
use App\Models\BoardPosition;
use App\Models\CategoryChange;
use App\Models\GroupMember;
use App\Models\Member;
use App\Models\SavedQuery;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Runs a Saved query (#923, ADR-0038 §7): one Eloquent query built from the stored structure, over
 * {@see SavedQueryFields} only. A field, sort or column outside the allowlist is a validation
 * error, never SQL. With no clause on the Category group, the query runs on the Current group, as
 * the Legacy app prepended it, and the clauses join inside that scope.
 *
 * A related source matches when any of the Member's rows does; a negative operator (is not, does
 * not contain) on one matches when none does. A negative operator on a Member field keeps blank
 * values. An empty value reads as a blank field.
 *
 * Rows carry only the chosen columns, formatted as text, never a serialized Member.
 */
final class SavedQueryRunner
{
    /** The positive operator a negative one inverts. */
    private const POSITIVE = ['is_not' => 'is', 'not_contains' => 'contains'];

    private const SYMBOL = ['is' => '=', 'less_than' => '<', 'greater_than' => '>'];

    /**
     * The Members a query selects, in its sort order.
     *
     * @return Builder<Member>
     */
    public function query(SavedQuery $query): Builder
    {
        $this->validate($query);

        $clauses = $query->clauses;
        $method = $query->conjunction === 'or' ? 'orWhere' : 'where';

        return Member::query()
            ->when(! $this->namesCategoryGroup($clauses), fn (Builder $members) => $members
                ->whereIn('category', Category::inGroup(CategoryGroup::Current)))
            ->when($clauses !== [], fn (Builder $members) => $members->where(function (Builder $where) use ($clauses, $method): void {
                foreach ($clauses as $clause) {
                    $where->{$method}(fn (Builder $one) => $this->clause($one, $clause));
                }
            }))
            ->orderBy($query->sort)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id');
    }

    /**
     * The result rows: each Member's id and the chosen columns as text.
     *
     * @return list<array{id: int, cells: array<string, string|null>}>
     */
    public function rows(SavedQuery $query): array
    {
        $columns = $query->output_columns;

        return $this->query($query)
            ->with(array_values(array_filter([
                in_array('memberships', $columns, true) ? 'memberships.group' : null,
                in_array('board_positions', $columns, true) ? 'boardPositions' : null,
                in_array('category_history', $columns, true) ? 'categoryChanges' : null,
            ])))
            ->get()
            ->map(fn (Member $member) => [
                'id' => $member->id,
                'cells' => array_combine($columns, array_map(fn (string $column) => $this->cell($member, $column), $columns)),
            ])
            ->all();
    }

    /** The clause text a run shows, with the default Current scope written out when it applies. */
    public function describe(SavedQuery $query): string
    {
        $clauses = $query->clauses;
        $joiner = ' '.__('saved_queries.conjunction.'.$query->conjunction).' ';
        $text = implode($joiner, array_map(fn (array $clause) => $this->clauseText($clause), $clauses));

        if ($this->namesCategoryGroup($clauses)) {
            return $text;
        }

        $scope = $this->clauseText(['source' => 'member', 'field' => SavedQueryFields::CATEGORY_GROUP, 'operator' => 'is', 'value' => CategoryGroup::Current->value]);

        return match (count($clauses)) {
            0 => $scope,
            1 => $scope.' '.__('saved_queries.conjunction.and').' '.$text,
            default => $scope.' '.__('saved_queries.conjunction.and').' ('.$text.')',
        };
    }

    /**
     * Refuse a stored query that strays off the allowlist.
     *
     * @throws ValidationException
     */
    private function validate(SavedQuery $query): void
    {
        $errors = [];

        foreach ($query->clauses as $index => $clause) {
            if (! is_array($clause) || ($error = SavedQueryFields::clauseError($clause)) !== null) {
                $errors["clauses.{$index}"] = __($error ?? 'saved_queries.error.field');
            }
        }

        if (count($query->clauses) > SavedQuery::MAX_CLAUSES) {
            $errors['clauses'] = __('saved_queries.error.too_many');
        }

        if (! in_array($query->conjunction, SavedQueryFields::CONJUNCTIONS, true)) {
            $errors['conjunction'] = __('saved_queries.error.field');
        }

        if (! in_array($query->sort, SavedQueryFields::sortFields(), true)) {
            $errors['sort'] = __('saved_queries.error.field');
        }

        foreach ($query->output_columns as $column) {
            if (! in_array($column, SavedQueryFields::outputColumns(), true)) {
                $errors['output_columns'] = __('saved_queries.error.field');
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @param  list<array<string, mixed>>  $clauses */
    private function namesCategoryGroup(array $clauses): bool
    {
        foreach ($clauses as $clause) {
            if (($clause['source'] ?? null) === 'member' && ($clause['field'] ?? null) === SavedQueryFields::CATEGORY_GROUP) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Builder<Member>  $members
     * @param  array{source: string, field: string, operator: string, value: string|null}  $clause
     */
    private function clause(Builder $members, array $clause): void
    {
        $source = $clause['source'];
        $field = $clause['field'];
        $type = SavedQueryFields::type($source, $field);
        $operator = $clause['operator'];
        $value = trim((string) $clause['value']);
        $positive = self::POSITIVE[$operator] ?? $operator;
        $negative = $positive !== $operator;

        if ($source !== 'member') {
            $relation = SavedQueryFields::RELATED[$source]['relation'];
            $match = fn (Builder $rows) => $this->condition($rows, $field, $type, $positive, $value);

            $negative ? $members->whereDoesntHave($relation, $match) : $members->whereHas($relation, $match);

            return;
        }

        $column = $field === SavedQueryFields::CATEGORY_GROUP ? 'category' : $field;

        if (! $negative) {
            $this->condition($members, $column, $type, $positive, $value);
        } elseif ($value === '') {
            $members->whereNotNull($column)->when(
                in_array($type, ['text', 'list'], true),
                fn (Builder $filled) => $filled->where($column, '!=', '')->where($column, '!=', '[]'),
            );
        } else {
            $members->where(fn (Builder $not) => $not
                ->whereNot(fn (Builder $match) => $this->condition($match, $column, $type, $positive, $value))
                ->orWhereNull($column));
        }
    }

    /**
     * One positive condition (is, less than, greater than, contains) on a column.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     */
    private function condition(Builder $query, string $column, string $type, string $operator, string $value): void
    {
        if ($value === '') {
            $query->where(fn (Builder $blank) => $blank->whereNull($column)->when(
                in_array($type, ['text', 'list'], true),
                fn (Builder $empty) => $empty->orWhere($column, '')->orWhere($column, '[]'),
            ));

            return;
        }

        if ($operator === 'contains') {
            $query->whereLike($column, '%'.addcslashes($value, '%_\\').'%');

            return;
        }

        $symbol = self::SYMBOL[$operator];

        match ($type) {
            'category_group' => $query->whereIn('category', Category::inGroup(CategoryGroup::from($value))),
            'group' => $query->whereHas('group', fn (Builder $group) => $group->where('slug', $value)),
            'boolean' => $query->where($column, $value === 'yes'),
            'number' => $query->where($column, $symbol, $value + 0),
            'date', 'datetime' => $query->whereDate($column, $symbol, $value),
            default => $query->where($column, $symbol, $value),
        };
    }

    /** @param  array<string, mixed>  $clause */
    private function clauseText(array $clause): string
    {
        $type = SavedQueryFields::type($clause['source'], $clause['field']);
        $value = trim((string) ($clause['value'] ?? ''));
        $options = SavedQueryFields::options($type) ?? [];

        $shown = match (true) {
            $value === '' => __('saved_queries.blank'),
            array_key_exists($value, $options) => $options[$value],
            default => '“'.$value.'”',
        };

        return SavedQueryFields::fieldLabel($clause['source'], $clause['field'])
            .' '.__('saved_queries.operator.'.$clause['operator'])
            .' '.$shown;
    }

    private function cell(Member $member, string $column): ?string
    {
        return match ($column) {
            'memberships' => $this->joinRows($member->memberships
                ->sortBy(fn (GroupMember $membership) => $membership->group->name)
                ->map(fn (GroupMember $membership) => $membership->group->name.' ('.__('group.standing.'.$membership->status->value).')')),
            'board_positions' => $this->joinRows($member->boardPositions
                ->sortBy('id')
                ->map(fn (BoardPosition $position) => filled($position->years) ? "{$position->position} ({$position->years})" : $position->position)),
            'category_history' => $this->joinRows($member->categoryChanges
                ->sortBy(fn (CategoryChange $change) => [$change->date->toDateString(), $change->id])
                ->map(fn (CategoryChange $change) => $this->historyLabel($change).' ('.$change->date->toDateString().')')),
            default => $this->memberCell($member, $column),
        };
    }

    private function memberCell(Member $member, string $column): ?string
    {
        $value = $member->getAttribute($column);
        $type = SavedQueryFields::MEMBER[$column];

        return match (true) {
            $value === null => null,
            $value instanceof BackedEnum => SavedQueryFields::options($type)[$value->value] ?? (string) $value->value,
            $type === 'boolean' => __($value ? 'saved_queries.yes' : 'saved_queries.no'),
            $type === 'datetime' && $value instanceof Carbon => $value->timezone(config('app.org_timezone'))->format('Y-m-d H:i'),
            $value instanceof Carbon => $value->toDateString(),
            is_array($value) => implode(', ', $value),
            default => (string) $value,
        };
    }

    private function historyLabel(CategoryChange $change): string
    {
        return SavedQueryFields::options('history_category')[$change->category] ?? $change->category;
    }

    /** @param  iterable<string>  $parts */
    private function joinRows(iterable $parts): ?string
    {
        $text = implode('; ', is_array($parts) ? $parts : iterator_to_array($parts, false));

        return $text === '' ? null : $text;
    }
}
