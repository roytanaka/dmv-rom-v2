<?php

use App\Enums\Role;
use App\Models\Document;
use App\Models\DocumentFolder;
use App\Models\DocumentTag;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\Member;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Folders in the Document library (#714, spec #290, ADR-0030 §3): a Librarian creates,
 * renames, moves and deletes Folders, uploads into one and moves Documents between them;
 * readers open a Folder and see its breadcrumb, child Folders and Documents.
 *
 * Asserted from outside: the HTTP response, the Inertia props and the rows in the database.
 */

beforeEach(function () {
    Storage::fake('local');
});

function folderLibrary(array $attributes = []): Group
{
    return Group::factory()->create(['has_documents' => true, ...$attributes]);
}

function folderReader(Group $group, ?Role $role = null): Member
{
    $member = Member::factory()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);

    if ($role !== null) {
        GroupMemberRole::factory()->role($role)->create(['group_member_id' => $membership->id]);
    }

    return $member;
}

function topFolder(Group $group, string $name = 'Minutes'): DocumentFolder
{
    return DocumentFolder::factory()->create(['group_id' => $group->id, 'name' => $name]);
}

/** A chain of `$levels` nested Folders under `$from` (or at the top level); returns the deepest. */
function folderChain(Group $group, int $levels, ?DocumentFolder $from = null): DocumentFolder
{
    $folder = $from;

    for ($i = 1; $i <= $levels; $i++) {
        $folder = DocumentFolder::factory()->create(['group_id' => $group->id, 'parent_id' => $folder?->id, 'name' => "Level {$i}"]);
    }

    return $folder;
}

// --- Create ---------------------------------------------------------------------

