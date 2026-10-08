<?php

use App\Enums\Role;
use App\Enums\StewardshipFunction;
use App\Models\Document;
use App\Models\DocumentDownload;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\Member;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The Download log page (#754, spec #290 story 55, ADR-0030): the super-tier reads who opened
 * which Document, and when, newest first. Read-only; filters by Group, Member, date range and
 * filename.
 *
 * Asserted from outside: the HTTP response and the Inertia props.
 */

function downloadLogRow(Member $member, Document $document, string $at, array $attributes = []): DocumentDownload
{
    return $document->downloads()->create([
        'member_id' => $member->id,
        'group_id' => $document->group_id,
        'original_filename' => $document->original_filename,
        'downloaded_at' => Carbon::parse($at),
        ...$attributes,
    ]);
}

// --- Access ---------------------------------------------------------------------------

it('sends a guest to the login page', function () {
    $this->get('/officer/document-downloads')->assertRedirect('/login');
});

it('shows the page to the super-tier', function () {
    $this->actingAs(Member::factory()->superTier()->create())
        ->get('/officer/document-downloads')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('DocumentDownloadLog'));
});

it('resolves the French twin under /fr/', function () {
    $member = Member::factory()->superTier()->create();

    $this->withLocaleRoutes('fr', function () use ($member) {
        $this->actingAs($member)
            ->get('/fr/officier/telechargements-documents')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('DocumentDownloadLog')->where('locale', 'fr'));
    });
});

it('forbids a plain Member', function () {
    $this->actingAs(Member::factory()->create())
        ->get('/officer/document-downloads')
        ->assertForbidden();
});

it('forbids a Records Member and a Group Librarian or Chair', function (Role|string $authority) {
    $group = Group::factory()->create(['has_documents' => true]);
    $member = Member::factory()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);

    if ($authority === 'records') {
        $group->stewardships()->create(['function' => StewardshipFunction::MemberAdmin]);
    } else {
        GroupMemberRole::factory()->role($authority)->create(['group_member_id' => $membership->id]);
    }

    $this->actingAs($member)
        ->get('/officer/document-downloads')
        ->assertForbidden();
})->with(['records', Role::Librarian, Role::Chair]);

it('offers no write route', function () {
    $row = downloadLogRow(Member::factory()->create(), Document::factory()->create(), '2026-09-01 12:00');

    $this->actingAs(Member::factory()->superTier()->create());

    $this->delete('/officer/document-downloads')->assertMethodNotAllowed();
    $this->post('/officer/document-downloads')->assertMethodNotAllowed();
    expect(DocumentDownload::find($row->id))->not->toBeNull();
});

// --- Rows -----------------------------------------------------------------------------

it('lists rows newest first with when, Member, Group, filename and a library link', function () {
    $group = Group::factory()->create(['has_documents' => true, 'name' => 'Visitor Wayfinders']);
    $reader = Member::factory()->create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);
    $document = Document::factory()->create(['group_id' => $group->id, 'original_filename' => 'roster.pdf']);

    downloadLogRow($reader, $document, '2026-09-01 12:00');
    $newest = downloadLogRow($reader, $document, '2026-09-03 12:00');

    $this->actingAs(Member::factory()->superTier()->create())
        ->get('/officer/document-downloads')
        ->assertInertia(fn (Assert $page) => $page
            ->component('DocumentDownloadLog')
            ->count('downloads.data', 2)
            ->where('downloads.data.0', [
                'id' => $newest->id,
                'downloadedAt' => Carbon::parse('2026-09-03 12:00')->toIso8601String(),
                'memberName' => 'Ada Lovelace',
                'groupName' => 'Visitor Wayfinders',
                'filename' => 'roster.pdf',
                'libraryHref' => route('groups.show', ['group' => $group, 'section' => 'documents'], false),
            ]));
});

it('links a Document in a Folder to that Folder, not to the download', function () {
    $document = Document::factory()->create();
    $folder = $document->group->documentFolders()->create(['name' => 'Rosters']);
    $document->update(['folder_id' => $folder->id]);
    downloadLogRow(Member::factory()->create(), $document, '2026-09-01 12:00');

    $this->actingAs(Member::factory()->superTier()->create())
        ->get('/officer/document-downloads')
        ->assertInertia(fn (Assert $page) => $page
            ->where('downloads.data.0.libraryHref', route('groups.documents.folder', ['group' => $document->group, 'folder' => $folder], false)));
});

it('keeps a deleted Document row with its snapshotted filename and no link', function () {
    $document = Document::factory()->create(['original_filename' => 'members-pii.xlsx']);
    downloadLogRow(Member::factory()->create(), $document, '2026-09-01 12:00');
    $document->delete();

    $this->actingAs(Member::factory()->superTier()->create())
        ->get('/officer/document-downloads')
        ->assertInertia(fn (Assert $page) => $page
            ->count('downloads.data', 1)
            ->where('downloads.data.0.filename', 'members-pii.xlsx')
            ->where('downloads.data.0.libraryHref', null));
});

it('shows a null name for a deleted Member and a deleted Group', function () {
    $group = Group::factory()->create(['has_documents' => true]);
    $reader = Member::factory()->create();
    downloadLogRow($reader, Document::factory()->create(['group_id' => $group->id]), '2026-09-01 12:00');
    $reader->delete();
    $group->delete();

    $this->actingAs(Member::factory()->superTier()->create())
        ->get('/officer/document-downloads')
        ->assertInertia(fn (Assert $page) => $page
            ->where('downloads.data.0.memberName', null)
            ->where('downloads.data.0.groupName', null));
});

