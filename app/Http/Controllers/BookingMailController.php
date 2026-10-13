<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Support\Notices\BookingMailWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Sending a Booking's Request or Confirmation again (#799, ADR-0032 §9). The Booking was mailed
 * once on create; a Booker, the Chair or super-tier sends either again from the Booking, Reply-To
 * themselves. Both write Delivery rows through the {@see BookingMailWriter}; the Drain sends them.
 */
class BookingMailController extends Controller
{
    /**
     * Send the Request again: to every Member who may give the Booking's Tour.
     */
    public function request(Request $request, Booking $booking, BookingMailWriter $mails): RedirectResponse
    {
        abort_unless($request->user()->can('sendMails', $booking), 403);

        $mails->request($booking, $request->user());

        return back();
    }

    /**
     * Send the Confirmation again: to every Member on the Booking.
     */
    public function confirmation(Request $request, Booking $booking, BookingMailWriter $mails): RedirectResponse
    {
        abort_unless($request->user()->can('sendMails', $booking), 403);

        $mails->confirmation($booking, $request->user());

        return back();
    }
}
