<?php

use App\Enums\DocumentKind;
use App\Enums\Role;
use App\Models\Document;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\Member;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * A Librarian keeps Documents current (#713, spec #290, ADR-0030 §8): edit a Document's
 * title and description, upload a new file over it, and delete it.
 *
 * Asserted from outside: the HTTP response, the Inertia props, the rows in the database,
 * and the files on the faked private disk.
 */

beforeEach(function () {
    Storage::fake('local');
});

function keptLibrary(): Group
{
    return Group::factory()->create(['has_documents' => true]);
}

function keeperOf(Group $group, ?Role $role = Role::Librarian): Member
{
    $member = Member::factory()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);

    if ($role !== null) {
        GroupMemberRole::factory()->role($role)->create(['group_member_id' => $membership->id]);
    }

    return $member;
}

function keptDocument(Group $group, array $attributes = []): Document
{
    $document = Document::factory()->create(['group_id' => $group->id, ...$attributes]);
    Storage::disk('local')->put($document->storage_path, 'old-bytes');

    return $document;
}

// --- Edit -------------------------------------------------------------------------

it('saves a title and description as written', function () {
    $group = keptLibrary();
    $document = keptDocument($group);

    $this->actingAs(keeperOf($group))
        ->patch(route('documents.update', $document), [
            'title' => 'Minutes <March> & notes',
            'description' => "Line one\nLine two",
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($document->fresh())
        ->title->toBe('Minutes <March> & notes')
        ->description->toBe("Line one\nLine two");
});

it('clears a blank title back to the filename', function () {
    $group = keptLibrary();
    $document = keptDocument($group, ['title' => 'Old title', 'description' => 'Old', 'original_filename' => 'minutes.pdf']);

    $this->actingAs(keeperOf($group))
        ->patch(route('documents.update', $document), ['title' => '', 'description' => ''])
        ->assertSessionHasNoErrors();

    expect($document->fresh())
        ->title->toBeNull()
        ->description->toBeNull()
        ->displayName()->toBe('minutes.pdf');
});

it('rejects a title over 255 characters', function () {
    $group = keptLibrary();
    $document = keptDocument($group);

    $this->actingAs(keeperOf($group))
        ->patch(route('documents.update', $document), ['title' => str_repeat('a', 256)])
        ->assertSessionHasErrors('title');
});

it('sends each row\'s description to the tab', function () {
    $group = keptLibrary();
    keptDocument($group, ['description' => 'Read before the tour.']);

    $this->actingAs(keeperOf($group, null))
        ->get(route('groups.show', ['group' => $group, 'section' => 'documents']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('library.documents.0.description', 'Read before the tour.'));
});

// --- Replace ------------------------------------------------------------------------

it('swaps the file and keeps the Document', function () {
    $group = keptLibrary();
    $document = keptDocument($group, [
        'title' => 'Data sheet',
        'description' => 'Gallery 3',
        'original_filename' => 'old.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 999,
        'updated_at' => now()->subWeek(),
    ]);
    $oldPath = $document->storage_path;
    $librarian = keeperOf($group);

    $this->actingAs($librarian)
        ->post(route('documents.replace', $document), [
            'file' => UploadedFile::fake()->create('Data Sheet v2.docx', 40, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $replaced = Document::sole();

    expect($replaced->id)->toBe($document->id)
        ->and($replaced->title)->toBe('Data sheet')
        ->and($replaced->description)->toBe('Gallery 3')
        ->and($replaced->folder_id)->toBe($document->folder_id)
        ->and($replaced->original_filename)->toBe('Data Sheet v2.docx')
        ->and($replaced->mime_type)->toBe('application/vnd.openxmlformats-officedocument.wordprocessingml.document')
        ->and($replaced->size_bytes)->toBe(40 * 1024)
        ->and($replaced->uploaded_by_id)->toBe($librarian->id)
        ->and($replaced->updated_at->isAfter(now()->subMinute()))->toBeTrue()
        ->and($replaced->storage_path)->not->toBe($oldPath);

    Storage::disk('local')->assertMissing($oldPath);
    Storage::disk('local')->assertExists($replaced->storage_path);
    expect(Storage::disk('local')->allFiles())->toBe([$replaced->storage_path]);

    $this->actingAs(keeperOf($group, null))
        ->get(route('documents.download', $document))
        ->assertOk()
        ->assertDownload('Data Sheet v2.docx');
});

it('keeps the Document\'s Tags when its file is replaced', function () {
    $group = keptLibrary();
    $document = keptDocument($group);
    $tag = $group->documentTags()->create(['name' => 'Required']);
    $document->tags()->attach($tag);

    $this->actingAs(keeperOf($group))
        ->post(route('documents.replace', $document), ['file' => UploadedFile::fake()->create('new.pdf', 5, 'application/pdf')])
        ->assertSessionHasNoErrors();

    expect($document->fresh()->tags->pluck('id')->all())->toBe([$tag->id]);
});

it('keeps the old file when the new one is refused', function () {
    $group = keptLibrary();
    $document = keptDocument($group, ['original_filename' => 'old.pdf']);

    $this->actingAs(keeperOf($group))
        ->post(route('documents.replace', $document), ['file' => UploadedFile::fake()->create('setup.exe', 5, 'application/x-msdownload')])
        ->assertSessionHasErrors(['file' => 'setup.exe was not added. This type of file is not allowed.']);

    expect($document->fresh()->original_filename)->toBe('old.pdf');
    expect(Storage::disk('local')->allFiles())->toBe([$document->storage_path]);
});

it('requires a file to replace with', function () {
    $group = keptLibrary();
    $document = keptDocument($group);

    $this->actingAs(keeperOf($group))
        ->post(route('documents.replace', $document), [])
        ->assertSessionHasErrors('file');
});

it('refuses to replace a link Document, which has no file', function () {
    $group = keptLibrary();
    $link = Document::factory()->create([
        'group_id' => $group->id,
        'kind' => DocumentKind::Link,
        'url' => 'https://example.org/report',
        'original_filename' => null,
        'storage_path' => null,
        'mime_type' => null,
        'size_bytes' => null,
    ]);

    $this->actingAs(keeperOf($group))
        ->post(route('documents.replace', $link), ['file' => UploadedFile::fake()->create('new.pdf', 5, 'application/pdf')])
        ->assertForbidden();

    expect(Storage::disk('local')->allFiles())->toBe([]);
});

// --- Delete -------------------------------------------------------------------------

it('deletes the row, its access log and the stored file', function () {
    $group = keptLibrary();
    $document = keptDocument($group);
    $other = keptDocument($group);
    $document->downloads()->create(['member_id' => keeperOf($group, null)->id, 'downloaded_at' => now()]);

    $this->actingAs(keeperOf($group))
        ->delete(route('documents.destroy', $document))
        ->assertRedirect();

    $this->assertModelMissing($document);
    $this->assertDatabaseCount('document_downloads', 0);
    Storage::disk('local')->assertMissing($document->storage_path);
    Storage::disk('local')->assertExists($other->storage_path);
});

it('deletes a Document whose file is already gone', function () {
    $group = keptLibrary();
    $document = Document::factory()->create(['group_id' => $group->id]);

    $this->actingAs(keeperOf($group))
        ->delete(route('documents.destroy', $document))
        ->assertRedirect();

    $this->assertModelMissing($document);
});
