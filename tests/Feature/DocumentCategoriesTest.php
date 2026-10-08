<?php

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
 * Document categories (#724, spec #721, ADR-0030 §4): a Librarian keeps a list of Document
 * categories on the library root and on each Folder, files that Folder's items under them,
 * and every reader sees the Folder page in sections.
 *
 * Asserted from outside: the HTTP response, the Inertia props and the rows in the database.
 */

function categoryLibrary(): Group
{
    return Group::factory()->create(['has_documents' => true]);
}

function categoryReader(Group $group, ?Role $role = null): Member
{
    $member = Member::factory()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);

    if ($role !== null) {
        GroupMemberRole::factory()->role($role)->create(['group_member_id' => $membership->id]);
    }

    return $member;
}

// --- Manage ---------------------------------------------------------------------

it('adds a Document category to the library root and to a Folder', function () {
    $group = categoryLibrary();
    $folder = DocumentFolder::factory()->create(['group_id' => $group->id]);
    $librarian = categoryReader($group, Role::Librarian);

    $this->actingAs($librarian)
        ->post(route('document-categories.store', $group), ['name' => 'Data Sheets'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $this->actingAs($librarian)
        ->post(route('document-categories.store', $group), ['name' => 'AAAP', 'folder_id' => $folder->id])
        ->assertSessionHasNoErrors();

    $root = DocumentCategory::where('name', 'Data Sheets')->sole();
    expect($root->group_id)->toBe($group->id)->and($root->folder_id)->toBeNull()
        ->and(DocumentCategory::where('name', 'AAAP')->sole()->folder_id)->toBe($folder->id);
});

it('keeps Document category names unique within one Folder, the root included', function () {
    $group = categoryLibrary();
    $folder = DocumentFolder::factory()->create(['group_id' => $group->id]);
    $librarian = categoryReader($group, Role::Librarian);
    DocumentCategory::factory()->create(['group_id' => $group->id, 'name' => 'Publications']);
    DocumentCategory::factory()->in($folder)->create(['name' => 'Europe']);

    $this->actingAs($librarian)
        ->post(route('document-categories.store', $group), ['name' => 'Publications'])
        ->assertSessionHasErrors(['name' => 'A category with this name is already here.']);

    $this->actingAs($librarian)
        ->post(route('document-categories.store', $group), ['name' => 'Europe', 'folder_id' => $folder->id])
        ->assertSessionHasErrors('name');

    // The same name in another Folder's list, or at the root beside a Folder's, is fine.
    $this->actingAs($librarian)
        ->post(route('document-categories.store', $group), ['name' => 'Publications', 'folder_id' => $folder->id])
        ->assertSessionHasNoErrors();
    $this->actingAs($librarian)
        ->post(route('document-categories.store', $group), ['name' => 'Europe'])
        ->assertSessionHasNoErrors();

    expect(DocumentCategory::where('group_id', $group->id)->count())->toBe(4);
});

it('refuses a Document category for another Group\'s Folder', function () {
    $group = categoryLibrary();
    $foreign = DocumentFolder::factory()->create(['group_id' => categoryLibrary()->id]);

    $this->actingAs(categoryReader($group, Role::Librarian))
        ->post(route('document-categories.store', $group), ['name' => 'Stray', 'folder_id' => $foreign->id])
        ->assertSessionHasErrors('folder_id');

    expect(DocumentCategory::count())->toBe(0);
});

it('renames a Document category, keeping names unique in its list', function () {
    $group = categoryLibrary();
    $librarian = categoryReader($group, Role::Librarian);
    $category = DocumentCategory::factory()->create(['group_id' => $group->id, 'name' => 'Data sheets']);
    DocumentCategory::factory()->create(['group_id' => $group->id, 'name' => 'Publications']);

    $this->actingAs($librarian)
        ->patch(route('document-categories.update', $category), ['name' => 'Publications'])
        ->assertSessionHasErrors('name');

    $this->actingAs($librarian)
        ->patch(route('document-categories.update', $category), ['name' => 'Data Sheets'])
        ->assertSessionHasNoErrors();

    expect($category->fresh()->name)->toBe('Data Sheets');
});

it('deletes a Document category and moves its Folders and Documents to Other', function () {
    $group = categoryLibrary();
    $category = DocumentCategory::factory()->create(['group_id' => $group->id]);
    $folder = DocumentFolder::factory()->create(['group_id' => $group->id, 'category_id' => $category->id]);
    $document = Document::factory()->create(['group_id' => $group->id, 'category_id' => $category->id]);

    $this->actingAs(categoryReader($group, Role::Librarian))
        ->delete(route('document-categories.destroy', $category))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(DocumentCategory::count())->toBe(0)
        ->and($folder->fresh()->category_id)->toBeNull()
        ->and($document->fresh()->category_id)->toBeNull();
});

it('removes a Folder\'s Document category list when the empty Folder is deleted', function () {
    $group = categoryLibrary();
    $folder = DocumentFolder::factory()->create(['group_id' => $group->id]);
    DocumentCategory::factory()->in($folder)->count(2)->create();
    $rootCategory = DocumentCategory::factory()->create(['group_id' => $group->id]);

    $this->actingAs(categoryReader($group, Role::Librarian))
        ->delete(route('document-folders.destroy', $folder))
        ->assertSessionHasNoErrors();

    expect(DocumentCategory::pluck('id')->all())->toBe([$rootCategory->id]);
});

// --- Sections -------------------------------------------------------------------

it('shows the root in a section per Document category, sorted by name, then Other', function () {
    $group = categoryLibrary();
    $sheets = DocumentCategory::factory()->create(['group_id' => $group->id, 'name' => 'Data Sheets']);
    $publications = DocumentCategory::factory()->create(['group_id' => $group->id, 'name' => 'Publications']);
    $empty = DocumentCategory::factory()->create(['group_id' => $group->id, 'name' => 'archive']);
    $asia = DocumentFolder::factory()->create(['group_id' => $group->id, 'name' => 'Asia', 'category_id' => $sheets->id]);
    $africa = DocumentFolder::factory()->create(['group_id' => $group->id, 'name' => 'africa', 'category_id' => $sheets->id]);
    $guide = Document::factory()->create(['group_id' => $group->id, 'title' => 'Guide', 'category_id' => $sheets->id]);
    $annual = Document::factory()->create(['group_id' => $group->id, 'title' => 'Annual report', 'category_id' => $publications->id]);
    $loose = DocumentFolder::factory()->create(['group_id' => $group->id, 'name' => 'Loose']);
    $note = Document::factory()->create(['group_id' => $group->id, 'title' => 'Note']);

    // A Librarian, who sees the empty Document category too (#725).
    $this->actingAs(categoryReader($group, Role::Librarian))
        ->get(route('groups.show', ['group' => $group, 'section' => 'documents']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('library.categories', [
                ['id' => $empty->id, 'name' => 'archive'],
                ['id' => $sheets->id, 'name' => 'Data Sheets'],
                ['id' => $publications->id, 'name' => 'Publications'],
            ])
            ->has('library.sections', 4)
            ->where('library.sections.0.category', ['id' => $empty->id, 'name' => 'archive'])
            ->has('library.sections.0.folders', 0)
            ->has('library.sections.0.documents', 0)
            ->where('library.sections.1.category.id', $sheets->id)
            ->where('library.sections.1.folderCount', 2)
            ->where('library.sections.1.documentCount', 1)
            ->where('library.sections.1.folders.0.id', $africa->id)
            ->where('library.sections.1.folders.0.categoryId', $sheets->id)
            ->where('library.sections.1.folders.1.id', $asia->id)
            ->where('library.sections.1.documents.0.id', $guide->id)
            ->where('library.sections.1.documents.0.categoryId', $sheets->id)
            ->where('library.sections.2.category.id', $publications->id)
            ->has('library.sections.2.folders', 0)
            ->where('library.sections.2.documents.0.id', $annual->id)
            ->where('library.sections.3.category', null)
            ->where('library.sections.3.folders.0.id', $loose->id)
            ->where('library.sections.3.folders.0.categoryId', null)
            ->where('library.sections.3.documents.0.id', $note->id));
});

it('shows a Folder\'s own Document categories, not its parent\'s', function () {
    $group = categoryLibrary();
    $tours = DocumentFolder::factory()->create(['group_id' => $group->id]);
    DocumentCategory::factory()->create(['group_id' => $group->id, 'name' => 'Data Sheets']);
    $europe = DocumentCategory::factory()->in($tours)->create(['name' => 'Europe']);
    $tour = DocumentFolder::factory()->in($tours)->create(['category_id' => $europe->id]);

    $this->actingAs(categoryReader($group))
        ->get(route('groups.documents.folder', ['group' => $group, 'folder' => $tours]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('library.categories', [['id' => $europe->id, 'name' => 'Europe']])
            ->has('library.sections', 1)
            ->where('library.sections.0.category.id', $europe->id)
            ->where('library.sections.0.folders.0.id', $tour->id));
});

it('shows a Folder with no Document categories as one Other section, and an empty one with none', function () {
    $group = categoryLibrary();
    $folder = DocumentFolder::factory()->create(['group_id' => $group->id]);
    $document = Document::factory()->create(['group_id' => $group->id, 'folder_id' => $folder->id]);
    $empty = DocumentFolder::factory()->create(['group_id' => $group->id]);
    $member = categoryReader($group);

    $this->actingAs($member)
        ->get(route('groups.documents.folder', ['group' => $group, 'folder' => $folder]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('library.categories', [])
            ->has('library.sections', 1)
            ->where('library.sections.0.category', null)
            ->where('library.sections.0.folderCount', 0)
            ->where('library.sections.0.documentCount', 1)
            ->where('library.sections.0.documents.0.id', $document->id));

    $this->actingAs($member)
        ->get(route('groups.documents.folder', ['group' => $group, 'folder' => $empty]))
        ->assertInertia(fn (Assert $page) => $page->has('library.sections', 0));
});

it('never lets a Document category change who reads a Folder or Document', function () {
    $group = categoryLibrary();
    $category = DocumentCategory::factory()->create(['group_id' => $group->id]);
    $internal = DocumentFolder::factory()->create(['group_id' => $group->id, 'category_id' => $category->id]);
    $shared = DocumentFolder::factory()->sharedWithMembers()->create(['group_id' => $group->id, 'category_id' => $category->id]);
    $rootDocument = Document::factory()->create(['group_id' => $group->id, 'category_id' => $category->id]);
    $outsider = Member::factory()->create();

    $this->actingAs($outsider)
        ->get(route('groups.show', ['group' => $group, 'section' => 'documents']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('library.sections.0.folders', 1)
            ->where('library.sections.0.folders.0.id', $shared->id)
            ->has('library.sections.0.documents', 0));

    $this->actingAs($outsider)->get(route('groups.documents.folder', ['group' => $group, 'folder' => $internal]))->assertForbidden();
    $this->actingAs($outsider)->get(route('documents.download', $rootDocument))->assertForbidden();
});

// --- Filter (#725) --------------------------------------------------------------

it('filters the root to one Document category with ?category=', function () {
    $group = categoryLibrary();
    $sheets = DocumentCategory::factory()->create(['group_id' => $group->id, 'name' => 'Data Sheets']);
    $publications = DocumentCategory::factory()->create(['group_id' => $group->id, 'name' => 'Publications']);
    $sheet = Document::factory()->create(['group_id' => $group->id, 'category_id' => $sheets->id]);
    Document::factory()->create(['group_id' => $group->id, 'category_id' => $publications->id]);
    Document::factory()->create(['group_id' => $group->id]);
    $member = categoryReader($group);

    $this->actingAs($member)
        ->get(route('groups.show', ['group' => $group, 'section' => 'documents', 'category' => $sheets->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('library.category', $sheets->id)
            ->has('library.categories', 2)
            ->has('library.sections', 1)
            ->where('library.sections.0.category.id', $sheets->id)
            ->where('library.sections.0.documents.0.id', $sheet->id));

    $this->actingAs($member)
        ->get(route('groups.show', ['group' => $group, 'section' => 'documents']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('library.category', null)
            ->has('library.sections', 3));
});

it('filters a Folder page, and its sub folders open unfiltered', function () {
    $group = categoryLibrary();
    $tours = DocumentFolder::factory()->create(['group_id' => $group->id]);
    $europe = DocumentCategory::factory()->in($tours)->create(['name' => 'Europe']);
    $aaap = DocumentCategory::factory()->in($tours)->create(['name' => 'AAAP']);
    DocumentFolder::factory()->in($tours)->create(['category_id' => $aaap->id]);
    $tour = DocumentFolder::factory()->in($tours)->create(['category_id' => $europe->id]);

    $this->actingAs(categoryReader($group))
        ->get(route('groups.documents.folder', ['group' => $group, 'folder' => $tours, 'category' => $europe->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('library.category', $europe->id)
            ->has('library.categories', 2)
            ->has('library.sections', 1)
            ->where('library.sections.0.folders.0.id', $tour->id)
            ->where('library.sections.0.folders.0.href', route('groups.documents.folder', ['group' => $group, 'folder' => $tour], absolute: false)));
});

it('gives an empty result, not an error, for another Folder\'s Document category', function () {
    $group = categoryLibrary();
    $tours = DocumentFolder::factory()->create(['group_id' => $group->id]);
    $europe = DocumentCategory::factory()->in($tours)->create();
    $sheets = DocumentCategory::factory()->create(['group_id' => $group->id]);
    Document::factory()->create(['group_id' => $group->id, 'category_id' => $sheets->id]);
    Document::factory()->create(['group_id' => $group->id]);
    $member = categoryReader($group);

    foreach ([$europe->id, 999999] as $id) {
        $this->actingAs($member)
            ->get(route('groups.show', ['group' => $group, 'section' => 'documents', 'category' => $id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('library.category', $id)
                ->has('library.categories', 1)
                ->has('library.sections', 0));
    }
});

it('leaves out a Document category with nothing readable for a viewer who cannot manage', function () {
    $group = categoryLibrary();
    $empty = DocumentCategory::factory()->create(['group_id' => $group->id, 'name' => 'Archive']);
    $internal = DocumentCategory::factory()->create(['group_id' => $group->id, 'name' => 'Internal']);
    $public = DocumentCategory::factory()->create(['group_id' => $group->id, 'name' => 'Public']);
    DocumentFolder::factory()->create(['group_id' => $group->id, 'category_id' => $internal->id]);
    Document::factory()->create(['group_id' => $group->id, 'category_id' => $internal->id]);
    $shared = DocumentFolder::factory()->sharedWithMembers()->create(['group_id' => $group->id, 'category_id' => $public->id]);
    $outsider = Member::factory()->create();

    // A member of the Group reads the Internal items; the empty Document category still goes.
    $this->actingAs(categoryReader($group))
        ->get(route('groups.show', ['group' => $group, 'section' => 'documents']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('library.categories', [['id' => $internal->id, 'name' => 'Internal'], ['id' => $public->id, 'name' => 'Public']])
            ->has('library.sections', 2));

    // An outsider sees only the section holding the shared Folder, in the sections and the filter.
    $this->actingAs($outsider)
        ->get(route('groups.show', ['group' => $group, 'section' => 'documents']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('library.categories', [['id' => $public->id, 'name' => 'Public']])
            ->has('library.sections', 1)
            ->where('library.sections.0.category.id', $public->id)
            ->where('library.sections.0.folders.0.id', $shared->id));

    // A filter link to a section the outsider may not read shows nothing.
    $this->actingAs($outsider)
        ->get(route('groups.show', ['group' => $group, 'section' => 'documents', 'category' => $internal->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('library.sections', 0));

    // A Librarian keeps every Document category, the empty one included, to manage them.
    $this->actingAs(categoryReader($group, Role::Librarian))
        ->get(route('groups.show', ['group' => $group, 'section' => 'documents', 'category' => $empty->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('library.categories', 3)
            ->has('library.sections', 1)
            ->where('library.sections.0.category.id', $empty->id)
            ->where('library.sections.0.folderCount', 0)
            ->where('library.sections.0.documentCount', 0));
});

// --- Assign ---------------------------------------------------------------------

it('files a Folder under one of its parent\'s Document categories on edit, and back under Other', function () {
    $group = categoryLibrary();
    $parent = DocumentFolder::factory()->create(['group_id' => $group->id]);
    $folder = DocumentFolder::factory()->in($parent)->create(['name' => 'Tour 1']);
    $europe = DocumentCategory::factory()->in($parent)->create(['name' => 'Europe']);
    $librarian = categoryReader($group, Role::Librarian);

    $this->actingAs($librarian)
        ->patch(route('document-folders.update', $folder), ['name' => 'Tour 1', 'category_id' => $europe->id])
        ->assertSessionHasNoErrors();
    expect($folder->fresh()->category_id)->toBe($europe->id);

    // Left out, it stays; null clears it.
    $this->actingAs($librarian)->patch(route('document-folders.update', $folder), ['name' => 'Tour One']);
    expect($folder->fresh()->category_id)->toBe($europe->id);

    $this->actingAs($librarian)->patch(route('document-folders.update', $folder), ['name' => 'Tour One', 'category_id' => null]);
    expect($folder->fresh()->category_id)->toBeNull();
});

it('files a top-level Folder under one of the root\'s Document categories', function () {
    $group = categoryLibrary();
    $folder = DocumentFolder::factory()->create(['group_id' => $group->id, 'name' => 'Asia']);
    $sheets = DocumentCategory::factory()->create(['group_id' => $group->id]);

    $this->actingAs(categoryReader($group, Role::Librarian))
        ->patch(route('document-folders.update', $folder), ['name' => 'Asia', 'category_id' => $sheets->id])
        ->assertSessionHasNoErrors();

    expect($folder->fresh()->category_id)->toBe($sheets->id);
});

it('files a Document under one of its Folder\'s Document categories on edit', function () {
    $group = categoryLibrary();
    $folder = DocumentFolder::factory()->create(['group_id' => $group->id]);
    $document = Document::factory()->create(['group_id' => $group->id, 'folder_id' => $folder->id]);
    $europe = DocumentCategory::factory()->in($folder)->create();

    $this->actingAs(categoryReader($group, Role::Librarian))
        ->patch(route('documents.update', $document), ['title' => 'Overview', 'category_id' => $europe->id])
        ->assertSessionHasNoErrors();

    expect($document->fresh()->category_id)->toBe($europe->id);
});

it('refuses another Folder\'s Document category on a Folder or Document edit', function () {
    $group = categoryLibrary();
    $folder = DocumentFolder::factory()->create(['group_id' => $group->id, 'name' => 'Asia']);
    $document = Document::factory()->create(['group_id' => $group->id]);
    $elsewhere = DocumentCategory::factory()->in($folder)->create();
    $foreign = DocumentCategory::factory()->create(['group_id' => categoryLibrary()->id]);
    $librarian = categoryReader($group, Role::Librarian);

    foreach ([$elsewhere, $foreign] as $category) {
        $this->actingAs($librarian)
            ->patch(route('document-folders.update', $folder), ['name' => 'Asia', 'category_id' => $category->id])
            ->assertSessionHasErrors(['category_id' => 'Choose a category of this folder.']);

        $this->actingAs($librarian)
            ->patch(route('documents.update', $document), ['title' => 'X', 'category_id' => $category->id])
            ->assertSessionHasErrors('category_id');
    }

    expect($folder->fresh()->category_id)->toBeNull()->and($document->fresh()->category_id)->toBeNull();
});

it('keeps a Document\'s Document category when its file is replaced', function () {
    Storage::fake('local');
    $group = categoryLibrary();
    $category = DocumentCategory::factory()->create(['group_id' => $group->id]);
    $document = Document::factory()->create(['group_id' => $group->id, 'category_id' => $category->id]);

    $this->actingAs(categoryReader($group, Role::Librarian))
        ->post(route('documents.replace', $document), ['file' => UploadedFile::fake()->create('new.pdf', 10, 'application/pdf')])
        ->assertSessionHasNoErrors();

    expect($document->fresh()->category_id)->toBe($category->id);
});
