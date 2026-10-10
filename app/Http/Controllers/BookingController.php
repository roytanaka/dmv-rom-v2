<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteBookingRequest;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Requests\UpdateBookingRequest;
use App\Models\Booking;
use App\Models\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * A Group's Bookings (#795, ADR-0032) — the group tours a client books. Adding one writes the
 * Booking and its one Shift on the month's group-tour Schedule in a single transaction
 * ({@see Booking::book()}). The client suggestions are a JSON read the Booking form fetches as the
 * Booker types (§10). Every write is authorized in its Form Request through the BookingPolicy.
 */
class BookingController extends Controller
{
    /**
     * The most suggestions the client field offers at once.
     */
    private const SUGGESTION_LIMIT = 10;

    /**
     * Add a Booking and its Shift. The first Booking in a month creates and publishes that
     * month's group-tour Schedule; later ones reuse it.
     */
    public function store(StoreBookingRequest $request, Group $group): RedirectResponse
    {
        Booking::book(
            $group,
            $request->startsAt(),
            $request->endsAt(),
            (int) $request->validated('docents_needed'),
            $request->bookingAttributes(),
        );

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

    /**
     * The Group's past client names matching `q`, for the form's suggestions (§10): distinct, in
     * alphabetical order, at most {@see SUGGESTION_LIMIT}. There is no client record; the names
     * are what earlier Bookings typed. Whoever may add a Booking may read them.
     */
    public function clients(Request $request, Group $group): JsonResponse
    {
        abort_unless($request->user()->can('suggestClients', [Booking::class, $group]), 403);

        $needle = trim((string) $request->query('q', ''));

        $clients = $group->bookings()
            ->when($needle !== '', fn ($query) => $query->where('client', 'like', '%'.addcslashes($needle, '%_\\').'%'))
            ->distinct()
            ->orderBy('client')
            ->limit(self::SUGGESTION_LIMIT)
            ->pluck('client');

        return response()->json(['clients' => $clients->values()]);
    }
}
