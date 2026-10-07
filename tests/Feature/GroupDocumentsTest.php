<?php

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
 * The Document library's tracer bullet (#712, spec #290, ADR-0030): the Documents tab
 * lists the root Documents, a Librarian uploads files, and a member downloads one under
 * its original name through the gated, logged download route.
 *
 * Asserted from outside: the HTTP response, the Inertia props, the rows in the database,
 * and the files on the faked private disk.
 */

beforeEach(function () {
    Storage::fake('local');
});

function libraryGroup(array $attributes = []): Group
{
    return Group::factory()->create(['has_documents' => true, ...$attributes]);
}

function libraryMemberOf(Group $group, ?Role $role = null): Member
{
    $member = Member::factory()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);

    if ($role !== null) {
        GroupMemberRole::factory()->role($role)->create(['group_member_id' => $membership->id]);
    }

    return $member;
}

/** A file Document of the Group with its bytes on the faked private disk. */
function storedDocument(Group $group, array $attributes = []): Document
{
    $document = Document::factory()->create(['group_id' => $group->id, ...$attributes]);
    Storage::disk('local')->put($document->storage_path, 'file-bytes');

    return $document;
}

// --- Upload -------------------------------------------------------------------

it('stores an uploaded file on the private disk under a UUID name at the library root', function () {
    $group = libraryGroup();
    $librarian = libraryMemberOf($group, Role::Librarian);

    $this->actingAs($librarian)
        ->post(route('documents.store', $group), ['file' => UploadedFile::fake()->create('March 2026 Minutes.pdf', 120, 'application/pdf')])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $document = Document::sole();
    Storage::disk('local')->assertExists($document->storage_path);

    expect($document->group_id)->toBe($group->id)
        ->and($document->folder_id)->toBeNull()
        ->and($document->kind->value)->toBe('file')
        ->and($document->title)->toBeNull()
        ->and($document->original_filename)->toBe('March 2026 Minutes.pdf')
        ->and($document->mime_type)->toBe('application/pdf')
        ->and($document->storage_path)->toMatch('#^documents/[0-9a-f-]{36}$#')
        ->and($document->size_bytes)->toBe(120 * 1024)
        ->and($document->uploaded_by_id)->toBe($librarian->id)
        ->and($document->uploaded_at)->not->toBeNull();
});

it('keeps a safe version of the original filename', function () {
    $group = libraryGroup();

    $this->actingAs(libraryMemberOf($group, Role::Librarian))
        ->post(route('documents.store', $group), ['file' => UploadedFile::fake()->create('../Rapport: été*2 <v2>?.pdf', 10, 'application/pdf')])
        ->assertSessionHasNoErrors();

    expect(Document::sole()->original_filename)->toBe('Rapport été2 v2.pdf');
});

