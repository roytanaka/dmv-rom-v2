<?php

use App\Enums\Role;
use App\Models\Document;
use App\Models\DocumentTag;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\Member;
use Illuminate\Support\Facades\DB;

/*
 * Role-matrix HTTP harness for Document library Tags (#717, spec #290, ADR-0030 §4).
 *
 * Every Tag write (create, rename, delete, set a Document's Tags) is a manage action: the
 * Group's Librarian, its Chair, and the super-tier, only while the Group has the documents
 * capability. Parentage never grants a write. Exercised through every layer: route → auth →
 * Form Request → DocumentTagPolicy → DocumentPolicy → Gate::before.
 */

function tagGroup(array $attributes = []): Group
{
    return Group::factory()->create(['has_documents' => true, ...$attributes]);
}

function tagActorFor(Group $group, string $who): Member
{
    $memberOf = function (Group $group, ?Role $role = null): Member {
        $member = Member::factory()->create();
        $membership = GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);

        if ($role !== null) {
            GroupMemberRole::factory()->role($role)->create(['group_member_id' => $membership->id]);
        }

        return $member;
    };

    return match ($who) {
        'Librarian' => $memberOf($group, Role::Librarian),
        'Chair' => $memberOf($group, Role::Chair),
        'super-tier' => Member::factory()->superTier()->create(),
        'ordinary member' => $memberOf($group),
        'Secretary' => $memberOf($group, Role::Secretary),
        'Librarian of another Group' => $memberOf(tagGroup(), Role::Librarian),
        'non-member' => Member::factory()->create(),
        'parent Chair' => $memberOf(tap(tagGroup(), fn (Group $parent) => $group->update(['parent_id' => $parent->id])), Role::Chair),
    };
}

/**
 * Each Tag write against the Group, as [method, url, payload].
 *
 * @return array<string, array{0: string, 1: string, 2: array<string, mixed>}>
 */
function tagWrites(Group $group): array
{
    $tag = DocumentTag::factory()->create(['group_id' => $group->id, 'name' => 'Highlights']);
    $document = Document::factory()->create(['group_id' => $group->id]);

    return [
        'create' => ['post', route('document-tags.store', $group), ['name' => 'Required']],
        'rename' => ['patch', route('document-tags.update', $tag), ['name' => 'Renamed']],
        'delete' => ['delete', route('document-tags.destroy', $tag), []],
        'assign' => ['put', route('documents.tags.update', $document), ['tags' => [$tag->id]]],
    ];
}

/** The Tag state a refused write must leave untouched. */
function tagState(): array
{
    return [
        DocumentTag::query()->orderBy('id')->pluck('name')->all(),
        DB::table('document_tag')->count(),
    ];
}

it('lets the Librarian, the Chair, and the super-tier write Tags', function (string $who, string $write) {
    $group = tagGroup();
    [$method, $url, $payload] = tagWrites($group)[$write];

    $this->actingAs(tagActorFor($group, $who))
        ->{$method}($url, $payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect();
})->with(['Librarian', 'Chair', 'super-tier'])->with(['create', 'rename', 'delete', 'assign']);

it('forbids a Tag write from anyone who does not manage the library', function (string $who, string $write) {
    $group = tagGroup();
    [$method, $url, $payload] = tagWrites($group)[$write];
    $actor = tagActorFor($group, $who);
    $before = tagState();

    $this->actingAs($actor)->{$method}($url, $payload)->assertForbidden();

    expect(tagState())->toBe($before);
})->with(['ordinary member', 'Secretary', 'Librarian of another Group', 'non-member', 'parent Chair'])
    ->with(['create', 'rename', 'delete', 'assign']);

it('forbids a Tag write in a Group without the documents capability, super-tier included', function (string $who, string $write) {
    $group = tagGroup();
    [$method, $url, $payload] = tagWrites($group)[$write];
    $actor = tagActorFor($group, $who);
    $group->update(['has_documents' => false]);
    $before = tagState();

    $this->actingAs($actor)->{$method}($url, $payload)->assertForbidden();

    expect(tagState())->toBe($before);
})->with(['Chair', 'super-tier'])->with(['create', 'rename', 'delete', 'assign']);

it('redirects an unauthenticated Tag write to login', function (string $write) {
    [$method, $url, $payload] = tagWrites(tagGroup())[$write];

    $this->{$method}($url, $payload)->assertRedirect(route('login'));
})->with(['create', 'rename', 'delete', 'assign']);
