<?php

namespace App\Support\Bookings;

use App\Models\Booking;
use App\Models\BookingType;
use App\Models\Group;
use App\Models\Member;
use App\Models\Shift;
use App\Models\ShiftKind;
use App\Models\SignUp;
use App\Models\Tour;

/**
 * The Group page's Booking payloads (#794–#798, ADR-0032), built by the Group page: the Settings
 * tab's Group tours card ({@see self::settings()}), a Shift's Booking block filtered by viewer
 * ({@see self::forShift()}), the Booking form's pickers ({@see self::options()}) and client
 * suggestions ({@see self::clients()}), and a seat-holder's substitutes ({@see self::substitutes()}).
 * Each read is gated here or by its caller through the BookingPolicy; the Form Requests re-check
 * every write.
 */
class BookingPayloads
{
    /**
     * The Group tours card (#794, ADR-0032 §1, §4, §6): the booking types in order, retired ones
     * included, with their rates; the group-tour shift kind and Schedule label; and the Group's
     * shift kinds to pick from.
     *
     * @return array{types: list<array{id: int, name: string, ratePerVisitor: string, ratePerDocentHour: string, active: bool, sortOrder: int}>, shiftKindId: int|null, label: string|null, copyEmail: string|null, shiftKinds: list<array{id: int, name: string, active: bool}>}
     */
    public static function settings(Group $group): array
    {
        return [
            'types' => $group->bookingTypes()->ordered()->get()
                ->map(fn (BookingType $type): array => [
                    'id' => $type->id,
                    'name' => $type->name,
                    'ratePerVisitor' => $type->rate_per_visitor,
                    'ratePerDocentHour' => $type->rate_per_docent_hour,
                    'active' => $type->active,
                    'sortOrder' => $type->sort_order,
                ])->values()->all(),
            'shiftKindId' => $group->group_tour_shift_kind_id,
            'label' => $group->group_tour_label,
            'copyEmail' => $group->booking_copy_email,
            'shiftKinds' => $group->shiftKinds()->orderBy('sort_order')->get()
                ->map(fn (ShiftKind $kind): array => [
                    'id' => $kind->id,
                    'name' => $kind->name,
                    'active' => $kind->active,
                ])->values()->all(),
        ];
    }

    /**
     * A Booking's Schedule block (#795, ADR-0032 §5), filtered by viewer: the server sends only the
     * fields the viewer may read. Everyone who can read the Schedule gets the Tour. A current
     * Member of the Group also gets `details` (client, visitors, type, leader, comments); a Booker,
     * Statistician, Chair or super-tier also gets `officer` (order number and date, and Earned with
     * #797). A withheld block is null. Null for a Shift with no Booking.
     *
     * Reads the eager-loaded `booking.tour` and `booking.bookingType`; the Group is set from the
     * Shift's Schedule so the policy never lazy-loads it.
     *
     * @return array{id: int, tour: string, tour_id: int, details: array<string, mixed>|null, officer: array<string, mixed>|null, edit: array<string, mixed>|null, can_delete: bool, can_send_mails: bool}|null
     */
    public static function forShift(Member $viewer, Shift $shift): ?array
    {
        $booking = $shift->booking;

        if ($booking === null) {
            return null;
        }

        $booking->setRelation('group', $shift->schedule->group);
        $booking->setRelation('shift', $shift);

        return [
            'id' => $booking->id,
            'tour' => $booking->tour->name,
            'tour_id' => $booking->tour_id,
            'details' => $viewer->can('viewDetails', $booking) ? [
                'client' => $booking->client,
                'visitors' => $booking->visitors,
                'type' => $booking->bookingType->name,
                'booking_type_id' => $booking->booking_type_id,
                'leader' => $booking->leader,
                'comments' => $booking->comments,
            ] : null,
            'officer' => $viewer->can('viewOfficerFields', $booking) ? [
                'order_number' => $booking->order_number,
                'order_date' => $booking->order_date?->toDateString(),
                // Earned (#797, ADR-0032 §7): the correction while set, else worked out on read.
                'earned' => $booking->earned(),
                'earned_is_corrected' => $booking->isEarnedCorrected(),
                'earned_correction' => $booking->earned_correction,
                'can_correct_earned' => $viewer->can('correctEarned', $booking),
            ] : null,
            // The change form's values (#796), for a viewer who may change the Booking; null
            // otherwise, and the Edit control does not render.
            'edit' => $viewer->can('update', $booking) ? self::editValues($shift, $booking) : null,
            'can_delete' => $viewer->can('delete', $booking),
            // The Send Request and Send Confirmation buttons (#799, §9). UI hint only.
            'can_send_mails' => $viewer->can('sendMails', $booking),
        ];
    }

