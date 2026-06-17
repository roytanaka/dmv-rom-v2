<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Requests\DeleteNewsRequest;
use App\Http\Requests\StoreNewsRequest;
use App\Http\Requests\UpdateNewsRequest;
use App\Models\GroupMember;
use App\Models\News;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The org-wide news feed (#155, ADR-0017 §5). Reading is open to every logged-in
 * member; writing is gated by a capability-scoped news-editor role on the posting
 * Group, enforced in the Form Requests (StoreNewsRequest / UpdateNewsRequest) and
 * the NewsPolicy. The `can` props are UI hints only — the server enforces.
 */
class NewsController extends Controller
{
    /**
     * The feed: one org-wide stream, newest first, readable by every member
     * regardless of their Groups. Eager-loads each item's posting Group for
     * attribution and the viewer's roles so the per-item `can` hints resolve in
     * memory rather than per-call.
     */
    public function index(Request $request): Response
    {
        $viewer = $request->user();
        $viewer->loadMissing('memberships.roles', 'memberships.group');

        $items = News::with('postingGroup')->latest()->get();

        return Inertia::render('news/Index', [
            'news' => $items->map(fn (News $item) => [
                'id' => $item->id,
                'title' => $item->title,
                'body' => $item->body,
                'group' => $item->postingGroup->name,
                'posted_at' => $item->created_at,
                'can' => [
                    'update' => $viewer->can('update', $item),
                    'delete' => $viewer->can('delete', $item),
                ],
            ])->all(),
            // The announcements-on Groups this member may post on behalf of — the
            // options for the "New post" form. Empty for a member who holds no
            // news-editor role anywhere.
            'postableGroups' => $viewer->memberships
                ->filter(fn (GroupMember $m) => $m->group->has_announcements
                    && $viewer->canActAs(Role::NewsEditor, $m->group))
                ->map(fn (GroupMember $m) => ['id' => $m->group->id, 'name' => $m->group->name])
                ->values()
                ->all(),
            // Coarse hint for the "New post" control: may this member post to the
            // feed on behalf of any announcements-on Group they belong to?
            'can' => [
                'create' => $viewer->can('post-news'),
            ],
        ]);
    }

    /**
     * Post a new item. Authorization and the field whitelist both live in the
     * Form Request (ADR-0017 §4).
     */
    public function store(StoreNewsRequest $request): RedirectResponse
    {
        News::create($request->validated());

        return back();
    }

    /**
     * Edit an item. The posting Group is fixed at creation, so only the content
     * fields are updated.
     */
    public function update(UpdateNewsRequest $request, News $news): RedirectResponse
    {
        $news->update($request->validated());

        return back();
    }

    /**
     * Delete an item. Authorization lives in the Form Request (ADR-0017 §4).
     */
    public function destroy(DeleteNewsRequest $request, News $news): RedirectResponse
    {
        $news->delete();

        return back();
    }
}
