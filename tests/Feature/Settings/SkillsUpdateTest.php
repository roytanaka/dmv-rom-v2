<?php

namespace Tests\Feature\Settings;

use App\Models\Member;
use App\Models\Skill;
use App\Models\SkillCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SkillsUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_skills_page_renders_with_current_selections_pre_checked()
    {
        $user = Member::factory()->create();
        $category = SkillCategory::factory()->create(['display_order' => 0]);
        $held = Skill::factory()->create(['category_id' => $category->id]);
        $unheld = Skill::factory()->create(['category_id' => $category->id]);
        $user->skills()->attach($held);

        $this->actingAs($user)
            ->get('/settings/skills')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Skills')
                ->where('selected', fn ($selected) => collect($selected)->contains($held->id)
                    && ! collect($selected)->contains($unheld->id)));
    }

    public function test_catalog_is_grouped_by_active_category_in_display_order()
    {
        $user = Member::factory()->create();
        $second = SkillCategory::factory()->create(['display_order' => 1]);
        $first = SkillCategory::factory()->create(['display_order' => 0]);
        Skill::factory()->create(['category_id' => $first->id]);
        Skill::factory()->create(['category_id' => $second->id]);

        // A retired category and a retired skill must not appear in the catalog.
        $retiredCategory = SkillCategory::factory()->retired()->create();
        Skill::factory()->create(['category_id' => $retiredCategory->id]);
        $retiredSkill = Skill::factory()->retired()->create(['category_id' => $first->id]);

        $this->actingAs($user)
            ->get('/settings/skills')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Skills')
                ->where('catalog', function ($catalog) use ($first, $second, $retiredCategory, $retiredSkill) {
                    $catalog = collect($catalog);
                    $ids = $catalog->pluck('id')->all();

                    // Active categories only, ordered by display_order.
                    return $ids === [$first->id, $second->id]
                        && ! in_array($retiredCategory->id, $ids, true)
                        && ! collect($catalog->firstWhere('id', $first->id)['skills'])
                            ->pluck('id')->contains($retiredSkill->id);
                }));
    }

    public function test_patch_persists_exactly_the_submitted_set()
    {
        $user = Member::factory()->create();
        $category = SkillCategory::factory()->create();
        $a = Skill::factory()->create(['category_id' => $category->id]);
        $b = Skill::factory()->create(['category_id' => $category->id]);

        $response = $this->actingAs($user)
            ->patch('/settings/skills', ['skills' => [$a->id, $b->id]]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/settings/skills');

        $this->assertEqualsCanonicalizing([$a->id, $b->id], $user->skills()->pluck('skills.id')->all());
    }

    public function test_unchecking_removes_rows()
    {
        $user = Member::factory()->create();
        $category = SkillCategory::factory()->create();
        $keep = Skill::factory()->create(['category_id' => $category->id]);
        $drop = Skill::factory()->create(['category_id' => $category->id]);
        $user->skills()->attach([$keep->id, $drop->id]);

        $response = $this->actingAs($user)
            ->patch('/settings/skills', ['skills' => [$keep->id]]);

        $response->assertSessionHasNoErrors();

        $this->assertSame([$keep->id], $user->skills()->pluck('skills.id')->all());
    }

    public function test_submitting_all_unchecked_clears_the_selection()
    {
        $user = Member::factory()->create();
        $category = SkillCategory::factory()->create();
        $skill = Skill::factory()->create(['category_id' => $category->id]);
        $user->skills()->attach($skill);

        $response = $this->actingAs($user)
            ->patch('/settings/skills', ['skills' => []]);

        $response->assertSessionHasNoErrors();

        $this->assertCount(0, $user->skills()->get());
    }

    public function test_unknown_skill_id_is_rejected_with_no_partial_write()
    {
        $user = Member::factory()->create();
        $category = SkillCategory::factory()->create();
        $valid = Skill::factory()->create(['category_id' => $category->id]);

        $response = $this->actingAs($user)
            ->from('/settings/skills')
            ->patch('/settings/skills', ['skills' => [$valid->id, 999999]]);

        $response
            ->assertSessionHasErrors('skills.1')
            ->assertRedirect('/settings/skills');

        $this->assertCount(0, $user->skills()->get());
    }

    public function test_inactive_skill_id_is_rejected()
    {
        $user = Member::factory()->create();
        $category = SkillCategory::factory()->create();
        $retired = Skill::factory()->retired()->create(['category_id' => $category->id]);

        $response = $this->actingAs($user)
            ->from('/settings/skills')
            ->patch('/settings/skills', ['skills' => [$retired->id]]);

        $response->assertSessionHasErrors('skills.0');

        $this->assertCount(0, $user->skills()->get());
    }

    public function test_skill_in_a_retired_category_is_rejected()
    {
        $user = Member::factory()->create();
        $retiredCategory = SkillCategory::factory()->retired()->create();
        $skill = Skill::factory()->create(['category_id' => $retiredCategory->id]);

        $response = $this->actingAs($user)
            ->from('/settings/skills')
            ->patch('/settings/skills', ['skills' => [$skill->id]]);

        $response->assertSessionHasErrors('skills.0');

        $this->assertCount(0, $user->skills()->get());
    }
}