    /**
     * The Booking form's pickers (#795, #796, ADR-0032 §1), for a viewer the BookingPolicy lets do
     * `$ability` on the Group: `create` for the add form (a Booker, Chair or super-tier), `change`
     * for the change form (a Statistician too). The Group's active Tours and active booking types,
     * each in the Group's order; the change form adds a Booking's own retired Tour or type itself.
     * Null for everyone else, and the control does not render.
     *
     * @param  'create'|'change'  $ability
     * @return array{tours: list<array{id: int, name: string}>, types: list<array{id: int, name: string}>}|null
     */
    public static function options(Member $viewer, Group $group, string $ability): ?array
    {
        if (! $viewer->can($ability, [Booking::class, $group])) {
            return null;
        }

        return [
            'tours' => $group->tours()->active()->ordered()->get()
                ->map(fn (Tour $tour): array => ['id' => $tour->id, 'name' => $tour->name])
                ->values()->all(),
            'types' => $group->bookingTypes()->active()->ordered()->get()
                ->map(fn (BookingType $type): array => ['id' => $type->id, 'name' => $type->name])
                ->values()->all(),
        ];
    }

    /**
     * The Group's past client names for the Booking form's suggestions (§10): distinct, in
     * alphabetical order. There is no client record; the names are what earlier Bookings typed.
     * Whoever may add or change a Booking reads them; everyone else gets none.
     *
     * @return list<string>
     */
    public static function clients(Member $viewer, Group $group): array
    {
        if (! $viewer->can('suggestClients', [Booking::class, $group])) {
            return [];
        }

        return $group->bookings()
            ->distinct()
            ->orderBy('client')
            ->pluck('client')
            ->values()
            ->all();
    }

    /**
     * The Members the holder may hand their Booking seat to (#798, ADR-0032 §8), for the
     * Substitute picker: id and full name. The holder alone, until the Shift starts; anyone else,
     * or a Sign-up that is gone, gets none.
     *
     * @return list<array{id: int, name: string}>
     */
    public static function substitutes(Member $viewer, ?SignUp $signUp): array
    {
        if ($signUp === null || ! $viewer->can('substitute', $signUp)) {
            return [];
        }

        return $signUp->eligibleSubstitutes()
            ->map(fn (Member $member): array => ['id' => $member->id, 'name' => $member->fullName()])
            ->values()
            ->all();
    }

    /**
     * A Booking's change-form values (#796): every field, the date and times on the org wall clock
     * and the docents needed from the Shift. Never the Earned correction (#797).
     *
     * @return array<string, mixed>
     */
    private static function editValues(Shift $shift, Booking $booking): array
    {
        $zone = config('app.org_timezone');

        return [
            'date' => $shift->starts_at->setTimezone($zone)->toDateString(),
            'starts_time' => $shift->starts_at->setTimezone($zone)->format('H:i'),
            'ends_time' => $shift->ends_at->setTimezone($zone)->format('H:i'),
            'docents_needed' => $shift->capacity,
            'tour_id' => $booking->tour_id,
            'booking_type_id' => $booking->booking_type_id,
            // The type's name, so the form can offer the Booking's own type once retired.
            'booking_type' => $booking->bookingType->name,
            'client' => $booking->client,
            'visitors' => $booking->visitors,
            'leader' => $booking->leader,
            'order_number' => $booking->order_number,
            'order_date' => $booking->order_date?->toDateString(),
            'comments' => $booking->comments,
        ];
    }
}
