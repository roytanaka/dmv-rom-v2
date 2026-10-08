<?php

namespace App\Http\Controllers;

use App\Http\Requests\MoveDocumentRequest;
use App\Models\Document;
use Illuminate\Http\RedirectResponse;

/**
 * Move a Document to another Folder of its Group, or to the library root (#714, ADR-0030 §3).
 * Its id, download link and file stay the same.
 */
class MoveDocumentController extends Controller
{
    public function __invoke(MoveDocumentRequest $request, Document $document): RedirectResponse
    {
        // A Document category belongs to one Folder, so the move clears it or sets the
        // destination's (#728, ADR-0030 §4).
        $document->update([
            'folder_id' => $request->validated('folder_id'),
            'category_id' => $request->validated('category_id'),
        ]);

        return back();
    }
}
