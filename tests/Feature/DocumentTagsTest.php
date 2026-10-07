<?php

use App\Enums\Role;
use App\Models\Document;
use App\Models\DocumentFolder;
use App\Models\DocumentTag;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\Member;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Tags in the Document library (#717, spec #290, ADR-0030 §4): a Librarian defines the
 * Group's Tags and puts them on Documents; readers see each Document's Tags and filter the
 * library by one Tag across all Folders.
 *
 * Asserted from outside: the HTTP response, the Inertia props, and the rows in the database.
 */

function taggedLibrary(array $attributes = []): Group
{
    return Group::factory()->create(['has_documents' => true, ...$attributes]);
}

function taggedLibraryMemberOf(Group $group, ?Role $role = null): Member
{
    $member = Member::factory()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);

    if ($role !== null) {
        GroupMemberRole::factory()->role($role)->create(['group_member_id' => $membership->id]);
    }

    return $member;
}

function tagOf(Group $group, string $name): DocumentTag
{
    return DocumentTag::factory()->create(['group_id' => $group->id, 'name' => $name]);
}

function documentsTab(Group $group, array $query = []): string
{
    return route('groups.show', ['group' => $group, 'section' => 'documents', ...$query]);
}

// --- Managing Tags ------------------------------------------------------------------

