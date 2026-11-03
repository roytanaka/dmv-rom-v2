<?php

namespace App\Enums;

use App\Models\Member;
use Illuminate\Database\Eloquent\Builder;

/**
 * A Member list (ADR-0038 §5): one of the seven Records office rosters under Officer Tools →
 * Members, in menu order. Each maps to its {@see CategoryGroup}; Donors reads the Donor Category
 * alone, and Past the removed-data marker. Applicants has no Members here: its URL word is the
 * Applicants page's (#928).
 *
 * Backed string enum: the value is the list's English URL word, the {list} route parameter.
 */
enum MemberList: string
{
    case Current = 'current';
    case Resigned = 'resigned';
    case Past = 'past';
    case Deceased = 'deceased';
    case Admin = 'admin';
    case Donors = 'donors';
    case Applicants = 'applicants';

    /**
     * The Members of this list. A Resigned or Deceased Member leaves their list for Past once
     * their personal data is removed, as the Legacy app's group X did. Null for Applicants.
     *
     * @return Builder<Member>|null
     */
    public function members(): ?Builder
    {
        return match ($this) {
            self::Current => Member::query()->whereIn('category', Category::inGroup(CategoryGroup::Current)),
            self::Resigned => Member::query()
                ->whereIn('category', Category::inGroup(CategoryGroup::Resigned))
                ->whereNull('personal_data_removed_at'),
            self::Past => Member::query()->past(),
            self::Deceased => Member::query()
                ->whereIn('category', Category::inGroup(CategoryGroup::Deceased))
                ->whereNull('personal_data_removed_at'),
            self::Admin => Member::query()->whereIn('category', Category::inGroup(CategoryGroup::Admin)),
            self::Donors => Member::query()->where('category', Category::Donor),
            self::Applicants => null,
        };
    }

    /**
     * The row actions, as the Legacy app gave each list.
     *
     * @return list<string>
     */
    public function rowActions(): array
    {
        return match ($this) {
            self::Current => ['edit', 'photo'],
            self::Resigned => ['edit', 'reinstate'],
            self::Past, self::Deceased, self::Admin => ['edit', 'delete'],
            self::Donors, self::Applicants => ['edit'],
        };
    }

    /** Whether the list offers Send email: Current and Resigned, as the Legacy app did. */
    public function offersSendEmail(): bool
    {
        return in_array($this, [self::Current, self::Resigned], true);
    }

    /**
     * The seven tabs of the Member lists, each linking its list in the active locale. Applicants
     * links the Applicants page (#928), which owns that URL word.
     *
     * @return list<array{key: string, href: string}>
     */
    public static function tabs(): array
    {
        return array_map(fn (self $tab) => [
            'key' => $tab->value,
            'href' => route('officer.members', $tab === self::Current ? [] : ['list' => $tab->urlWord()], false),
        ], self::cases());
    }

    /** The list's URL word in the active locale, from the route tables (ADR-0008). */
    public function urlWord(): string
    {
        return basename(__("routes.officer.members.list.{$this->value}"));
    }
}
