<?php

use App\Enums\DocumentVisibility;
use App\Enums\Role;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentFolder;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\Member;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Role-matrix HTTP harness for the Document library (#712, spec #290, ADR-0030).
 *
 * Read (download): a member of the owning Group and the super-tier; any Member too when the
 * Document sits under a `members` top-level Folder (#715, ADR-0030 §5), except in a Private
 * Group (ADR-0019). Manage: the Group's Librarian, its Chair, and the super-tier. Both only
 * while the Group has the documents capability. Parentage never grants a read or a write
 * (ADR-0030 §6). Exercised through every layer: route → auth → Form Request / controller →
 * DocumentPolicy / DocumentFolderPolicy → Gate::before.
 *
 * The Folder visibility matrix proves read three ways (the Folder listed in the props, the
 * Folder's page, the download) and manage by setting a Folder's visibility. The Document
 * category matrix at the end (#724) gives managing Document categories the same rows.
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

// --- Folder visibility matrix (#715) -------------------------------------------------

/** One actor of the matrix, relative to `$group`. */
function libraryActor(Group $group, string $who): Member
{
    return match ($who) {
        'member' => documentsMemberOf($group),
        'Librarian' => documentsMemberOf($group, Role::Librarian),
        'Chair' => documentsMemberOf($group, Role::Chair),
        'non-member' => Member::factory()->create(),
        'parent Chair' => documentsMemberOf(tap(documentsGroup(), fn (Group $parent) => $group->update(['parent_id' => $parent->id])), Role::Chair),
        'super-tier' => Member::factory()->superTier()->create(),
    };
}

/**
 * A top-level Folder with the given visibility, a subfolder, and one Document in the subfolder.
 *
 * @return array{DocumentFolder, DocumentFolder, Document}
 */
function sharedFolderOf(Group $group, DocumentVisibility $visibility): array
{
    $folder = DocumentFolder::factory()->create(['group_id' => $group->id, 'name' => 'Handbooks', 'visibility' => $visibility]);
    $sub = DocumentFolder::factory()->in($folder)->create(['name' => 'Tours']);
    $document = Document::factory()->create(['group_id' => $group->id, 'folder_id' => $sub->id]);
    Storage::disk('local')->put($document->storage_path, 'bytes');

    return [$folder, $sub, $document];
}

it('reads a Folder, its subfolders and their Documents per the Folder\'s visibility', function (string $who, DocumentVisibility $visibility, bool $reads) {
    $group = documentsGroup();
    [$folder, $sub, $document] = sharedFolderOf($group, $visibility);
    $actor = libraryActor($group, $who);

    $this->actingAs($actor)
        ->get(route('groups.show', ['group' => $group, 'section' => 'documents']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('library.sections', $reads ? 1 : 0));

    foreach ([$folder, $sub] as $item) {
        $this->actingAs($actor)
            ->get(route('groups.documents.folder', ['group' => $group, 'folder' => $item]))
            ->assertStatus($reads ? 200 : 403);
    }

    $this->actingAs($actor)
        ->get(route('documents.download', $document))
        ->assertStatus($reads ? 200 : 403);
})->with([
    'member, group' => ['member', DocumentVisibility::Group, true],
    'member, members' => ['member', DocumentVisibility::Members, true],
    'Librarian, group' => ['Librarian', DocumentVisibility::Group, true],
    'Chair, group' => ['Chair', DocumentVisibility::Group, true],
    'non-member, group' => ['non-member', DocumentVisibility::Group, false],
    'non-member, members' => ['non-member', DocumentVisibility::Members, true],
    'parent Chair, group' => ['parent Chair', DocumentVisibility::Group, false],
    'parent Chair, members' => ['parent Chair', DocumentVisibility::Members, true],
    'super-tier, group' => ['super-tier', DocumentVisibility::Group, true],
]);

it('keeps a Private Group\'s library from non-members, even in a members Folder', function (string $who, int $status) {
    $group = Group::factory()->privateListing()->create(['has_documents' => true]);
    [$folder, , $document] = sharedFolderOf($group, DocumentVisibility::Members);
    $actor = libraryActor($group, $who);

    // The Private page-gate hides the Group itself (404); the download refuses (403).
    $this->actingAs($actor)
        ->get(route('groups.documents.folder', ['group' => $group, 'folder' => $folder]))
        ->assertStatus($status);

    $this->actingAs($actor)
        ->get(route('documents.download', $document))
        ->assertStatus($status === 200 ? 200 : 403);
})->with([
    'non-member' => ['non-member', 404],
    'parent Chair' => ['parent Chair', 404],
    'member' => ['member', 200],
    'super-tier' => ['super-tier', 200],
]);

it('closes a members Folder to everyone once the capability is off, super-tier included', function (string $who) {
    $group = documentsGroup();
    [$folder, , $document] = sharedFolderOf($group, DocumentVisibility::Members);
    $actor = libraryActor($group, $who);
    $group->update(['has_documents' => false]);

    $this->actingAs($actor)
        ->get(route('groups.documents.folder', ['group' => $group, 'folder' => $folder]))
        ->assertNotFound();

    $this->actingAs($actor)
        ->get(route('documents.download', $document))
        ->assertNotFound();

    $this->actingAs($actor)
        ->patch(route('document-folders.update', $folder), ['name' => 'Handbooks', 'visibility' => 'group'])
        ->assertForbidden();

    expect($folder->fresh()->visibility())->toBe(DocumentVisibility::Members);
})->with(['member', 'non-member', 'Librarian', 'super-tier']);

it('lets only the Librarian, the Chair and the super-tier set a Folder\'s visibility', function (string $who, bool $manages) {
    $group = documentsGroup();
    [$folder] = sharedFolderOf($group, DocumentVisibility::Group);

    $this->actingAs(libraryActor($group, $who))
        ->patch(route('document-folders.update', $folder), ['name' => 'Handbooks', 'visibility' => 'members'])
        ->assertStatus($manages ? 302 : 403);

    expect($folder->fresh()->visibility())->toBe($manages ? DocumentVisibility::Members : DocumentVisibility::Group);
})->with([
    'Librarian' => ['Librarian', true],
    'Chair' => ['Chair', true],
    'super-tier' => ['super-tier', true],
    'member' => ['member', false],
    'non-member' => ['non-member', false],
    'parent Chair' => ['parent Chair', false],
]);

it('refuses every write to a non-member who reads a members Folder', function () {
    $group = documentsGroup();
    [$folder, $sub, $document] = sharedFolderOf($group, DocumentVisibility::Members);
    $reader = libraryActor($group, 'non-member');

    $this->actingAs($reader)->post(route('document-folders.store', $group), ['name' => 'Mine', 'parent_id' => $folder->id])->assertForbidden();
    $this->actingAs($reader)->patch(route('document-folders.move', $sub), ['parent_id' => null])->assertForbidden();
    $this->actingAs($reader)->post(route('documents.store', $group), [...uploadTo($group), 'folder_id' => $folder->id])->assertForbidden();
    $this->actingAs($reader)->delete(route('documents.destroy', $document))->assertForbidden();

    expect(DocumentFolder::count())->toBe(2)->and(Document::count())->toBe(1);
});

// --- Document categories (#724) ------------------------------------------------------

it('lets only the Librarian, the Chair and the super-tier manage Document categories', function (string $who, bool $manages) {
    $group = documentsGroup();
    $folder = DocumentFolder::factory()->create(['group_id' => $group->id]);
    $category = DocumentCategory::factory()->in($folder)->create(['name' => 'Europe']);
    $doomed = DocumentCategory::factory()->in($folder)->create(['name' => 'Asia']);
    $actor = libraryActor($group, $who);
    $status = $manages ? 302 : 403;

    $this->actingAs($actor)
        ->post(route('document-categories.store', $group), ['name' => 'Egypt', 'folder_id' => $folder->id])
        ->assertStatus($status);
    $this->actingAs($actor)
        ->patch(route('document-categories.update', $category), ['name' => 'Europe & Near East'])
        ->assertStatus($status);
    $this->actingAs($actor)
        ->delete(route('document-categories.destroy', $doomed))
        ->assertStatus($status);

    expect(DocumentCategory::where('name', 'Egypt')->exists())->toBe($manages)
        ->and($category->fresh()->name)->toBe($manages ? 'Europe & Near East' : 'Europe')
        ->and($doomed->fresh() === null)->toBe($manages);
})->with([
    'Librarian' => ['Librarian', true],
    'Chair' => ['Chair', true],
    'super-tier' => ['super-tier', true],
    'member' => ['member', false],
    'non-member' => ['non-member', false],
    'parent Chair' => ['parent Chair', false],
]);

it('refuses Document category writes once the capability is off, super-tier included', function (string $who) {
    $group = Group::factory()->create(['has_documents' => false]);
    $category = DocumentCategory::factory()->create(['group_id' => $group->id, 'name' => 'Europe']);
    $actor = $who === 'super-tier' ? Member::factory()->superTier()->create() : documentsMemberOf($group, Role::Chair);

    $this->actingAs($actor)->post(route('document-categories.store', $group), ['name' => 'Egypt'])->assertForbidden();
    $this->actingAs($actor)->patch(route('document-categories.update', $category), ['name' => 'Asia'])->assertForbidden();
    $this->actingAs($actor)->delete(route('document-categories.destroy', $category))->assertForbidden();

    expect(DocumentCategory::sole()->name)->toBe('Europe');
})->with(['Chair', 'super-tier']);

it('reads a Folder filed under a Document category per its top-level Folder only', function (string $who, bool $reads) {
    $group = documentsGroup();
    [$folder, $sub, $document] = sharedFolderOf($group, DocumentVisibility::Group);
    $category = DocumentCategory::factory()->in($folder)->create();
    $sub->update(['category_id' => $category->id]);
    $document->update(['category_id' => DocumentCategory::factory()->in($sub)->create()->id]);
    $actor = libraryActor($group, $who);

    $this->actingAs($actor)->get(route('groups.documents.folder', ['group' => $group, 'folder' => $sub]))->assertStatus($reads ? 200 : 403);
    $this->actingAs($actor)->get(route('documents.download', $document))->assertStatus($reads ? 200 : 403);
})->with([
    'member' => ['member', true],
    'non-member' => ['non-member', false],
]);
