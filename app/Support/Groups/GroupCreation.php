<?php

namespace App\Support\Groups;

use App\Enums\Kind;
use App\Enums\LifecycleState;
use App\Enums\ListingVisibility;
use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Models\Group;
use App\Models\Member;
use Illuminate\Support\Facades\DB;

/**
 * Creating a Group from DMV Settings (ADR-0036 §4, §11). Both shapes start from the same
 * defaults and record the creator as its first writer (ADR-0036 §8).
 */
final class GroupCreation
{
    /**
     * Where every new Group starts: active, public listing, display order 0, no logo, not
     * time-boxed, every capability and collection flag off, hours multiplier 1.
     */
    private const DEFAULTS = [
        'lifecycle_state' => LifecycleState::Active,
        'listing_visibility' => ListingVisibility::Public,
        'display_order' => 0,
        'logo_key' => null,
        'time_boxed' => false,
        'has_meetings' => false,
        'has_documents' => false,
        'has_scheduling' => false,
        'has_vetting' => false,
        'has_announcements' => false,
        'has_bookings' => false,
        'collects_visitor_count' => false,
        'collects_extra_interactions' => false,
        'collects_visitor_provenance' => false,
        'visitor_figures_await_booking' => false,
        'hours_multiplier' => 1,
    ];

    /**
     * The super-tier's Group (#842): its validated fields (name, slug, parent, kind) on top of
     * the defaults.
     *
     * @param  array<string, mixed>  $fields
     */
    public static function create(array $fields, Member $actor): Group
    {
        return Group::create([
            ...self::DEFAULTS,
            ...$fields,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
    }

    /**
     * A parent officer's sub-group (#884): a name and a parent, the slug made here. It starts as
     * a Legacy sub-committee did: a Working group with Meetings and Documents on and a Group
     * listing. An optional Chair from the parent's roster (#885) joins it as Full with the Chair
     * role, in the same transaction: no Group is left behind if that write fails.
     */
    public static function subGroup(Group $parent, string $name, ?int $chairId, Member $actor): Group
    {
        return DB::transaction(function () use ($parent, $name, $chairId, $actor) {
            $group = self::create([
                'name' => $name,
                'slug' => SubGroupSlug::for($name, $parent),
                'parent_id' => $parent->id,
                'kind' => Kind::WorkingGroup,
                'listing_visibility' => ListingVisibility::Group,
                'has_meetings' => true,
                'has_documents' => true,
            ], $actor);

            if ($chairId !== null) {
                $group->memberships()
                    ->create(['member_id' => $chairId, 'status' => MembershipStatus::Full])
                    ->roles()->create(['role' => Role::Chair]);
            }

            return $group;
        });
    }
}
