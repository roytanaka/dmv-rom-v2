<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteBookingTypeRequest;
use App\Http\Requests\ReorderBookingTypesRequest;
use App\Http\Requests\StoreBookingTypeRequest;
use App\Http\Requests\UpdateBookingTypeRequest;
use App\Http\Requests\UpdateGroupTourSettingsRequest;
use App\Models\BookingType;
use App\Models\Group;
use Illuminate\Http\RedirectResponse;

/**
 * Maintaining a Group's booking types and group-tour settings (#794, ADR-0032 §6) — the Settings
 * tab's Group tours card: add, rename, re-rate, retire, restore, reorder, delete one added in
 * error, and set the group-tour shift kind and Schedule label. Every mutation is authorized in its
 * Form Request through the BookingTypePolicy's `manage` gate.
 */
class BookingTypeController extends Controller
{
    /**
     * Add a booking type to the Group, active and last in the order.
     */
    public function store(StoreBookingTypeRequest $request, Group $group): RedirectResponse
    {
        $group->bookingTypes()->create([
            ...$request->validated(),
            'active' => true,
            'sort_order' => ($group->bookingTypes()->max('sort_order') ?? -1) + 1,
        ]);

        return back();
    }

    /**
     * Rename, re-rate, retire or restore.
     */
    public function update(UpdateBookingTypeRequest $request, BookingType $bookingType): RedirectResponse
    {
        $bookingType->update($request->validated());

        return back();
    }

    /**
     * Reorder the Group's booking types to match the submitted id list.
     */
    public function reorder(ReorderBookingTypesRequest $request, Group $group): RedirectResponse
    {
        foreach ($request->validated('ids') as $position => $id) {
            $group->bookingTypes()->whereKey($id)->update(['sort_order' => $position]);
        }

        return back();
    }

    /**
     * Delete a booking type no Booking uses.
     */
    public function destroy(DeleteBookingTypeRequest $request, BookingType $bookingType): RedirectResponse
    {
        $bookingType->delete();

        return back();
    }

    /**
     * Save the group-tour shift kind and Schedule label.
     */
    public function updateSettings(UpdateGroupTourSettingsRequest $request, Group $group): RedirectResponse
    {
        $group->update($request->validated());

        return back();
    }
}
