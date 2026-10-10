<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateEarnedCorrectionRequest;
use App\Models\Booking;
use Illuminate\Http\RedirectResponse;

/**
 * The Statistician's correction to a Booking's Earned (#797, ADR-0032 §7). Earned is worked out on
 * read ({@see Booking::earned()}); a correction replaces it until cleared. Authorized in the Form
 * Request through the BookingPolicy's `correctEarned` gate.
 */
class BookingEarnedController extends Controller
{
    /**
     * Set the correction, or clear it with null.
     */
    public function update(UpdateEarnedCorrectionRequest $request, Booking $booking): RedirectResponse
    {
        $amount = $request->validated('earned_correction');

        $booking->correctEarned($amount === null ? null : (string) $amount);

        return back();
    }
}
