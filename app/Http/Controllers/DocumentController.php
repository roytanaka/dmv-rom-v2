<?php

namespace App\Http\Controllers;

use App\Enums\DocumentKind;
use App\Http\Requests\DeleteDocumentRequest;
use App\Http\Requests\ReplaceDocumentFileRequest;
use App\Http\Requests\StoreDocumentRequest;
use App\Http\Requests\UpdateDocumentRequest;
use App\Models\Document;
use App\Models\Group;
use App\Support\DocumentStorage;
use Illuminate\Http\RedirectResponse;

/**
 * The Librarian's write seam for a Group's Document library (#712, spec #290, ADR-0030),
 * parallel to the read surface on {@see GroupController::show}. Follows the Meetings
 * pattern: store nests under the Group, later edits and deletes bind the Document by id.
 * Every write is authorized in its Form Request through the DocumentPolicy.
 */
class DocumentController extends Controller
{
    /**
     * Upload one file to the library root, or into the Folder the request names (#714), under
     * the Document category it names (#728).
     */
    public function store(StoreDocumentRequest $request, Group $group, DocumentStorage $storage): RedirectResponse
    {
        $group->documents()->create([
            ...$storage->store($request->file('file')),
            'kind' => DocumentKind::File,
            'folder_id' => $request->validated('folder_id'),
            // One Document category for the whole batch (#728): each file's request carries it.
            'category_id' => $request->validated('category_id'),
            'uploaded_by_id' => $request->user()->id,
            'uploaded_at' => now(),
        ]);

        return back();
    }

    /**
     * Edit a Document's title and description (#713).
     */
    public function update(UpdateDocumentRequest $request, Document $document): RedirectResponse
    {
        $document->update($request->validated());

        return back();
    }

    /**
     * Upload a new file over a Document (#713, ADR-0030 §8). The row keeps its id, so its
     * download link and Folder stay; the file's columns and the uploader change. The old
     * file is deleted only once the row points at the new one.
     */
    public function replace(ReplaceDocumentFileRequest $request, Document $document, DocumentStorage $storage): RedirectResponse
    {
        $oldPath = $document->storage_path;

        $document->update([
            ...$storage->store($request->file('file')),
            'uploaded_by_id' => $request->user()->id,
            'uploaded_at' => now(),
        ]);

        if ($oldPath !== null) {
            $storage->delete($oldPath);
        }

        return back();
    }

    /**
     * Delete a Document and its stored file (#713). Its access log rows cascade at the database.
     */
    public function destroy(DeleteDocumentRequest $request, Document $document, DocumentStorage $storage): RedirectResponse
    {
        $document->delete();

        if ($document->storage_path !== null) {
            $storage->delete($document->storage_path);
        }

        return back();
    }
}
