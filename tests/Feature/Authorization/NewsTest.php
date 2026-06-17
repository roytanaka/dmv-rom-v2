<?php

use App\Enums\Role;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\Member;
use App\Models\News;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Role-matrix HTTP harness for the org-wide news feed (#155, ADR-0017 §5).
 *
 * Exercises the feed through every layer — route → auth middleware → Form Request
 * authorize() → NewsPolicy → Gate::before — as a per-action allow-and-deny matrix
 * over the spine factories. Reading is open to every member; writing is gated by a
 * capability-scoped news-editor role on the posting Group. The deny rows
 * (no-role member, wrong-Group editor, non-announcements Group, unauthenticated)
 * are required, not optional: they prove the deny-by-default / fail-closed posture.
 */

/** An announcements-on Group — the only kind a news item may be posted on behalf of. */
function announcementsGroup(): Group
{
    return Group::factory()->create(['has_announcements' => true]);
}

/** A member holding the news-editor role in the given Group. */
function newsEditorOf(Group $group): Member
{
    $member = Member::factory()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);
    GroupMemberRole::factory()->role(Role::NewsEditor)->create(['group_member_id' => $membership->id]);

    return $member;
}

/** A member holding a given role in the given Group. */
function roleHolderOf(Group $group, Role $role): Member
{
    $member = Member::factory()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);
    GroupMemberRole::factory()->role($role)->create(['group_member_id' => $membership->id]);

    return $member;
}

$post = ['title' => 'A headline', 'body' => 'Some news body.'];

// --- Reading the feed (open to every member) --------------------------------

