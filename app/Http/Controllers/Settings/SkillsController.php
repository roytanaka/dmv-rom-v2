<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\SkillsUpdateRequest;
use App\Models\SkillCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class SkillsController extends Controller
{
    /**
     * Show the Member's self-service Skills page (#246, PRD #243): the active catalog
     * grouped under category headings in `display_order`, with the Member's current
     * selections pre-checked. Retired skills/categories drop out (the `active` scopes).
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Skills', [
            'catalog' => $this->catalog(),
            // The Member's held skill ids — drives which checkboxes render pre-checked.
            // Records-only data, but this is the owner's own settings response, so it
            // is theirs to see (never added to any peer-visible payload — PRD #247).
            'selected' => $request->user()->skills()->pluck('skills.id'),
            // Per-resource UI hint from MemberPolicy (ADR-0017 §9): may this Member edit
            // their record? The server still enforces the action; this only drives
            // whether the save control renders enabled.
            'can' => [
                'update' => $request->user()->can('update', $request->user()),
            ],
        ]);
    }

    /**
     * Replace the Member's full skill selection to match the submitted set (#246): one
     * PATCH adds newly checked and removes newly unchecked via `sync`. Validation has
     * already rejected any unknown or inactive id, so there is no partial write.
     */
    public function update(SkillsUpdateRequest $request): RedirectResponse
    {
        $request->user()->skills()->sync($request->validated('skills') ?? []);

        return to_route('settings.skills');
    }

    /**
     * The active catalog for the page: categories in `display_order`, each carrying its
     * active skills. Names render as authored (content, single-language per the
     * bilingual boundary — only chrome is translated).
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function catalog()
    {
        return SkillCategory::query()
            ->active()
            ->with(['skills' => fn ($query) => $query->active()->orderBy('name')])
            ->get()
            ->map(fn (SkillCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'skills' => $category->skills->map(fn ($skill) => [
                    'id' => $skill->id,
                    'name' => $skill->name,
                ])->values(),
            ]);
    }
}
