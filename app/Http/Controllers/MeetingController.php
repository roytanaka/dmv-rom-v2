<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteMeetingRequest;
use App\Http\Requests\StoreMeetingRequest;
use App\Http\Requests\UpdateMeetingRequest;
use App\Models\Group;
use App\Models\Meeting;
use Illuminate\Http\RedirectResponse;

/**
 * Officer meetings CRUD (#193, PRD #186) — the write seam for a Group's meetings,
 * parallel to the read surface on {@see GroupController::show}. Every mutation is
 * structurally authorized in its Form Request, which delegates to the MeetingPolicy
 * (a Secretary or Chair of the Group, plus the super-tier, and only while the
 * Group's meetings capability is on). The `can` props on the Meetings tab are UI
 * hints only — the server enforces here regardless.
 */
class MeetingController extends Controller
{
    /**
     * Add a meeting to a Group. The owning Group comes from the route; the fields
     * and links come from the Form Request whitelist. Links (the conventional
     * agenda / minutes / report set) are written alongside the meeting.
     */
    public function store(StoreMeetingRequest $request, Group $group): RedirectResponse
    {
        $data = $request->validated();
        $links = $data['links'] ?? [];
        unset($data['links']);

        $meeting = $group->meetings()->create($data);
        $this->syncLinks($meeting, $links);

        return back();
    }

    /**
     * Edit a meeting — its fields, its published/hidden state, and its links. The
     * owning Group is fixed at creation. Links are synced wholesale only when the
     * request carries them, so a field-only edit never silently drops them.
     */
    public function update(UpdateMeetingRequest $request, Meeting $meeting): RedirectResponse
    {
        $data = $request->validated();
        $hasLinks = array_key_exists('links', $data);
        $links = $data['links'] ?? [];
        unset($data['links']);

        $meeting->update($data);

        if ($hasLinks) {
            $this->syncLinks($meeting, $links);
        }

        return back();
    }

    /**
     * Delete a meeting. Its links cascade at the database.
     */
    public function destroy(DeleteMeetingRequest $request, Meeting $meeting): RedirectResponse
    {
        $meeting->delete();

        return back();
    }

    /**
     * Replace a meeting's links with the supplied set — the simplest sync for the
     * fixed agenda / minutes / report trio, where each save sends the complete
     * intended state.
     *
     * @param  list<array{kind: string, url: string}>  $links
     */
    private function syncLinks(Meeting $meeting, array $links): void
    {
        $meeting->links()->delete();
        $meeting->links()->createMany($links);
    }
}