it('pages the rows fifty at a time', function () {
    $member = Member::factory()->create();
    $document = Document::factory()->create();
    foreach (range(1, 51) as $i) {
        downloadLogRow($member, $document, Carbon::parse('2026-09-01 12:00')->addMinutes($i)->toDateTimeString());
    }

    $actor = Member::factory()->superTier()->create();

    $this->actingAs($actor)
        ->get('/officer/document-downloads')
        ->assertInertia(fn (Assert $page) => $page
            ->count('downloads.data', 50)
            ->where('downloads.current_page', 1)
            ->where('downloads.last_page', 2));

    $this->actingAs($actor)
        ->get('/officer/document-downloads?page=2')
        ->assertInertia(fn (Assert $page) => $page->count('downloads.data', 1));
});

// --- Filters --------------------------------------------------------------------------

it('filters by Group, Member, date range and filename together', function () {
    $wayfinders = Group::factory()->create(['has_documents' => true, 'name' => 'Wayfinders']);
    $library = Group::factory()->create(['has_documents' => true, 'name' => 'Library']);
    $ada = Member::factory()->create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);
    $grace = Member::factory()->create(['first_name' => 'Grace', 'last_name' => 'Hopper']);
    $roster = Document::factory()->create(['group_id' => $wayfinders->id, 'original_filename' => 'roster-2026.pdf']);
    $minutes = Document::factory()->create(['group_id' => $wayfinders->id, 'original_filename' => 'minutes.pdf']);
    $other = Document::factory()->create(['group_id' => $library->id, 'original_filename' => 'roster-library.pdf']);

    $match = downloadLogRow($ada, $roster, '2026-09-10 15:00');
    downloadLogRow($grace, $roster, '2026-09-10 15:00');     // wrong Member
    downloadLogRow($ada, $other, '2026-09-10 15:00');        // wrong Group
    downloadLogRow($ada, $minutes, '2026-09-10 15:00');      // wrong filename
    downloadLogRow($ada, $roster, '2026-08-31 15:00');       // before the range
    downloadLogRow($ada, $roster, '2026-09-21 15:00');       // after the range

    $query = http_build_query([
        'group' => $wayfinders->id,
        'member' => $ada->id,
        'from' => '2026-09-01',
        'to' => '2026-09-20',
        'q' => 'roster',
    ]);

    $this->actingAs(Member::factory()->superTier()->create())
        ->get("/officer/document-downloads?{$query}")
        ->assertInertia(fn (Assert $page) => $page
            ->count('downloads.data', 1)
            ->where('downloads.data.0.id', $match->id)
            ->where('filters', [
                'group' => $wayfinders->id,
                'member' => $ada->id,
                'from' => '2026-09-01',
                'to' => '2026-09-20',
                'q' => 'roster',
            ]));
});

it('reads the date range as whole days in the org timezone', function () {
    $member = Member::factory()->create();
    $document = Document::factory()->create();

    // 23:30 on 20 September in Toronto is 03:30 UTC on the 21st: still inside a range ending the 20th.
    $lateEvening = downloadLogRow($member, $document, '2026-09-21 03:30:00');
    // 00:30 on 21 September in Toronto: outside it.
    downloadLogRow($member, $document, '2026-09-21 04:30:00');
    // 00:30 on 1 September in Toronto is 04:30 UTC: inside a range starting the 1st.
    $earlyMorning = downloadLogRow($member, $document, '2026-09-01 04:30:00');
    // 23:30 on 31 August in Toronto: outside it.
    downloadLogRow($member, $document, '2026-09-01 03:30:00');

    $this->actingAs(Member::factory()->superTier()->create())
        ->get('/officer/document-downloads?from=2026-09-01&to=2026-09-20')
        ->assertInertia(fn (Assert $page) => $page
            ->count('downloads.data', 2)
            ->where('downloads.data.0.id', $lateEvening->id)
            ->where('downloads.data.1.id', $earlyMorning->id));
});

it('ignores malformed filter values', function () {
    downloadLogRow(Member::factory()->create(), Document::factory()->create(), '2026-09-01 12:00');

    $this->actingAs(Member::factory()->superTier()->create())
        ->get('/officer/document-downloads?group=abc&member[]=1&from=yesterday&to=2026-02-31')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->count('downloads.data', 1)
            ->where('filters', ['group' => null, 'member' => null, 'from' => null, 'to' => null, 'q' => null]));
});

it('offers the Groups and Members that appear in the log as filter options', function () {
    $wayfinders = Group::factory()->create(['has_documents' => true, 'name' => 'Wayfinders']);
    Group::factory()->create(['name' => 'Never downloaded']);
    $ada = Member::factory()->create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);
    Member::factory()->create(['first_name' => 'Never', 'last_name' => 'Reader']);
    downloadLogRow($ada, Document::factory()->create(['group_id' => $wayfinders->id]), '2026-09-01 12:00');

    $this->actingAs(Member::factory()->superTier()->create())
        ->get('/officer/document-downloads')
        ->assertInertia(fn (Assert $page) => $page
            ->where('groups', [['value' => $wayfinders->id, 'label' => 'Wayfinders']])
            ->where('members', [['value' => $ada->id, 'label' => 'Ada Lovelace']]));
});