it('creates a Folder at the top level and inside another Folder', function () {
    $group = folderLibrary();
    $librarian = folderReader($group, Role::Librarian);

    $this->actingAs($librarian)
        ->post(route('document-folders.store', $group), ['name' => 'Data Sheets'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $top = DocumentFolder::sole();
    expect($top->group_id)->toBe($group->id)->and($top->parent_id)->toBeNull()->and($top->name)->toBe('Data Sheets');

    $this->actingAs($librarian)
        ->post(route('document-folders.store', $group), ['name' => 'Asia', 'parent_id' => $top->id])
        ->assertSessionHasNoErrors();

    expect(DocumentFolder::where('name', 'Asia')->sole()->parent_id)->toBe($top->id);
});

it('refuses a sibling Folder with the same name, at the top level and below', function () {
    $group = folderLibrary();
    $librarian = folderReader($group, Role::Librarian);
    $top = topFolder($group, 'Minutes');
    DocumentFolder::factory()->in($top)->create(['name' => '2026']);

    $this->actingAs($librarian)
        ->post(route('document-folders.store', $group), ['name' => 'Minutes'])
        ->assertSessionHasErrors(['name' => 'A folder with this name is already here.']);

    $this->actingAs($librarian)
        ->post(route('document-folders.store', $group), ['name' => '2026', 'parent_id' => $top->id])
        ->assertSessionHasErrors('name');

    // The same name elsewhere is fine: under another parent, or in another Group.
    $this->actingAs($librarian)
        ->post(route('document-folders.store', $group), ['name' => 'Minutes', 'parent_id' => $top->id])
        ->assertSessionHasNoErrors();
    topFolder(folderLibrary(), '2026');

    expect(DocumentFolder::where('group_id', $group->id)->count())->toBe(3);
});

it('refuses a Folder deeper than five levels', function () {
    $group = folderLibrary();
    $librarian = folderReader($group, Role::Librarian);
    $fourth = folderChain($group, 4);

    $this->actingAs($librarian)
        ->post(route('document-folders.store', $group), ['name' => 'Fifth', 'parent_id' => $fourth->id])
        ->assertSessionHasNoErrors();

    $fifth = DocumentFolder::where('name', 'Fifth')->sole();

    $this->actingAs($librarian)
        ->post(route('document-folders.store', $group), ['name' => 'Sixth', 'parent_id' => $fifth->id])
        ->assertSessionHasErrors(['parent_id' => 'Folders can be at most 5 levels deep.']);

    expect(DocumentFolder::where('name', 'Sixth')->exists())->toBeFalse();
});

it('refuses a parent from another Group', function () {
    $group = folderLibrary();
    $foreign = topFolder(folderLibrary());

    $this->actingAs(folderReader($group, Role::Librarian))
        ->post(route('document-folders.store', $group), ['name' => 'Stray', 'parent_id' => $foreign->id])
        ->assertSessionHasErrors('parent_id');
});

it('refuses Folder writes to a non-manager and to a Group without the capability', function () {
    $group = folderLibrary();
    $folder = topFolder($group);
    $member = folderReader($group);

    $this->actingAs($member)->post(route('document-folders.store', $group), ['name' => 'X'])->assertForbidden();
    $this->actingAs($member)->patch(route('document-folders.update', $folder), ['name' => 'X'])->assertForbidden();
    $this->actingAs($member)->patch(route('document-folders.move', $folder), ['parent_id' => null])->assertForbidden();
    $this->actingAs($member)->delete(route('document-folders.destroy', $folder))->assertForbidden();

    $group->update(['has_documents' => false]);
    $superTier = Member::factory()->superTier()->create();

    $this->actingAs($superTier)->post(route('document-folders.store', $group), ['name' => 'X'])->assertForbidden();
    $this->actingAs($superTier)->delete(route('document-folders.destroy', $folder))->assertForbidden();

    expect(DocumentFolder::count())->toBe(1);
});

// --- Rename ---------------------------------------------------------------------

it('renames a Folder, keeping names unique among siblings', function () {
    $group = folderLibrary();
    $librarian = folderReader($group, Role::Librarian);
    $folder = topFolder($group, 'Minutes');
    topFolder($group, 'Reports');

    $this->actingAs($librarian)
        ->patch(route('document-folders.update', $folder), ['name' => 'Reports'])
        ->assertSessionHasErrors('name');

    $this->actingAs($librarian)
        ->patch(route('document-folders.update', $folder), ['name' => 'Meeting minutes'])
        ->assertSessionHasNoErrors();

    expect($folder->fresh()->name)->toBe('Meeting minutes');
});

// --- Move -----------------------------------------------------------------------

it('moves a Folder under another Folder and back to the top level', function () {
    $group = folderLibrary();
    $librarian = folderReader($group, Role::Librarian);
    $folder = topFolder($group, 'Asia');
    $target = topFolder($group, 'Galleries');

    $this->actingAs($librarian)
        ->patch(route('document-folders.move', $folder), ['parent_id' => $target->id])
        ->assertSessionHasNoErrors();

    expect($folder->fresh()->parent_id)->toBe($target->id);

    $this->actingAs($librarian)
        ->patch(route('document-folders.move', $folder), ['parent_id' => null])
        ->assertSessionHasNoErrors();

    expect($folder->fresh()->parent_id)->toBeNull();
});

it('refuses to move a Folder inside itself or its descendants', function () {
    $group = folderLibrary();
    $librarian = folderReader($group, Role::Librarian);
    $folder = topFolder($group);
    $grandchild = folderChain($group, 2, $folder);

    $this->actingAs($librarian)
        ->patch(route('document-folders.move', $folder), ['parent_id' => $folder->id])
        ->assertSessionHasErrors(['parent_id' => 'A folder cannot move inside itself.']);

    $this->actingAs($librarian)
        ->patch(route('document-folders.move', $folder), ['parent_id' => $grandchild->id])
        ->assertSessionHasErrors(['parent_id' => 'A folder cannot move inside itself.']);

    expect($folder->fresh()->parent_id)->toBeNull();
});

it('refuses a move that would put the moved subtree past five levels', function () {
    $group = folderLibrary();
    $librarian = folderReader($group, Role::Librarian);
    $third = folderChain($group, 3);
    // A Folder with two levels below it: three levels in all.
    $moved = topFolder($group, 'Moved');
    folderChain($group, 2, $moved);

    $this->actingAs($librarian)
        ->patch(route('document-folders.move', $moved), ['parent_id' => $third->id])
        ->assertSessionHasErrors(['parent_id' => 'Folders can be at most 5 levels deep.']);

    expect($moved->fresh()->parent_id)->toBeNull();

    // Under the second level, the subtree ends exactly at level 5.
    $this->actingAs($librarian)
        ->patch(route('document-folders.move', $moved), ['parent_id' => $third->parent_id])
        ->assertSessionHasNoErrors();

    expect($moved->fresh()->parent_id)->toBe($third->parent_id);
});

it('refuses a move into a parent that already has a Folder of that name', function () {
    $group = folderLibrary();
    $librarian = folderReader($group, Role::Librarian);
    $target = topFolder($group, 'Galleries');
    DocumentFolder::factory()->in($target)->create(['name' => 'Asia']);
    $folder = topFolder($group, 'Asia');

    $this->actingAs($librarian)
        ->patch(route('document-folders.move', $folder), ['parent_id' => $target->id])
        ->assertSessionHasErrors(['parent_id' => 'A folder with this name is already here.']);
});

it('refuses a move into another Group\'s Folder', function () {
    $group = folderLibrary();
    $folder = topFolder($group);

    $this->actingAs(folderReader($group, Role::Librarian))
        ->patch(route('document-folders.move', $folder), ['parent_id' => topFolder(folderLibrary())->id])
        ->assertSessionHasErrors('parent_id');
});

// --- Delete ---------------------------------------------------------------------

it('deletes an empty Folder', function () {
    $group = folderLibrary();
    $folder = topFolder($group);

    $this->actingAs(folderReader($group, Role::Librarian))
        ->delete(route('document-folders.destroy', $folder))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(DocumentFolder::count())->toBe(0);
});

it('refuses to delete a Folder that holds a Folder or a Document', function () {
    $group = folderLibrary();
    $librarian = folderReader($group, Role::Librarian);
    $withFolder = topFolder($group, 'Parent');
    DocumentFolder::factory()->in($withFolder)->create();
    $withDocument = topFolder($group, 'Holder');
    Document::factory()->create(['group_id' => $group->id, 'folder_id' => $withDocument->id]);

    foreach ([$withFolder, $withDocument] as $folder) {
        $this->actingAs($librarian)
            ->delete(route('document-folders.destroy', $folder))
            ->assertSessionHasErrors(['folder' => 'This folder still holds folders or documents. Move or delete them first.']);
    }

    expect(DocumentFolder::count())->toBe(3)->and(Document::count())->toBe(1);
});

// --- Upload and move Documents ----------------------------------------------------

it('uploads into the current Folder', function () {
    $group = folderLibrary();
    $folder = topFolder($group);

    $this->actingAs(folderReader($group, Role::Librarian))
        ->post(route('documents.store', $group), ['file' => UploadedFile::fake()->create('minutes.pdf', 10, 'application/pdf'), 'folder_id' => $folder->id])
        ->assertSessionHasNoErrors();

    expect(Document::sole()->folder_id)->toBe($folder->id);
});

it('refuses an upload into another Group\'s Folder', function () {
    $group = folderLibrary();

    $this->actingAs(folderReader($group, Role::Librarian))
        ->post(route('documents.store', $group), ['file' => UploadedFile::fake()->create('minutes.pdf', 10, 'application/pdf'), 'folder_id' => topFolder(folderLibrary())->id])
        ->assertSessionHasErrors('folder_id');

    expect(Document::count())->toBe(0);
});

it('moves a Document to a Folder and back to the root', function () {
    $group = folderLibrary();
    $librarian = folderReader($group, Role::Librarian);
    $folder = topFolder($group);
    $document = Document::factory()->create(['group_id' => $group->id]);

    $this->actingAs($librarian)
        ->patch(route('documents.move', $document), ['folder_id' => $folder->id])
        ->assertSessionHasNoErrors();

    expect($document->fresh()->folder_id)->toBe($folder->id);

    $this->actingAs($librarian)
        ->patch(route('documents.move', $document), ['folder_id' => null])
        ->assertSessionHasNoErrors();

    expect($document->fresh()->folder_id)->toBeNull();
});

it('refuses a Document move by a non-manager or into another Group\'s Folder', function () {
    $group = folderLibrary();
    $document = Document::factory()->create(['group_id' => $group->id]);

    $this->actingAs(folderReader($group))
        ->patch(route('documents.move', $document), ['folder_id' => topFolder($group)->id])
        ->assertForbidden();

    $this->actingAs(folderReader($group, Role::Librarian))
        ->patch(route('documents.move', $document), ['folder_id' => topFolder(folderLibrary())->id])
        ->assertSessionHasErrors('folder_id');

    expect($document->fresh()->folder_id)->toBeNull();
});

it('adds a link Document to the current Folder', function () {
    $group = folderLibrary();
    $folder = topFolder($group);

    $this->actingAs(folderReader($group, Role::Librarian))
        ->post(route('documents.links.store', $group), ['title' => 'Collections', 'url' => 'https://www.rom.on.ca', 'folder_id' => $folder->id])
        ->assertSessionHasNoErrors();

    expect(Document::sole()->folder_id)->toBe($folder->id);
});

// --- Browse ---------------------------------------------------------------------

it('lists the top-level Folders and root Documents at the library root, sorted by name', function () {
    $group = folderLibrary();
    $reports = topFolder($group, 'Reports');
    $minutes = topFolder($group, 'minutes');
    DocumentFolder::factory()->in($reports)->create(['name' => 'Annual']);
    Document::factory()->create(['group_id' => $group->id, 'folder_id' => $reports->id]);
    $root = Document::factory()->create(['group_id' => $group->id, 'title' => 'Handbook']);

    $this->actingAs(folderReader($group))
        ->get(route('groups.show', ['group' => $group, 'section' => 'documents']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('library.folder', null)
            ->where('library.breadcrumb', [])
            ->has('library.folders', 2)
            ->where('library.folders.0.id', $minutes->id)
            ->where('library.folders.0.name', 'minutes')
            ->where('library.folders.0.href', "/groups/{$group->slug}/documents/folders/{$minutes->id}")
            ->where('library.folders.1.id', $reports->id)
            ->has('library.documents', 1)
            ->where('library.documents.0.id', $root->id)
            ->where('library.destinations', []));
});

it('opens a Folder with its breadcrumb, child Folders and Documents', function () {
    $group = folderLibrary();
    $top = topFolder($group, 'Data Sheets');
    $section = DocumentFolder::factory()->in($top)->create(['name' => 'Asia']);
    $tour = DocumentFolder::factory()->in($section)->create(['name' => 'Japan']);
    $sheet = Document::factory()->create(['group_id' => $group->id, 'folder_id' => $section->id, 'title' => 'Overview']);
    Document::factory()->create(['group_id' => $group->id, 'folder_id' => $tour->id]);
    Document::factory()->create(['group_id' => $group->id]);

    $this->actingAs(folderReader($group))
        ->get(route('groups.documents.folder', ['group' => $group, 'folder' => $section]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('groups/Show')
            ->where('section', 'documents')
            ->where('library.folder.id', $section->id)
            ->where('library.folder.name', 'Asia')
            ->has('library.breadcrumb', 1)
            ->where('library.breadcrumb.0.id', $top->id)
            ->where('library.breadcrumb.0.name', 'Data Sheets')
            ->has('library.folders', 1)
            ->where('library.folders.0.id', $tour->id)
            ->has('library.documents', 1)
            ->where('library.documents.0.id', $sheet->id));
});

it('shows an empty Folder with nothing in it', function () {
    $group = folderLibrary();
    $folder = topFolder($group);

    $this->actingAs(folderReader($group))
        ->get(route('groups.documents.folder', ['group' => $group, 'folder' => $folder]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('library.folders', 0)
            ->has('library.documents', 0));
});

it('sends a manager every Folder as a move destination, in tree order', function () {
    $group = folderLibrary();
    $b = topFolder($group, 'B');
    $a = topFolder($group, 'A');
    $child = DocumentFolder::factory()->in($a)->create(['name' => 'Child']);

    $this->actingAs(folderReader($group, Role::Librarian))
        ->get(route('groups.show', ['group' => $group, 'section' => 'documents']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('library.destinations', 3)
            ->where('library.destinations.0', ['id' => $a->id, 'parentId' => null, 'depth' => 1, 'path' => ['A']])
            ->where('library.destinations.1', ['id' => $child->id, 'parentId' => $a->id, 'depth' => 2, 'path' => ['A', 'Child']])
            ->where('library.destinations.2.id', $b->id));
});

it('lists the Documents of a Tag across Folders, without the Folder rows', function () {
    $group = folderLibrary();
    $folder = topFolder($group);
    $tag = DocumentTag::factory()->create(['group_id' => $group->id, 'name' => 'Highlights']);
    $nested = Document::factory()->create(['group_id' => $group->id, 'folder_id' => DocumentFolder::factory()->in($folder)->create()->id]);
    $nested->tags()->attach($tag);
    $root = Document::factory()->create(['group_id' => $group->id]);
    $root->tags()->attach($tag);

    $this->actingAs(folderReader($group))
        ->get(route('groups.show', ['group' => $group, 'section' => 'documents', 'tag' => $tag->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('library.folders', 0)
            ->has('library.documents', 2)
            ->where('library.tag.id', $tag->id));
});

it('returns 404 for a Folder of another Group', function () {
    $group = folderLibrary();

    $this->actingAs(Member::factory()->superTier()->create())
        ->get(route('groups.documents.folder', ['group' => $group, 'folder' => topFolder(folderLibrary())]))
        ->assertNotFound();
});

it('refuses a non-member a Folder the Group keeps to its members', function () {
    $group = folderLibrary();
    $folder = topFolder($group);

    $this->actingAs(Member::factory()->create())
        ->get(route('groups.documents.folder', ['group' => $group, 'folder' => $folder]))
        ->assertForbidden();

    $this->actingAs(Member::factory()->create())
        ->get(route('groups.show', ['group' => $group, 'section' => 'documents']))
        ->assertInertia(fn (Assert $page) => $page->has('library.folders', 0));
});

it('serves a Folder under /fr/ with French segments', function () {
    $group = folderLibrary();
    $folder = topFolder($group);
    $member = folderReader($group);

    $this->withLocaleRoutes('fr', function () use ($group, $folder, $member) {
        $this->actingAs($member)
            ->get("/fr/groupes/{$group->slug}/documents/dossiers/{$folder->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('library.folder.href', "/fr/groupes/{$group->slug}/documents/dossiers/{$folder->id}"));
    });
});
