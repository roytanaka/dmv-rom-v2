<?php

use App\Enums\Role;
use App\Models\Document;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\Member;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
 * Role-matrix HTTP harness for the Document library (#712, spec #290, ADR-0030).
 *
 * Read (download): a member of the owning Group and the super-tier. Manage (upload): the
 * Group's Librarian, its Chair, and the super-tier. Both only while the Group has the
 * documents capability. Parentage never grants a read or a write. Exercised through every
 * layer: route → auth → Form Request / controller → DocumentPolicy → Gate::before.
 *
 * Folder visibility (`members`) and the Private-Group rows that depend on it land with the
 * Folders ticket (#714).
 */

beforeEach(function () {
    Storage::fake('local');
});

function documentsGroup(array $attributes = []): Group
{
    return Group::factory()->create(['has_documents' => true, ...$attributes]);
}

function documentsMemberOf(Group $group, ?Role $role = null): Member
{
    $member = Member::factory()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);

    if ($role !== null) {
        GroupMemberRole::factory()->role($role)->create(['group_member_id' => $membership->id]);
    }

    return $member;
}

function rootDocumentOf(Group $group): Document
{
    $document = Document::factory()->create(['group_id' => $group->id]);
    Storage::disk('local')->put($document->storage_path, 'bytes');

    return $document;
}

function uploadTo(Group $group): array
{
    return ['file' => UploadedFile::fake()->create('minutes.pdf', 10, 'application/pdf')];
}

// --- Read (download) --------------------------------------------------------------

it('lets the Group\'s members and officers download', function (?Role $role) {
    $group = documentsGroup();
    $document = rootDocumentOf($group);

    $this->actingAs(documentsMemberOf($group, $role))
        ->get(route('documents.download', $document))
        ->assertOk();
})->with([
    'ordinary member' => [null],
    'Librarian' => [Role::Librarian],
    'Chair' => [Role::Chair],
]);

it('lets the super-tier download without membership', function () {
    $document = rootDocumentOf(documentsGroup());

    $this->actingAs(Member::factory()->superTier()->create())
        ->get(route('documents.download', $document))
        ->assertOk();
});

it('refuses a non-member a Document at the library root', function () {
    $document = rootDocumentOf(documentsGroup());

    $this->actingAs(Member::factory()->create())
        ->get(route('documents.download', $document))
        ->assertForbidden();
});

it('refuses a parent Group\'s Chair who has not joined the child', function () {
    $parent = documentsGroup();
    $child = documentsGroup(['parent_id' => $parent->id]);
    $document = rootDocumentOf($child);

    $this->actingAs(documentsMemberOf($parent, Role::Chair))
        ->get(route('documents.download', $document))
        ->assertForbidden();
});

it('refuses a Librarian of another Group', function () {
    $document = rootDocumentOf(documentsGroup());

    $this->actingAs(documentsMemberOf(documentsGroup(), Role::Librarian))
        ->get(route('documents.download', $document))
        ->assertForbidden();
});

it('refuses a non-member a Private Group\'s Document and lets its member in', function () {
    $group = Group::factory()->privateListing()->create(['has_documents' => true]);
    $document = rootDocumentOf($group);

    $this->actingAs(Member::factory()->create())
        ->get(route('documents.download', $document))
        ->assertForbidden();

    $this->actingAs(documentsMemberOf($group))
        ->get(route('documents.download', $document))
        ->assertOk();
});

it('refuses every download once the documents capability is off, super-tier included', function (string $who) {
    $group = documentsGroup();
    $document = rootDocumentOf($group);
    $actor = $who === 'super-tier' ? Member::factory()->superTier()->create() : documentsMemberOf($group, Role::Librarian);
    $group->update(['has_documents' => false]);

    $this->actingAs($actor)
        ->get(route('documents.download', $document))
        ->assertNotFound();

    $this->assertDatabaseCount('document_downloads', 0);
})->with(['Librarian', 'super-tier']);

it('rechecks access on each download, so a member who left is refused', function () {
    $group = documentsGroup();
    $document = rootDocumentOf($group);
    $member = documentsMemberOf($group);

    $this->actingAs($member)->get(route('documents.download', $document))->assertOk();

    GroupMember::query()->where('member_id', $member->id)->delete();
    $member->unsetRelation('memberships');

    $this->actingAs($member)->get(route('documents.download', $document))->assertForbidden();
});

// --- Manage (upload) ----------------------------------------------------------------

it('lets the Librarian, the Chair, and the super-tier upload', function (string $who) {
    $group = documentsGroup();
    $actor = match ($who) {
        'Librarian' => documentsMemberOf($group, Role::Librarian),
        'Chair' => documentsMemberOf($group, Role::Chair),
        'super-tier' => Member::factory()->superTier()->create(),
    };

    $this->actingAs($actor)
        ->post(route('documents.store', $group), uploadTo($group))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(Document::sole()->group_id)->toBe($group->id);
})->with(['Librarian', 'Chair', 'super-tier']);

it('forbids an upload from anyone who does not manage the library', function (string $who) {
    $group = documentsGroup();
    $actor = match ($who) {
        'ordinary member' => documentsMemberOf($group),
        'Secretary' => documentsMemberOf($group, Role::Secretary),
        'Librarian of another Group' => documentsMemberOf(documentsGroup(), Role::Librarian),
        'non-member' => Member::factory()->create(),
        'parent Chair' => documentsMemberOf(tap(documentsGroup(), fn (Group $parent) => $group->update(['parent_id' => $parent->id])), Role::Chair),
    };

    $this->actingAs($actor)
        ->post(route('documents.store', $group), uploadTo($group))
        ->assertForbidden();

    expect(Document::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
})->with(['ordinary member', 'Secretary', 'Librarian of another Group', 'non-member', 'parent Chair']);

it('forbids an upload to a Group without the documents capability, super-tier included', function (string $who) {
    $group = Group::factory()->create(['has_documents' => false]);
    $actor = $who === 'super-tier' ? Member::factory()->superTier()->create() : documentsMemberOf($group, Role::Chair);

    $this->actingAs($actor)
        ->post(route('documents.store', $group), uploadTo($group))
        ->assertForbidden();

    expect(Document::count())->toBe(0);
})->with(['Chair', 'super-tier']);

it('redirects an unauthenticated upload to login', function () {
    $group = documentsGroup();

    $this->post(route('documents.store', $group), uploadTo($group))
        ->assertRedirect(route('login'));
});
