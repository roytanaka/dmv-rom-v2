<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateRenewalSettingsRequest;
use App\Models\Member;
use App\Models\RenewalSetting;
use App\Support\DocumentStorage;
use App\Support\FileResponse;
use App\Support\OrgTime;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Renewal settings (#937, ADR-0038 §9): member administration's page for the one settings row
 * that runs the year's Renewal, under Officer Tools → Members. The `administer-members` Gate.
 */
class RenewalSettingsController extends Controller
{
    public function show(): Response
    {
        Gate::authorize('administer-members');

        $row = RenewalSetting::current()->load('updatedBy');

        return Inertia::render('officer/members/RenewalSettings', [
            'settings' => [
                'show_renewal' => $row->show_renewal,
                'renewal_year' => $row->renewal_year,
                'active_send_date' => $row->active_send_date?->toDateString(),
                'sustaining_send_date' => $row->sustaining_send_date?->toDateString(),
                'active_message_1' => $row->active_message_1,
                'active_message_2' => $row->active_message_2,
                'sustaining_message_1' => $row->sustaining_message_1,
                'sustaining_message_2' => $row->sustaining_message_2,
                'external_addresses' => $row->external_addresses,
                'attachment' => $row->sustaining_attachment_path === null ? null : $row->sustaining_attachment_filename,
            ],
            // The season so far: Members whose renewal year is the year (they submitted the form)
            // and whose paid-through year is (Records has typed their payment).
            'counts' => $row->renewal_year === null ? null : [
                'renewed' => Member::query()->where('renewal_year', $row->renewal_year)->count(),
                'paid' => Member::query()->where('paid_through_year', $row->renewal_year)->count(),
            ],
            'lastUpdated' => $row->updatedBy === null ? null : [
                'name' => $row->updatedBy->fullName(),
                'date' => OrgTime::toLocal($row->updated_at)->toDateString(),
            ],
        ]);
    }

    /**
     * Save the row. A new Sustaining attachment replaces the stored file; a save without one keeps it.
     */
    public function update(UpdateRenewalSettingsRequest $request, DocumentStorage $storage): RedirectResponse
    {
        $row = RenewalSetting::current();
        $row->fill([
            ...$request->safe()->except(['sustaining_attachment', 'external_addresses']),
            'show_renewal' => $request->boolean('show_renewal'),
            'external_addresses' => array_values($request->validated('external_addresses') ?? []),
        ]);

        $replaced = null;
        if ($request->hasFile('sustaining_attachment')) {
            $replaced = $row->sustaining_attachment_path;
            $stored = $storage->store($request->file('sustaining_attachment'));
            $row->forceFill([
                'sustaining_attachment_path' => $stored['storage_path'],
                'sustaining_attachment_filename' => $stored['original_filename'],
                'sustaining_attachment_mime_type' => $stored['mime_type'],
            ]);
        }

        $row->updated_by = $request->user()->id;
        $row->save();

        if ($replaced !== null) {
            $storage->delete($replaced);
        }

        return back();
    }

    /**
     * The Sustaining attachment, for member administration to check what goes out: always as a
     * download under its original name, never inline.
     */
    public function attachment(): StreamedResponse
    {
        Gate::authorize('administer-members');

        $row = RenewalSetting::current();

        abort_unless(
            $row->sustaining_attachment_path !== null && Storage::disk('local')->exists($row->sustaining_attachment_path),
            404,
        );

        return FileResponse::make(
            $row->sustaining_attachment_path,
            (string) $row->sustaining_attachment_filename,
            (string) $row->sustaining_attachment_mime_type,
            [],
            true,
        );
    }
}
