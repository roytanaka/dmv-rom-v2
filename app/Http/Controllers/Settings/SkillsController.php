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
     * Show the Member's skill selection page with the active catalog pre-checked to
     * their current selections.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Skills', [
            'catalog' => $this->catalog(),
            // Not shared in any peer-visible payload; only the owner's own settings page.
            'selected' => $request->user()->skills()->pluck('skills.id'),
            // Fine-grained, per-resource UI hint (ADR-0017 §9): may this member edit
            // this record? The server still enforces the action; this only drives
            // whether the form's controls render enabled.
            'can' => [
                'update' => $request->user()->can('update', $request->user()),
            ],
        ]);
    }

    /**
     * Replace the Member's full skill selection via `sync`. Any unknown or inactive id
     * was already rejected by validation, so there is no partial write.
     */
    public function update(SkillsUpdateRequest $request): RedirectResponse
    {
        $request->user()->skills()->sync($request->validated('skills') ?? []);

        return to_route('settings.skills');
    }

    /**
     * Active catalog: categories in display_order, each carrying their active skills.
     * Names come from DB and render as-authored — content, not chrome, so not translated.
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
