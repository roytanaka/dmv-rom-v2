<?php

namespace App\Http\Controllers;

use App\Enums\DocumentKind;
use App\Http\Requests\StoreDocumentRequest;
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
     * Upload one file to the library root, or into the Folder the request names (#714).
     */
    public function store(StoreDocumentRequest $request, Group $group, DocumentStorage $storage): RedirectResponse
    {
        $group->documents()->create([
            ...$storage->store($request->file('file')),
            'kind' => DocumentKind::File,
            'folder_id' => $request->validated('folder_id'),
            'uploaded_by_id' => $request->user()->id,
            'uploaded_at' => now(),
        ]);

        return back();
    }
}
