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
 * Role-matrix HTTP harness for the Document edit, replace and delete writes (#713, spec #290,
 * ADR-0030). Allowed: the Group's Librarian, its Chair, and the super-tier, only while the
 * Group has the documents capability. Everyone else is refused and nothing changes, on the
 * row or on the faked private disk.
 */

beforeEach(function () {
    Storage::fake('local');
});

function editLibrary(array $attributes = []): Group
{
    return Group::factory()->create(['has_documents' => true, ...$attributes]);
}

function editMemberOf(Group $group, ?Role $role = null): Member
{
    $member = Member::factory()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);

    if ($role !== null) {
        GroupMemberRole::factory()->role($role)->create(['group_member_id' => $membership->id]);
    }

    return $member;
}

function editDocumentOf(Group $group): Document
{
    $document = Document::factory()->create(['group_id' => $group->id, 'title' => 'Before']);
    Storage::disk('local')->put($document->storage_path, 'bytes');

    return $document;
}

// Each write, as [method, route name]; its payload comes from documentWritePayload().
dataset('document writes', [
    'edit' => ['patch', 'documents.update'],
    'replace' => ['post', 'documents.replace'],
    'delete' => ['delete', 'documents.destroy'],
]);

/**
 * @return array<string, mixed>
 */
function documentWritePayload(string $route): array
{
    return match ($route) {
        'documents.update' => ['title' => 'After'],
        'documents.replace' => ['file' => UploadedFile::fake()->create('new.pdf', 10, 'application/pdf')],
        'documents.destroy' => [],
    };
}

function editActor(string $who, Group $group): Member
{
    return match ($who) {
        'Librarian' => editMemberOf($group, Role::Librarian),
        'Chair' => editMemberOf($group, Role::Chair),
        'super-tier' => Member::factory()->superTier()->create(),
        'ordinary member' => editMemberOf($group),
        'Secretary' => editMemberOf($group, Role::Secretary),
        'Librarian of another Group' => editMemberOf(editLibrary(), Role::Librarian),
        'non-member' => Member::factory()->create(),
        'parent Chair' => editMemberOf(tap(editLibrary(), fn (Group $parent) => $group->update(['parent_id' => $parent->id])), Role::Chair),
    };
}

it('lets the Librarian, the Chair, and the super-tier edit, replace and delete', function (string $who, string $method, string $route) {
    $group = editLibrary();
    $document = editDocumentOf($group);

    $this->actingAs(editActor($who, $group))
        ->{$method}(route($route, $document), documentWritePayload($route))
        ->assertSessionHasNoErrors()
        ->assertRedirect();
})->with(['Librarian', 'Chair', 'super-tier'])->with('document writes');

it('forbids every write from anyone who does not manage the library', function (string $who, string $method, string $route) {
    $group = editLibrary();
    $document = editDocumentOf($group);
    $path = $document->storage_path;

    $this->actingAs(editActor($who, $group))
        ->{$method}(route($route, $document), documentWritePayload($route))
        ->assertForbidden();

    expect($document->fresh())
        ->not->toBeNull()
        ->title->toBe('Before')
        ->storage_path->toBe($path);
    expect(Storage::disk('local')->allFiles())->toBe([$path]);
})->with(['ordinary member', 'Secretary', 'Librarian of another Group', 'non-member', 'parent Chair'])->with('document writes');

it('forbids every write once the documents capability is off, super-tier included', function (string $who, string $method, string $route) {
    $group = editLibrary();
    $document = editDocumentOf($group);
    $actor = editActor($who, $group);
    $group->update(['has_documents' => false]);

    $this->actingAs($actor)
        ->{$method}(route($route, $document), documentWritePayload($route))
        ->assertForbidden();

    expect($document->fresh())->not->toBeNull()->title->toBe('Before');
})->with(['Librarian', 'super-tier'])->with('document writes');

it('redirects an unauthenticated write to login', function (string $method, string $route) {
    $document = editDocumentOf(editLibrary());

    $this->{$method}(route($route, $document), documentWritePayload($route))
        ->assertRedirect(route('login'));
})->with('document writes');

it('shows no manage controls to a reader', function () {
    $group = editLibrary();
    editDocumentOf($group);

    $this->actingAs(editMemberOf($group))
        ->get(route('groups.show', ['group' => $group, 'section' => 'documents']))
        ->assertInertia(fn ($page) => $page->where('can.manageDocuments', false));
});
