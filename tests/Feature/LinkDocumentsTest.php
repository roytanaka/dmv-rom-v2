<?php

use App\Enums\DocumentKind;
use App\Enums\Role;
use App\Models\Document;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\Member;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Link Documents (#716, spec #290, ADR-0030 §7): a Librarian adds a title and a web address;
 * opening it runs the download route's access check and log, then redirects to the address.
 *
 * Asserted from outside: the HTTP response, the Inertia props and the rows in the database.
 */

beforeEach(function () {
    Storage::fake('local');
});

function linkLibraryGroup(array $attributes = []): Group
{
    return Group::factory()->create(['has_documents' => true, ...$attributes]);
}

function linkLibraryMemberOf(Group $group, ?Role $role = null): Member
{
    $member = Member::factory()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);

    if ($role !== null) {
        GroupMemberRole::factory()->role($role)->create(['group_member_id' => $membership->id]);
    }

    return $member;
}

function linkDocumentOf(Group $group, array $attributes = []): Document
{
    return Document::factory()->link()->create(['group_id' => $group->id, ...$attributes]);
}

// --- Add ----------------------------------------------------------------------------

it('adds a link Document at the library root with no file columns', function () {
    $group = linkLibraryGroup();
    $librarian = linkLibraryMemberOf($group, Role::Librarian);

    $this->actingAs($librarian)
        ->post(route('documents.links.store', $group), ['title' => 'Volunteer handbook', 'url' => 'https://example.org/handbook'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $document = Document::sole();

    expect($document->group_id)->toBe($group->id)
        ->and($document->folder_id)->toBeNull()
        ->and($document->kind)->toBe(DocumentKind::Link)
        ->and($document->title)->toBe('Volunteer handbook')
        ->and($document->url)->toBe('https://example.org/handbook')
        ->and($document->original_filename)->toBeNull()
        ->and($document->storage_path)->toBeNull()
        ->and($document->mime_type)->toBeNull()
        ->and($document->size_bytes)->toBeNull()
        ->and($document->uploaded_by_id)->toBe($librarian->id)
        ->and($document->uploaded_at)->not->toBeNull();
});

it('accepts an http address', function () {
    $group = linkLibraryGroup();

    $this->actingAs(linkLibraryMemberOf($group, Role::Librarian))
        ->post(route('documents.links.store', $group), ['title' => 'Old site', 'url' => 'http://example.org/page'])
        ->assertSessionHasNoErrors();

    expect(Document::sole()->url)->toBe('http://example.org/page');
});

it('refuses an address that is not http or https', function (string $url) {
    $group = linkLibraryGroup();

    $this->actingAs(linkLibraryMemberOf($group, Role::Librarian))
        ->post(route('documents.links.store', $group), ['title' => 'Bad', 'url' => $url])
        ->assertSessionHasErrors('url');

    expect(Document::count())->toBe(0);
})->with(['javascript:alert(1)', 'ftp://example.org/file.pdf', 'file:///etc/passwd', 'not a url', '']);

it('requires a title', function () {
    $group = linkLibraryGroup();

    $this->actingAs(linkLibraryMemberOf($group, Role::Librarian))
        ->post(route('documents.links.store', $group), ['title' => '', 'url' => 'https://example.org'])
        ->assertSessionHasErrors('title');

    expect(Document::count())->toBe(0);
});

it('lets the Chair and the super-tier add a link', function (string $who) {
    $group = linkLibraryGroup();
    $actor = $who === 'Chair' ? linkLibraryMemberOf($group, Role::Chair) : Member::factory()->superTier()->create();

    $this->actingAs($actor)
        ->post(route('documents.links.store', $group), ['title' => 'Report', 'url' => 'https://example.org'])
        ->assertSessionHasNoErrors();

    expect(Document::sole()->kind)->toBe(DocumentKind::Link);
})->with(['Chair', 'super-tier']);

it('forbids a link from anyone who does not manage the library', function (string $who) {
    $group = linkLibraryGroup();
    $actor = match ($who) {
        'ordinary member' => linkLibraryMemberOf($group),
        'non-member' => Member::factory()->create(),
        'Librarian of another Group' => linkLibraryMemberOf(linkLibraryGroup(), Role::Librarian),
    };

    $this->actingAs($actor)
        ->post(route('documents.links.store', $group), ['title' => 'Report', 'url' => 'https://example.org'])
        ->assertForbidden();

    expect(Document::count())->toBe(0);
})->with(['ordinary member', 'non-member', 'Librarian of another Group']);

it('forbids a link in a Group without the documents capability, super-tier included', function () {
    $group = Group::factory()->create(['has_documents' => false]);

    $this->actingAs(Member::factory()->superTier()->create())
        ->post(route('documents.links.store', $group), ['title' => 'Report', 'url' => 'https://example.org'])
        ->assertForbidden();

    expect(Document::count())->toBe(0);
});

// --- Edit ---------------------------------------------------------------------------

it('lets a Librarian edit a link Document\'s title and address', function () {
    $group = linkLibraryGroup();
    $document = linkDocumentOf($group, ['title' => 'Old', 'url' => 'https://example.org/old']);

    $this->actingAs(linkLibraryMemberOf($group, Role::Librarian))
        ->patch(route('documents.update', $document), ['title' => 'New', 'url' => 'https://example.org/new', 'description' => 'Online copy'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($document->fresh())
        ->title->toBe('New')
        ->url->toBe('https://example.org/new')
        ->description->toBe('Online copy')
        ->kind->toBe(DocumentKind::Link);
});

it('keeps a link Document\'s title and address required', function () {
    $group = linkLibraryGroup();
    $document = linkDocumentOf($group, ['title' => 'Old', 'url' => 'https://example.org/old']);

    $this->actingAs(linkLibraryMemberOf($group, Role::Librarian))
        ->patch(route('documents.update', $document), ['title' => '', 'url' => ''])
        ->assertSessionHasErrors(['title', 'url']);
});

it('refuses an edit to a non-http address', function () {
    $group = linkLibraryGroup();
    $document = linkDocumentOf($group, ['url' => 'https://example.org/old']);

    $this->actingAs(linkLibraryMemberOf($group, Role::Librarian))
        ->patch(route('documents.update', $document), ['title' => 'New', 'url' => 'javascript:alert(1)'])
        ->assertSessionHasErrors('url');

    expect($document->fresh()->url)->toBe('https://example.org/old');
});

it('forbids an edit from an ordinary member or a Librarian of another Group', function (string $who) {
    $group = linkLibraryGroup();
    $document = linkDocumentOf($group, ['title' => 'Old']);
    $actor = $who === 'member' ? linkLibraryMemberOf($group) : linkLibraryMemberOf(linkLibraryGroup(), Role::Librarian);

    $this->actingAs($actor)
        ->patch(route('documents.update', $document), ['title' => 'New', 'url' => 'https://example.org'])
        ->assertForbidden();

    expect($document->fresh()->title)->toBe('Old');
})->with(['member', 'other Librarian']);

it('forbids an edit once the documents capability is off, super-tier included', function () {
    $group = linkLibraryGroup();
    $document = linkDocumentOf($group, ['title' => 'Old']);
    $group->update(['has_documents' => false]);

    $this->actingAs(Member::factory()->superTier()->create())
        ->patch(route('documents.update', $document), ['title' => 'New', 'url' => 'https://example.org'])
        ->assertForbidden();
});

it('ignores a web address sent for a file Document', function () {
    $group = linkLibraryGroup();
    $document = Document::factory()->create(['group_id' => $group->id]);

    $this->actingAs(linkLibraryMemberOf($group, Role::Librarian))
        ->patch(route('documents.update', $document), ['title' => 'New', 'url' => 'https://example.org'])
        ->assertSessionHasNoErrors();

    expect($document->fresh())->title->toBe('New')->url->toBeNull();
});

// --- Open ---------------------------------------------------------------------------

it('logs the access and redirects a member to the link\'s address', function () {
    $group = linkLibraryGroup();
    $document = linkDocumentOf($group, ['url' => 'https://example.org/handbook']);
    $member = linkLibraryMemberOf($group);

    $this->actingAs($member)
        ->get("/documents/{$document->id}/download")
        ->assertRedirect('https://example.org/handbook');

    $this->assertDatabaseHas('document_downloads', [
        'document_id' => $document->id,
        'member_id' => $member->id,
        'group_id' => $group->id,
        'original_filename' => $document->displayName(),
    ]);
});

it('opens a link from the French download route too', function () {
    $group = linkLibraryGroup();
    $document = linkDocumentOf($group, ['url' => 'https://example.org/guide']);

    $member = linkLibraryMemberOf($group);

    $this->withLocaleRoutes('fr', function () use ($document, $member) {
        $this->actingAs($member)
            ->get("/fr/documents/{$document->id}/telecharger")
            ->assertRedirect('https://example.org/guide');
    });
});

it('refuses a non-member a link, logs nothing and does not reveal the address', function () {
    $document = linkDocumentOf(linkLibraryGroup(), ['url' => 'https://example.org/secret']);

    $this->actingAs(Member::factory()->create())
        ->get("/documents/{$document->id}/download")
        ->assertForbidden()
        ->assertDontSee('example.org/secret');

    $this->assertDatabaseCount('document_downloads', 0);
});

it('returns 404 for a link once the documents capability is off, super-tier included', function () {
    $group = linkLibraryGroup();
    $document = linkDocumentOf($group);
    $group->update(['has_documents' => false]);

    $this->actingAs(Member::factory()->superTier()->create())
        ->get("/documents/{$document->id}/download")
        ->assertNotFound();

    $this->assertDatabaseCount('document_downloads', 0);
});

it('sends a logged-out visitor to login before the link', function () {
    $document = linkDocumentOf(linkLibraryGroup());

    $this->get("/documents/{$document->id}/download")->assertRedirect(route('login'));
});

// --- List ---------------------------------------------------------------------------

it('lists a link Document beside files, with its kind and no type or size', function () {
    $group = linkLibraryGroup();
    $link = linkDocumentOf($group, ['title' => 'Handbook', 'url' => 'https://example.org/handbook']);
    Document::factory()->create(['group_id' => $group->id, 'title' => 'Minutes']);

    $this->actingAs(linkLibraryMemberOf($group))
        ->get(route('groups.show', ['group' => $group, 'section' => 'documents']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('library.sections.0.documents', 2)
            ->where('library.sections.0.documents.0.id', $link->id)
            ->where('library.sections.0.documents.0.kind', 'link')
            ->where('library.sections.0.documents.0.extension', null)
            ->where('library.sections.0.documents.0.sizeBytes', null)
            ->where('library.sections.0.documents.0.url', null)
            ->where('library.sections.0.documents.0.href', "/documents/{$link->id}/download")
            ->where('library.sections.0.documents.0.downloadHref', null)
            ->where('library.sections.0.documents.1.kind', 'file'));
});

it('sends the link\'s address to a manager for editing', function () {
    $group = linkLibraryGroup();
    linkDocumentOf($group, ['url' => 'https://example.org/handbook']);

    $this->actingAs(linkLibraryMemberOf($group, Role::Librarian))
        ->get(route('groups.show', ['group' => $group, 'section' => 'documents']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('library.sections.0.documents.0.url', 'https://example.org/handbook'));
});
