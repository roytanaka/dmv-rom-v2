<?php

namespace App\Http\Controllers;

use App\Enums\Category;
use App\Enums\CategoryGroup;
use App\Enums\SavedQueryFieldType;
use App\Http\Requests\DeleteSavedQueryRequest;
use App\Http\Requests\RenameSavedQueryRequest;
use App\Http\Requests\SaveSavedQueryRequest;
use App\Models\Member;
use App\Models\SavedQuery;
use App\Policies\SavedQueryPolicy;
use App\Support\SavedQueries\SavedQueryFields;
use App\Support\SavedQueries\SavedQueryRunner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Saved queries (#923, ADR-0038 §7): Officer Tools → Members → Queries. The index by name, the
 * create and edit form (clauses, output columns, sort), and the run (count, clause text, the
 * table). Member administration only, through {@see SavedQueryPolicy}.
 */
class SavedQueryController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', SavedQuery::class);

        return Inertia::render('officer/members/queries/Index', [
            'queries' => SavedQuery::query()->orderBy('name')->orderBy('id')->get(['id', 'name']),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', SavedQuery::class);

        return Inertia::render('officer/members/queries/Form', [
            ...$this->formOptions(),
            'query' => null,
        ]);
    }

    public function store(SaveSavedQueryRequest $request): RedirectResponse
    {
        $query = (new SavedQuery($request->validated()))->forceFill([
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);
        $query->save();

        return redirect()->route('officer.members.queries.run', $query);
    }

    public function edit(SavedQuery $savedQuery): Response
    {
        Gate::authorize('update', $savedQuery);

        return Inertia::render('officer/members/queries/Form', [
            ...$this->formOptions(),
            'query' => $savedQuery->only('id', 'name', 'clauses', 'conjunction', 'sort', 'output_columns'),
        ]);
    }

    public function update(SaveSavedQueryRequest $request, SavedQuery $savedQuery): RedirectResponse
    {
        $savedQuery->fill($request->validated())->forceFill(['updated_by' => $request->user()->id])->save();

        return redirect()->route('officer.members.queries.run', $savedQuery);
    }

    public function rename(RenameSavedQueryRequest $request, SavedQuery $savedQuery): RedirectResponse
    {
        $savedQuery->fill($request->validated())->forceFill(['updated_by' => $request->user()->id])->save();

        return back();
    }

    public function destroy(DeleteSavedQueryRequest $request, SavedQuery $savedQuery): RedirectResponse
    {
        $savedQuery->delete();

        return redirect()->route('officer.members.queries');
    }

    public function run(SavedQueryRunner $runner, SavedQuery $savedQuery): Response
    {
        Gate::authorize('run', $savedQuery);

        $rows = $runner->rows($savedQuery);

        return Inertia::render('officer/members/queries/Run', [
            'query' => $savedQuery->only('id', 'name'),
            'clauseText' => $runner->describe($savedQuery),
            'count' => count($rows),
            'columns' => array_map(fn (string $column) => [
                'key' => $column,
                'label' => SavedQueryFields::columnLabel($column),
            ], $savedQuery->output_columns),
            'rows' => $rows,
            // Email results' recipient picker (#924): Current and Admin Members, loaded when the
            // dialog opens.
            'recipients' => Inertia::optional(fn (): array => Member::query()
                ->whereIn('category', [...Category::inGroup(CategoryGroup::Current), ...Category::inGroup(CategoryGroup::Admin)])
                ->orderBy('last_name')->orderBy('first_name')->orderBy('id')
                ->get(['id', 'first_name', 'last_name'])
                ->map(fn (Member $member): array => $member->only('id', 'first_name', 'last_name'))
                ->all()),
        ]);
    }

    /**
     * The allowlist the form offers, labelled in the page's language.
     *
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        $choices = fn (SavedQueryFieldType $type) => collect($type->options() ?? [])
            ->map(fn (string $label, string|int $value) => ['value' => (string) $value, 'label' => $label])
            ->values()
            ->all();

        $sources = array_map(fn (string $source) => [
            'key' => $source,
            'label' => __("saved_queries.source.{$source}"),
            'fields' => collect($source === 'member' ? SavedQueryFields::MEMBER : SavedQueryFields::RELATED[$source]['fields'])
                ->map(fn (SavedQueryFieldType $type, string $field) => [
                    'key' => $field,
                    'label' => SavedQueryFields::fieldLabel($source, $field),
                    'type' => $type->value,
                    'operators' => $type->operators(),
                    'options' => $type->options() === null ? null : $choices($type),
                ])
                ->values()
                ->all(),
        ], SavedQueryFields::sources());

        return [
            'sources' => $sources,
            'operators' => array_map(fn (string $operator) => [
                'key' => $operator,
                'label' => __("saved_queries.operator.{$operator}"),
            ], SavedQueryFieldType::OPERATORS),
            'columns' => array_map(fn (string $column) => [
                'key' => $column,
                'label' => SavedQueryFields::columnLabel($column),
            ], SavedQueryFields::outputColumns()),
            'sortFields' => array_map(fn (string $field) => [
                'key' => $field,
                'label' => SavedQueryFields::fieldLabel('member', $field),
            ], SavedQueryFields::sortFields()),
            'maxClauses' => SavedQuery::MAX_CLAUSES,
        ];
    }
}
