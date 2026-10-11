<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteBookingRequest;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Requests\UpdateBookingRequest;
use App\Models\Booking;
use App\Models\Group;
use App\Support\Notices\BookingMailWriter;
use Illuminate\Http\RedirectResponse;

/**
 * A Group's Bookings (#795, ADR-0032) — the group tours a client books. Adding one writes the
 * Booking and its one Shift on the month's group-tour Schedule in a single transaction
 * ({@see Booking::book()}). Every write is authorized in its Form Request through the BookingPolicy.
 */
class BookingController extends Controller
{
    /**
     * Add a Booking and its Shift. The first Booking in a month creates and publishes that
     * month's group-tour Schedule; later ones reuse it.
     */
    public function store(StoreBookingRequest $request, Group $group, BookingMailWriter $mails): RedirectResponse
    {
        $booking = Booking::book(
            $group,
            $request->startsAt(),
            $request->endsAt(),
            (int) $request->validated('docents_needed'),
            $request->bookingAttributes(),
        );

        // The Request, or the Confirmation when the Booking is already full (#799, §9).
        $mails->onCreate($booking, $request->user());

        return back();
    }

    /**
     * Change a Booking and its Shift (#796). A date in another month moves the Shift to that
     * month's group-tour Schedule ({@see Booking::change()}).
     */
    public function update(UpdateBookingRequest $request, Booking $booking): RedirectResponse
    {
        $booking->change(
            $request->startsAt(),
            $request->endsAt(),
            (int) $request->validated('docents_needed'),
            $request->bookingAttributes(),
        );

        return back();
    }

    /**
     * Delete a Booking (#796): deleting its Shift cascades to the Booking and the Shift's
     * Sign-ups. The month's group-tour Schedule stays, even empty.
     */
    public function destroy(DeleteBookingRequest $request, Booking $booking): RedirectResponse
    {
        $booking->shift->delete();

        return back();
    }
}