it('lets any logged-in member read the feed regardless of their Groups', function () {
    News::factory()->count(3)->create();

    $this->actingAs(Member::factory()->create())
        ->get(route('news'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('news/Index')->has('news', 3));
});

it('redirects an unauthenticated feed read to login', function () {
    $this->get(route('news'))->assertRedirect(route('login'));
});

// --- Posting (store) — allow rows -------------------------------------------

it('lets a news-editor post on behalf of their announcements-on Group', function () use ($post) {
    $group = announcementsGroup();

    $this->actingAs(newsEditorOf($group))
        ->post(route('news.store'), [...$post, 'posting_group_id' => $group->id])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(News::sole()->posting_group_id)->toBe($group->id);
});

it('lets a Chair of an announcements-on Group post (Chair-implication)', function () use ($post) {
    $group = announcementsGroup();

    $this->actingAs(roleHolderOf($group, Role::Chair))
        ->post(route('news.store'), [...$post, 'posting_group_id' => $group->id])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(News::count())->toBe(1);
});

it('lets a super-tier member post on behalf of any announcements-on Group', function () use ($post) {
    $group = announcementsGroup();

    $this->actingAs(Member::factory()->superTier()->create())
        ->post(route('news.store'), [...$post, 'posting_group_id' => $group->id])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(News::count())->toBe(1);
});

// --- Posting (store) — deny rows (required) ---------------------------------

it('forbids a member with no role from posting', function () use ($post) {
    $group = announcementsGroup();

    $this->actingAs(Member::factory()->create())
        ->post(route('news.store'), [...$post, 'posting_group_id' => $group->id])
        ->assertForbidden();

    expect(News::count())->toBe(0);
});

it('forbids a news-editor of one Group from posting on behalf of another', function () use ($post) {
    $editor = newsEditorOf(announcementsGroup());
    $other = announcementsGroup();

    $this->actingAs($editor)
        ->post(route('news.store'), [...$post, 'posting_group_id' => $other->id])
        ->assertForbidden();

    expect(News::count())->toBe(0);
});

it('forbids posting on behalf of a Group with announcements off', function () use ($post) {
    // A Chair (Chair-implication would grant news-editor) of a Group whose
    // announcements capability is off — there is nothing to post to.
    $group = Group::factory()->create(['has_announcements' => false]);

    $this->actingAs(roleHolderOf($group, Role::Chair))
        ->post(route('news.store'), [...$post, 'posting_group_id' => $group->id])
        ->assertForbidden();

    expect(News::count())->toBe(0);
});

it('redirects an unauthenticated post to login', function () use ($post) {
    $group = announcementsGroup();

    $this->post(route('news.store'), [...$post, 'posting_group_id' => $group->id])
        ->assertRedirect(route('login'));
});

// --- Editing (update) -------------------------------------------------------

it('lets a news-editor edit an item their Group posted', function () {
    $group = announcementsGroup();
    $item = News::factory()->create(['posting_group_id' => $group->id]);

    $this->actingAs(newsEditorOf($group))
        ->patch(route('news.update', $item), ['title' => 'Edited', 'body' => 'Edited body.'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($item->fresh()->title)->toBe('Edited');
});

it('lets a super-tier member edit any item', function () {
    $item = News::factory()->create();

    $this->actingAs(Member::factory()->superTier()->create())
        ->patch(route('news.update', $item), ['title' => 'Edited', 'body' => 'Edited body.'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($item->fresh()->title)->toBe('Edited');
});

it('forbids a news-editor of another Group from editing an item', function () {
    $item = News::factory()->create(['posting_group_id' => announcementsGroup()->id]);

    $this->actingAs(newsEditorOf(announcementsGroup()))
        ->patch(route('news.update', $item), ['title' => 'Edited', 'body' => 'Edited body.'])
        ->assertForbidden();

    expect($item->fresh()->title)->not->toBe('Edited');
});

it('forbids a member with no role from editing an item', function () {
    $item = News::factory()->create();

    $this->actingAs(Member::factory()->create())
        ->patch(route('news.update', $item), ['title' => 'Edited', 'body' => 'Edited body.'])
        ->assertForbidden();

    expect($item->fresh()->title)->not->toBe('Edited');
});

it('redirects an unauthenticated edit to login', function () {
    $item = News::factory()->create();

    $this->patch(route('news.update', $item), ['title' => 'Edited', 'body' => 'Edited body.'])
        ->assertRedirect(route('login'));
});

// --- Deleting (destroy) -----------------------------------------------------

it('lets a news-editor delete an item their Group posted', function () {
    $group = announcementsGroup();
    $item = News::factory()->create(['posting_group_id' => $group->id]);

    $this->actingAs(newsEditorOf($group))
        ->delete(route('news.destroy', $item))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(News::count())->toBe(0);
});

it('lets a super-tier member delete any item', function () {
    $item = News::factory()->create();

    $this->actingAs(Member::factory()->superTier()->create())
        ->delete(route('news.destroy', $item))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(News::count())->toBe(0);
});

it('forbids a news-editor of another Group from deleting an item', function () {
    $item = News::factory()->create(['posting_group_id' => announcementsGroup()->id]);

    $this->actingAs(newsEditorOf(announcementsGroup()))
        ->delete(route('news.destroy', $item))
        ->assertForbidden();

    expect(News::count())->toBe(1);
});

it('forbids a member with no role from deleting an item', function () {
    $item = News::factory()->create();

    $this->actingAs(Member::factory()->create())
        ->delete(route('news.destroy', $item))
        ->assertForbidden();

    expect(News::count())->toBe(1);
});

it('redirects an unauthenticated delete to login', function () {
    $item = News::factory()->create();

    $this->delete(route('news.destroy', $item))->assertRedirect(route('login'));
});

// --- `can` hints drive the controls (UI hint only) --------------------------

it('hints create on for a news-editor and off for an ordinary member', function () {
    $editor = newsEditorOf(announcementsGroup());

    $this->actingAs($editor)
        ->get(route('news'))
        ->assertInertia(fn (Assert $page) => $page->where('can.create', true)->has('postableGroups', 1));

    $this->actingAs(Member::factory()->create())
        ->get(route('news'))
        ->assertInertia(fn (Assert $page) => $page->where('can.create', false)->has('postableGroups', 0));
});

it('hints per-item update/delete only for an editor of the posting Group', function () {
    $group = announcementsGroup();
    News::factory()->create(['posting_group_id' => $group->id]);

    $this->actingAs(newsEditorOf($group))
        ->get(route('news'))
        ->assertInertia(fn (Assert $page) => $page->where('news.0.can.update', true)->where('news.0.can.delete', true));

    $this->actingAs(Member::factory()->create())
        ->get(route('news'))
        ->assertInertia(fn (Assert $page) => $page->where('news.0.can.update', false)->where('news.0.can.delete', false));
});