it('accepts every allowed type', function (string $name, string $mime) {
    $group = libraryGroup();

    $this->actingAs(libraryMemberOf($group, Role::Librarian))
        ->post(route('documents.store', $group), ['file' => UploadedFile::fake()->create($name, 5, $mime)])
        ->assertSessionHasNoErrors();

    expect(Document::sole()->mime_type)->toBe($mime);
})->with([
    ['notes.pdf', 'application/pdf'],
    ['notes.doc', 'application/msword'],
    ['notes.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    ['sheet.xls', 'application/vnd.ms-excel'],
    ['sheet.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    ['deck.ppt', 'application/vnd.ms-powerpoint'],
    ['deck.pptx', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'],
    ['photo.jpg', 'image/jpeg'],
    ['photo.png', 'image/png'],
    ['photo.webp', 'image/webp'],
    ['photo.gif', 'image/gif'],
    ['readme.txt', 'text/plain'],
    ['roster.csv', 'text/csv'],
    ['tour.mp4', 'video/mp4'],
    ['talk.mp3', 'audio/mpeg'],
    ['bundle.zip', 'application/zip'],
]);

it('rejects a file whose extension is not allowed', function () {
    $group = libraryGroup();

    $this->actingAs(libraryMemberOf($group, Role::Librarian))
        ->post(route('documents.store', $group), ['file' => UploadedFile::fake()->create('setup.exe', 5, 'application/x-msdownload')])
        ->assertSessionHasErrors(['file' => 'setup.exe was not added. This type of file is not allowed.']);

    expect(Document::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('rejects a file whose contents do not match its extension', function () {
    $group = libraryGroup();

    $this->actingAs(libraryMemberOf($group, Role::Librarian))
        ->post(route('documents.store', $group), ['file' => UploadedFile::fake()->create('report.pdf', 5, 'application/x-msdownload')])
        ->assertSessionHasErrors(['file' => 'report.pdf was not added. This type of file is not allowed.']);

    expect(Document::count())->toBe(0);
});

it('rejects a file over 1.5 GB', function () {
    $group = libraryGroup();

    $this->actingAs(libraryMemberOf($group, Role::Librarian))
        ->post(route('documents.store', $group), ['file' => UploadedFile::fake()->create('tour.mp4', 1536 * 1024 + 1, 'video/mp4')])
        ->assertSessionHasErrors(['file' => 'tour.mp4 was not added. It is larger than 1.5 GB.']);

    expect(Document::count())->toBe(0);
});

it('rejects a file the server refused as too large before validation', function () {
    $group = libraryGroup();
    $refused = new UploadedFile(UploadedFile::fake()->create('huge.mp4', 1)->getPathname(), 'huge.mp4', 'video/mp4', UPLOAD_ERR_INI_SIZE, true);

    $this->actingAs(libraryMemberOf($group, Role::Librarian))
        ->post(route('documents.store', $group), ['file' => $refused])
        ->assertSessionHasErrors(['file' => 'huge.mp4 was not added. It is larger than 1.5 GB.']);
});

it('requires a file', function () {
    $group = libraryGroup();

    $this->actingAs(libraryMemberOf($group, Role::Librarian))
        ->post(route('documents.store', $group), [])
        ->assertSessionHasErrors('file');
});

it('rejects the file in French for a French page', function () {
    $group = libraryGroup();
    $librarian = libraryMemberOf($group, Role::Librarian);

    $this->withLocaleRoutes('fr', function () use ($group, $librarian) {
        $this->actingAs($librarian)
            ->from("/fr/groupes/{$group->slug}/documents")
            ->post(route('documents.store', $group), ['file' => UploadedFile::fake()->create('setup.exe', 5, 'application/x-msdownload')])
            ->assertSessionHasErrors(['file' => 'setup.exe n’a pas été ajouté. Ce type de fichier n’est pas permis.']);
    });
});

// --- Documents tab --------------------------------------------------------------

it('lists the root Documents for a member, sorted by name', function () {
    $group = libraryGroup();
    $minutes = storedDocument($group, ['title' => null, 'original_filename' => 'minutes.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 2048]);
    storedDocument($group, ['title' => 'Annual report', 'original_filename' => 'ar-2026.docx']);
    storedDocument(libraryGroup());

    $this->actingAs(libraryMemberOf($group))
        ->get(route('groups.show', ['group' => $group, 'section' => 'documents']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('groups/Show')
            ->where('section', 'documents')
            ->where('can.manageDocuments', false)
            ->has('library.documents', 2)
            ->where('library.documents.0.title', 'Annual report')
            ->where('library.documents.1.id', $minutes->id)
            ->where('library.documents.1.title', null)
            ->where('library.documents.1.filename', 'minutes.pdf')
            ->where('library.documents.1.extension', 'pdf')
            ->where('library.documents.1.sizeBytes', 2048)
            ->where('library.documents.1.updatedAt', $minutes->updated_at->toIso8601String())
            ->where('library.documents.1.uploader', null)
            ->where('library.documents.1.href', "/documents/{$minutes->id}/download"));
});

it('shows the uploader and the upload control to a Librarian', function () {
    $group = libraryGroup();
    $uploader = Member::factory()->create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);
    storedDocument($group, ['uploaded_by_id' => $uploader->id]);

    $this->actingAs(libraryMemberOf($group, Role::Librarian))
        ->get(route('groups.show', ['group' => $group, 'section' => 'documents']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.manageDocuments', true)
            ->where('library.documents.0.uploader', $uploader->fullName()));
});

it('withholds root Documents from a non-member', function () {
    $group = libraryGroup();
    storedDocument($group);

    $this->actingAs(Member::factory()->create())
        ->get(route('groups.show', ['group' => $group, 'section' => 'documents']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.manageDocuments', false)
            ->has('library.documents', 0));
});

it('returns 404 for the Documents tab of a Group without the documents capability', function () {
    $group = Group::factory()->create(['has_documents' => false]);

    $this->actingAs(Member::factory()->superTier()->create())
        ->get(route('groups.show', ['group' => $group, 'section' => 'documents']))
        ->assertNotFound();
});

it('serves the Documents tab and download links under /fr/', function () {
    $group = libraryGroup();
    $document = storedDocument($group);
    $member = libraryMemberOf($group);

    $this->withLocaleRoutes('fr', function () use ($group, $document, $member) {
        $this->actingAs($member)
            ->get("/fr/groupes/{$group->slug}/documents")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('section', 'documents')
                ->where('library.documents.0.href', "/fr/documents/{$document->id}/telecharger"));

        $this->get("/fr/documents/{$document->id}/telecharger")
            ->assertOk()
            ->assertDownload($document->original_filename);
    });
});

// --- Download -------------------------------------------------------------------

it('lets a member download a Document under its original name and logs the access', function () {
    $group = libraryGroup();
    $document = storedDocument($group, ['original_filename' => 'March 2026 Minutes.pdf', 'mime_type' => 'application/pdf']);
    $member = libraryMemberOf($group);

    $response = $this->actingAs($member)
        ->get("/documents/{$document->id}/download")
        ->assertOk()
        ->assertDownload('March 2026 Minutes.pdf')
        ->assertHeader('Content-Type', 'application/pdf');

    expect($response->streamedContent())->toBe('file-bytes');

    $this->assertDatabaseHas('document_downloads', [
        'document_id' => $document->id,
        'member_id' => $member->id,
    ]);
    expect($document->downloads()->sole()->downloaded_at)->not->toBeNull();
});

it('refuses a non-member and logs nothing', function () {
    $document = storedDocument(libraryGroup());

    $this->actingAs(Member::factory()->create())
        ->get("/documents/{$document->id}/download")
        ->assertForbidden();

    $this->assertDatabaseCount('document_downloads', 0);
});

it('returns 404 for a Document whose file is gone', function () {
    $group = libraryGroup();
    $document = Document::factory()->create(['group_id' => $group->id]);

    $this->actingAs(libraryMemberOf($group))
        ->get("/documents/{$document->id}/download")
        ->assertNotFound();
});

it('sends a logged-out visitor to login, then hands over the file', function () {
    $group = libraryGroup();
    $document = storedDocument($group, ['original_filename' => 'minutes.pdf']);
    $member = libraryMemberOf($group);

    $this->get("/documents/{$document->id}/download")->assertRedirect(route('login'));

    $this->post('/login', ['email' => $member->email, 'password' => 'password'])
        ->assertRedirect(url("/documents/{$document->id}/download"));

    $this->get("/documents/{$document->id}/download")
        ->assertOk()
        ->assertDownload('minutes.pdf');
});
