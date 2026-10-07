<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteDocumentFolderRequest;
use App\Http\Requests\MoveDocumentFolderRequest;
use App\Http\Requests\StoreDocumentFolderRequest;
use App\Http\Requests\UpdateDocumentFolderRequest;
use App\Models\DocumentFolder;
use App\Models\Group;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

/**
 * The Librarian's Folder writes for a Group's Document library (#714, spec #290, ADR-0030 §3),
 * the Meetings pattern: store nests under the Group, rename, move and delete bind the Folder
 * by id. Authorized through the DocumentFolderPolicy; the read lives on
 * {@see GroupController::showDocumentFolder()}.
 */
class DocumentFolderController extends Controller
{
    /**
     * Create a Folder at the top level or inside another Folder.
     */
    public function store(StoreDocumentFolderRequest $request, Group $group): RedirectResponse
    {
        $group->documentFolders()->create($request->validated());

        return back();
    }

    /**
     * Rename a Folder, and set a top-level Folder's visibility (#715).
     */
    public function update(UpdateDocumentFolderRequest $request, DocumentFolder $folder): RedirectResponse
    {
        $folder->update($request->validated());

        return back();
    }

    /**
     * Move a Folder, and everything in it, under another parent or to the top level. The
     * Folder's visibility follows on save ({@see DocumentFolder::booted()}): under a parent it
     * inherits; at the top level it keeps what it had through its old top-level Folder (#715).
     */
    public function move(MoveDocumentFolderRequest $request, DocumentFolder $folder): RedirectResponse
    {
        $folder->update(['parent_id' => $request->validated('parent_id')]);

        return back();
    }

    /**
     * Delete an empty Folder. One that still holds Folders or Documents is refused with a
     * message; nothing is deleted along with it.
     */
    public function destroy(DeleteDocumentFolderRequest $request, DocumentFolder $folder): RedirectResponse
    {
        if (! $folder->isEmpty()) {
            throw ValidationException::withMessages(['folder' => trans('document_folders.error.not_empty')]);
        }

        $folder->delete();

        return back();
    }
}