it('lets a Librarian create a Tag', function () {
    $group = taggedLibrary();

    $this->actingAs(taggedLibraryMemberOf($group, Role::Librarian))
        ->post(route('document-tags.store', $group), ['name' => 'Highlights'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(DocumentTag::sole())
        ->group_id->toBe($group->id)
        ->name->toBe('Highlights');
});

it('requires a Tag name unique within the Group', function () {
    $group = taggedLibrary();
    tagOf($group, 'Highlights');
    tagOf(taggedLibrary(), 'Required');
    $librarian = taggedLibraryMemberOf($group, Role::Librarian);

    $this->actingAs($librarian)
        ->post(route('document-tags.store', $group), ['name' => 'Highlights'])
        ->assertSessionHasErrors('name');

    $this->actingAs($librarian)
        ->post(route('document-tags.store', $group), ['name' => ''])
        ->assertSessionHasErrors('name');

    // Another Group's Tag name is free here.
    $this->actingAs($librarian)
        ->post(route('document-tags.store', $group), ['name' => 'Required'])
        ->assertSessionHasNoErrors();

    expect($group->documentTags()->count())->toBe(2);
});

it('lets a Librarian rename a Tag, keeping its Documents', function () {
    $group = taggedLibrary();
    $tag = tagOf($group, 'Highlites');
    $document = Document::factory()->create(['group_id' => $group->id]);
    $document->tags()->attach($tag);
    tagOf($group, 'Required');
    $librarian = taggedLibraryMemberOf($group, Role::Librarian);

    $this->actingAs($librarian)
        ->patch(route('document-tags.update', $tag), ['name' => 'Required'])
        ->assertSessionHasErrors('name');

    $this->actingAs($librarian)
        ->patch(route('document-tags.update', $tag), ['name' => 'Highlights'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($tag->fresh()->name)->toBe('Highlights')
        ->and($document->tags()->pluck('document_tags.id')->all())->toBe([$tag->id]);
});

it('removes a deleted Tag from every Document', function () {
    $group = taggedLibrary();
    $tag = tagOf($group, 'Highlights');
    $kept = tagOf($group, 'Required');
    $first = Document::factory()->create(['group_id' => $group->id]);
    $second = Document::factory()->create(['group_id' => $group->id, 'folder_id' => DocumentFolder::factory()->create(['group_id' => $group->id])->id]);
    $first->tags()->attach([$tag->id, $kept->id]);
    $second->tags()->attach($tag);

    $this->actingAs(taggedLibraryMemberOf($group, Role::Librarian))
        ->delete(route('document-tags.destroy', $tag))
        ->assertRedirect();

    expect(DocumentTag::find($tag->id))->toBeNull()
        ->and($first->tags()->pluck('document_tags.id')->all())->toBe([$kept->id])
        ->and($second->tags()->count())->toBe(0)
        ->and(Document::count())->toBe(2);
});

// --- Tagging a Document -------------------------------------------------------------

it('lets a Librarian add and remove Tags on a Document', function () {
    $group = taggedLibrary();
    $highlights = tagOf($group, 'Highlights');
    $required = tagOf($group, 'Required');
    $script = tagOf($group, 'Script');
    $document = Document::factory()->create(['group_id' => $group->id]);
    $document->tags()->attach($script);
    $librarian = taggedLibraryMemberOf($group, Role::Librarian);

    $this->actingAs($librarian)
        ->put(route('documents.tags.update', $document), ['tags' => [$highlights->id, $required->id]])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($document->tags()->pluck('document_tags.id')->sort()->values()->all())->toBe([$highlights->id, $required->id]);

    $this->actingAs($librarian)
        ->put(route('documents.tags.update', $document), ['tags' => []])
        ->assertSessionHasNoErrors();

    expect($document->tags()->count())->toBe(0);
});

it('refuses another Group\'s Tag on a Document', function () {
    $group = taggedLibrary();
    $foreign = tagOf(taggedLibrary(), 'Highlights');
    $document = Document::factory()->create(['group_id' => $group->id]);

    $this->actingAs(taggedLibraryMemberOf($group, Role::Librarian))
        ->put(route('documents.tags.update', $document), ['tags' => [$foreign->id]])
        ->assertSessionHasErrors('tags.0');

    expect($document->tags()->count())->toBe(0);
});

// --- Reading Tags -------------------------------------------------------------------

it('shows each Document\'s Tags and the Group\'s Tags, sorted by name', function () {
    $group = taggedLibrary();
    $script = tagOf($group, 'script');
    $highlights = tagOf($group, 'Highlights');
    tagOf(taggedLibrary(), 'Elsewhere');
    $document = Document::factory()->create(['group_id' => $group->id]);
    $document->tags()->attach([$script->id, $highlights->id]);

    $this->actingAs(taggedLibraryMemberOf($group))
        ->get(documentsTab($group))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('library.tag', null)
            ->where('library.tags', [
                ['id' => $highlights->id, 'name' => 'Highlights'],
                ['id' => $script->id, 'name' => 'script'],
            ])
            ->where('library.documents.0.tags', [
                ['id' => $highlights->id, 'name' => 'Highlights'],
                ['id' => $script->id, 'name' => 'script'],
            ]));
});

it('filters by one Tag across every Folder', function () {
    $group = taggedLibrary();
    $highlights = tagOf($group, 'Highlights');
    $required = tagOf($group, 'Required');
    $atRoot = Document::factory()->create(['group_id' => $group->id, 'title' => 'B root sheet']);
    $inFolder = Document::factory()->create(['group_id' => $group->id, 'folder_id' => DocumentFolder::factory()->create(['group_id' => $group->id])->id, 'title' => 'A folder sheet']);
    $otherTag = Document::factory()->create(['group_id' => $group->id, 'title' => 'C other']);
    Document::factory()->create(['group_id' => $group->id, 'title' => 'D untagged']);
    $atRoot->tags()->attach($highlights);
    $inFolder->tags()->attach([$highlights->id, $required->id]);
    $otherTag->tags()->attach($required);

    $this->actingAs(taggedLibraryMemberOf($group))
        ->get(documentsTab($group, ['tag' => $highlights->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('library.tag', ['id' => $highlights->id, 'name' => 'Highlights'])
            ->has('library.documents', 2)
            ->where('library.documents.0.id', $inFolder->id)
            ->where('library.documents.1.id', $atRoot->id));
});

it('lists only the Documents the viewer may read under a Tag filter', function () {
    $group = taggedLibrary();
    $highlights = tagOf($group, 'Highlights');
    Document::factory()->create(['group_id' => $group->id, 'folder_id' => DocumentFolder::factory()->create(['group_id' => $group->id])->id])->tags()->attach($highlights);

    $this->actingAs(Member::factory()->create())
        ->get(documentsTab($group, ['tag' => $highlights->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('library.tag.id', $highlights->id)
            ->has('library.documents', 0));
});

it('returns 404 for a Tag of another Group', function () {
    $group = taggedLibrary();
    $foreign = tagOf(taggedLibrary(), 'Highlights');

    $this->actingAs(taggedLibraryMemberOf($group))
        ->get(documentsTab($group, ['tag' => $foreign->id]))
        ->assertNotFound();
});

it('filters by Tag under /fr/', function () {
    $group = taggedLibrary();
    $tag = tagOf($group, 'Incontournables');
    Document::factory()->create(['group_id' => $group->id, 'folder_id' => DocumentFolder::factory()->create(['group_id' => $group->id])->id])->tags()->attach($tag);
    $member = taggedLibraryMemberOf($group);

    $this->withLocaleRoutes('fr', function () use ($group, $tag, $member) {
        $this->actingAs($member)
            ->get("/fr/groupes/{$group->slug}/documents?tag={$tag->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('library.tag.name', 'Incontournables')
                ->has('library.documents', 1));
    });
});
