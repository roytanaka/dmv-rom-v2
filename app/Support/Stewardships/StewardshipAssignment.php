<?php

namespace App\Support\Stewardships;

use App\Enums\Role;
use App\Enums\StewardshipFunction;
use App\Models\GroupStewardship;
use App\Models\Member;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Moving a stewardship between Groups (#846, ADR-0036 §7): the preview of who gains and who
 * loses its authority, and the reassignment itself. Authority is membership, any status, in a
 * holder Group, resolved live by every reader, so it follows on the next request.
 */
final class StewardshipAssignment
{
    /**
     * Make the stewardship's holder rows exactly the given Groups. A new row records its
     * creator; every row written records the actor as its last writer (ADR-0036 §8).
     *
     * @param  list<int>  $holderIds
     */
    public static function assign(StewardshipFunction $function, array $holderIds, Member $actor): void
    {
        DB::transaction(function () use ($function, $holderIds, $actor) {
            GroupStewardship::query()
                ->where('function', $function)
                ->whereNotIn('group_id', $holderIds)
                ->delete();

            foreach ($holderIds as $groupId) {
                $row = GroupStewardship::query()->firstOrNew(['group_id' => $groupId, 'function' => $function]);
                if (! $row->exists) {
                    $row->created_by = $actor->id;
                }
                $row->updated_by = $actor->id;
                // A re-save of an unchanged holder is still a change of record: stamp it.
                $row->touch();
            }
        });
    }

    /**
     * Who gains and who loses each live stewardship's authority if the draft were saved. The
     * same reading as `Member::hasMemberAdminAuthority()` and `Member::isOrgWideSender()`. A
     * Chair of an active Group sends org-wide whatever the stewardship says, so org-wide sender
     * leaves them out. A null member-admin draft keeps the saved holder.
     *
     * @param  list<int>  $orgMail
     * @return array<string, array{gain: list<array{id: int, name: string}>, lose: list<array{id: int, name: string}>}>
     */
    public static function preview(?int $memberAdmin, array $orgMail): array
    {
        $savedMemberAdmin = self::holderIds(StewardshipFunction::MemberAdmin);

        $chairs = Member::query()
            ->whereHas('memberships', fn (Builder $membership) => $membership
                ->whereHas('roles', fn (Builder $role) => $role->where('role', Role::Chair))
                ->whereHas('group', fn (Builder $group) => $group->active()))
            ->pluck('id');

        return [
            StewardshipFunction::MemberAdmin->value => self::authorityDiff($savedMemberAdmin, $memberAdmin === null ? $savedMemberAdmin : [$memberAdmin], collect()),
            StewardshipFunction::OrgMail->value => self::authorityDiff(self::holderIds(StewardshipFunction::OrgMail), $orgMail, $chairs),
        ];
    }

    /**
     * @return list<int>
     */
    private static function holderIds(StewardshipFunction $function): array
    {
        return GroupStewardship::query()->where('function', $function)->pluck('group_id')->all();
    }

    /**
     * Members of the draft holders not in the saved ones (gain), and the reverse (lose),
     * each sorted by surname. Members in `$unaffected` carry the authority either way.
     *
     * @param  list<int>  $savedIds
     * @param  list<int>  $draftIds
     * @param  Collection<int, int>  $unaffected
     * @return array{gain: list<array{id: int, name: string}>, lose: list<array{id: int, name: string}>}
     */
    private static function authorityDiff(array $savedIds, array $draftIds, Collection $unaffected): array
    {
        $membersOf = fn (array $groupIds) => Member::query()
            ->whereHas('memberships', fn (Builder $membership) => $membership->whereIn('group_id', $groupIds))
            ->get()
            ->keyBy('id');

        $before = $membersOf($savedIds);
        $after = $membersOf($draftIds);

        $rows = fn (Collection $members) => $members
            ->reject(fn (Member $member) => $unaffected->contains($member->id))
            ->sortBy(fn (Member $member) => $member->surnameKey())
            ->map(fn (Member $member) => ['id' => $member->id, 'name' => $member->fullName()])
            ->values()
            ->all();

        return [
            'gain' => $rows($after->diffKeys($before)),
            'lose' => $rows($before->diffKeys($after)),
        ];
    }
}
