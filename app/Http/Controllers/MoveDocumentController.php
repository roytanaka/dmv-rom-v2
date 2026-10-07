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
        $document->update(['folder_id' => $request->validated('folder_id')]);

        return back();
    }
}
