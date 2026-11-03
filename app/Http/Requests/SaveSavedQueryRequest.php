<?php

namespace App\Http\Requests;

use App\Models\SavedQuery;
use App\Support\SavedQueries\SavedQueryFields;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A member admin creates or edits a Saved query (#923, ADR-0038 §7). Every clause, the sort and
 * each output column must be on the {@see SavedQueryFields} allowlist.
 */
class SaveSavedQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $query = $this->route('savedQuery');

        return $query instanceof SavedQuery
            ? $this->user()->can('update', $query)
            : $this->user()->can('create', SavedQuery::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'conjunction' => ['required', Rule::in(SavedQueryFields::CONJUNCTIONS)],
            'clauses' => ['present', 'array', 'max:'.SavedQuery::MAX_CLAUSES],
            'clauses.*' => ['array:source,field,operator,value'],
            'clauses.*.source' => ['required', 'string'],
            'clauses.*.field' => ['required', 'string'],
            'clauses.*.operator' => ['required', 'string'],
            'clauses.*.value' => ['nullable', 'string', 'max:255'],
            'sort' => ['required', Rule::in(SavedQueryFields::sortFields())],
            'output_columns' => ['required', 'array', 'min:1'],
            'output_columns.*' => ['distinct', Rule::in(SavedQueryFields::outputColumns())],
        ];
    }

    /**
     * Each clause must name an allowlisted field with an operator and value its type takes.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach ((array) $this->input('clauses', []) as $index => $clause) {
                    if (! is_array($clause) || $validator->errors()->has("clauses.{$index}.*")) {
                        continue;
                    }

                    $error = SavedQueryFields::clauseError($clause);

                    if ($error !== null) {
                        $validator->errors()->add("clauses.{$index}", __($error));
                    }
                }
            },
        ];
    }

    /**
     * The clauses as stored: the four keys, the value trimmed.
     *
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): mixed
    {
        $data = parent::validated();
        $data['clauses'] = array_values(array_map(fn (array $clause) => [
            'source' => $clause['source'],
            'field' => $clause['field'],
            'operator' => $clause['operator'],
            'value' => trim((string) ($clause['value'] ?? '')),
        ], $data['clauses']));
        $data['output_columns'] = array_values($data['output_columns']);

        return data_get($data, $key, $default);
    }
}
