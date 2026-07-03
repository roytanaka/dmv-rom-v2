<?php

namespace Database\Seeders;

use App\Models\Skill;
use App\Models\SkillCategory;
use Illuminate\Database\Seeder;

/**
 * The org-owned Skills catalog (PRD #243): the fixed vocabulary of skill categories
 * and skills a Member selects from on the Settings → Skills page. Transcribed from
 * the organization's existing curated list (Communications, Business Skills, Digital
 * Knowledge, Leadership Skills, …) and living here as the single source of truth —
 * editing the vocabulary means editing this seeder until a real admin surface is
 * needed (deferred, PRD #243 Out of Scope).
 *
 * Faker-free (plain Eloquent) so it runs under the `--no-dev` staging build like
 * {@see DatabaseSeeder} / {@see DemoSeeder}, and idempotent: every category and skill
 * keys on its unique `code`, so a re-run (`migrate:fresh --seed` or a repeat
 * `db:seed`) heals rather than duplicates. Categories carry a deliberate
 * `display_order` fixing the heading order the page renders; the `active` flag (all
 * seeded rows active) is the retirement lever that hides a skill or category without
 * deleting rows, preserving `member_skill` history.
 */
class SkillCatalogSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->catalog() as $order => $category) {
            $model = SkillCategory::firstOrCreate(
                ['code' => $category['code']],
                [
                    'name' => $category['name'],
                    'display_order' => $order,
                    'active' => true,
                ],
            );

            foreach ($category['skills'] as $skill) {
                Skill::firstOrCreate(
                    ['code' => $skill['code']],
                    [
                        'category_id' => $model->id,
                        'name' => $skill['name'],
                        'active' => true,
                    ],
                );
            }
        }
    }

    /**
     * The curated catalog, in the deliberate display order categories appear in.
     * Each `code` is the stable idempotency key; `name` renders as authored (content,
     * single-language per the bilingual boundary — only chrome is translated).
     *
     * @return list<array{code: string, name: string, skills: list<array{code: string, name: string}>}>
     */
    private function catalog(): array
    {
        return [
            [
                'code' => 'communications',
                'name' => 'Communications',
                'skills' => [
                    ['code' => 'copywriting', 'name' => 'Copywriting'],
                    ['code' => 'editing-proofreading', 'name' => 'Editing & proofreading'],
                    ['code' => 'translation', 'name' => 'Translation (English / French)'],
                    ['code' => 'public-speaking', 'name' => 'Public speaking'],
                    ['code' => 'social-media', 'name' => 'Social media'],
                    ['code' => 'photography', 'name' => 'Photography'],
                    ['code' => 'graphic-design', 'name' => 'Graphic design'],
                ],
            ],
            [
                'code' => 'business-skills',
                'name' => 'Business Skills',
                'skills' => [
                    ['code' => 'bookkeeping', 'name' => 'Bookkeeping'],
                    ['code' => 'budgeting', 'name' => 'Budgeting'],
                    ['code' => 'project-management', 'name' => 'Project management'],
                    ['code' => 'event-planning', 'name' => 'Event planning'],
                    ['code' => 'fundraising', 'name' => 'Fundraising'],
                    ['code' => 'spreadsheets', 'name' => 'Spreadsheets'],
                ],
            ],
            [
                'code' => 'digital-knowledge',
                'name' => 'Digital Knowledge',
                'skills' => [
                    ['code' => 'web-programming', 'name' => 'Web programming'],
                    ['code' => 'database-management', 'name' => 'Database management'],
                    ['code' => 'data-entry', 'name' => 'Data entry'],
                    ['code' => 'video-editing', 'name' => 'Video editing'],
                    ['code' => 'it-support', 'name' => 'IT support'],
                ],
            ],
            [
                'code' => 'leadership-skills',
                'name' => 'Leadership Skills',
                'skills' => [
                    ['code' => 'coaching-mentoring', 'name' => 'Coaching & mentoring'],
                    ['code' => 'facilitation', 'name' => 'Facilitation'],
                    ['code' => 'committee-chairing', 'name' => 'Committee chairing'],
                    ['code' => 'recruitment', 'name' => 'Recruitment'],
                    ['code' => 'conflict-resolution', 'name' => 'Conflict resolution'],
                ],
            ],
        ];
    }
}
