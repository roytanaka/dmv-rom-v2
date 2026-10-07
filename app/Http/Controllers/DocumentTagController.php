<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteDocumentTagRequest;
use App\Http\Requests\StoreDocumentTagRequest;
use App\Http\Requests\SyncDocumentTagsRequest;
use App\Http\Requests\UpdateDocumentTagRequest;
use App\Models\Document;
use App\Models\DocumentTag;
use App\Models\Group;
use Illuminate\Http\RedirectResponse;

/**
 * The Librarian's Tag writes for a Group's Document library (#717, spec #290, ADR-0030 §4):
 * create, rename and delete a Tag, and set which Tags a Document carries. The Meetings
 * pattern: create nests under the Group, rename and delete bind the Tag by id. Every write
 * is authorized in its Form Request through the DocumentTagPolicy. The read (each row's
 * Tags, the Group's Tags, and the one-Tag filter) lives on `groups.show`.
 */
class DocumentTagController extends Controller
{
    public function store(StoreDocumentTagRequest $request, Group $group): RedirectResponse
    {
        $group->documentTags()->create($request->validated());

        return back();
    }

    public function update(UpdateDocumentTagRequest $request, DocumentTag $documentTag): RedirectResponse
    {
        $documentTag->update($request->validated());

        return back();
    }

    /**
     * Delete a Tag. Its pivot rows cascade at the database, so no Document keeps it.
     */
    public function destroy(DeleteDocumentTagRequest $request, DocumentTag $documentTag): RedirectResponse
    {
        $documentTag->delete();

        return back();
    }

    /**
     * Replace a Document's Tags with the supplied set.
     */
    public function sync(SyncDocumentTagsRequest $request, Document $document): RedirectResponse
    {
        $document->tags()->sync($request->validated('tags'));

        return back();
    }
}
