<?php

namespace App\Support\Authorization;

use App\Models\Booking;
use App\Models\BookingType;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentFolder;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\HandlingObject;
use App\Models\HoursRecord;
use App\Models\Meeting;
use App\Models\News;
use App\Models\Qualification;
use App\Models\Schedule;
use App\Models\Shift;
use App\Models\ShiftKind;
use App\Models\SignUp;
use App\Models\Tour;
use Illuminate\Database\Eloquent\Model;

/**
 * The read-only rule for inactive Groups (#839, ADR-0036 §2): an archived Group, or a
 * time-boxed Group past its end date, refuses every write to its content — for every actor,
 * the super-tier included. Reads stay open to whoever could read before.
 *
 * The rule runs inside the `Gate::before` closure (AppServiceProvider), ahead of the
 * super-tier short-circuit, so it is the one place a Group-scoped ability is refused
 * outright. Two questions decide it: is the ability a write ({@see self::isWrite()}), and
 * which Group owns the subject being written ({@see self::groupFor()}). An ability with no
 * Group behind it — `administer-members`, `update` on a Member, the Group-less
 * `administer-groups` gate the DMV Settings endpoints use — never resolves a Group and is
 * untouched. That is what lets the super-tier unarchive a Group: unarchive is not a
 * Group-scoped write ability.
 */
final class GroupWrites
{
    /**
     * Every policy ability that changes a Group's content. Anything not listed is a read.
     * `manage` on {@see Tour} also gates the qualification screens, which are management
     * surfaces; they close with the rest.
     *
     * @var list<string>
     */
    public const WRITE_ABILITIES = [
        'create',
        'update',
        'delete',
        'deleteAny',
        'publish',
        'unpublish',
        'move',
        'manage',
        'manageRules',
        'manageShiftKinds',
        'manageObjects',
        'createSelfServe',
        'assign',
        'assignAny',
        'removeAny',
        'substitute',
        'changeTour',
        'record',
        'updateReminders',
        'updateEmptyDeskAlert',
        'updateSelfServe',
        'change',
        'place',
        'sendMails',
        'correctEarned',
        'enterExhibitionRevenue',
        'recalculate',
    ];

    /**
     * Should this ability check be refused because the Group it writes to is inactive?
     *
     * @param  array<int, mixed>  $arguments  the Gate's ability arguments, as passed to `can()`
     */
    public static function refuses(string $ability, array $arguments): bool
    {
        if (! self::isWrite($ability)) {
            return false;
        }

        $group = self::groupFor($arguments);

        return $group !== null && ! $group->isActive();
    }

    /**
     * Is this ability one that changes a Group's content?
     */
    public static function isWrite(string $ability): bool
    {
        return in_array($ability, self::WRITE_ABILITIES, true);
    }

    /**
     * The Group that owns the subject of an ability check, read off the first Model among the
     * arguments — a class-string (`can('create', [Shift::class, $schedule])`) is skipped. Null
     * when no argument is a Model, or the first Model has no owning Group.
     *
     * @param  array<int, mixed>  $arguments
     */
    public static function groupFor(array $arguments): ?Group
    {
        foreach ($arguments as $argument) {
            if ($argument instanceof Model) {
                return self::owningGroupOf($argument);
            }
        }

        return null;
    }

    /**
     * Walk a content Model to the Group that owns it. Each step mirrors the relation the
     * model's own policy walks, so a route-bound subject resolves without a lazy-load the
     * policy would not have made itself.
     */
    private static function owningGroupOf(Model $model): ?Group
    {
        return match (true) {
            $model instanceof Group => $model,
            $model instanceof Schedule => $model->group,
            $model instanceof Shift => $model->schedule->group,
            $model instanceof SignUp => $model->shift->schedule->group,
            $model instanceof News => $model->postingGroup,
            $model instanceof Qualification => $model->membership->group,
            $model instanceof Meeting,
            $model instanceof Document,
            $model instanceof DocumentFolder,
            $model instanceof DocumentCategory,
            $model instanceof GroupMember,
            $model instanceof Booking,
            $model instanceof BookingType,
            $model instanceof Tour,
            $model instanceof ShiftKind,
            $model instanceof HandlingObject,
            $model instanceof HoursRecord => $model->group,
            default => null,
        };
    }
}
