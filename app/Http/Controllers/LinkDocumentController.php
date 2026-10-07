<?php

namespace App\Http\Controllers;

use App\Enums\DocumentKind;
use App\Http\Requests\StoreLinkDocumentRequest;
use App\Http\Requests\UpdateLinkDocumentRequest;
use App\Models\Document;
use App\Models\Group;
use Illuminate\Http\RedirectResponse;

/**
 * The Librarian's writes for link Documents (#716, spec #290, ADR-0030 §7): a title and a
 * web address in place of a stored file. Opening one goes through the download route
 * ({@see DownloadDocumentController}), which checks access, logs, then redirects.
 * Delete, move and Tags are shared with file Documents on {@see DocumentController}.
 */
class LinkDocumentController extends Controller
{
    /**
     * Add a link Document to the library root.
     */
    public function store(StoreLinkDocumentRequest $request, Group $group): RedirectResponse
    {
        $group->documents()->create([
            ...$request->validated(),
            'kind' => DocumentKind::Link,
            'uploaded_by_id' => $request->user()->id,
            'uploaded_at' => now(),
        ]);

        return back();
    }

    /**
     * Change a link Document's title and web address.
     */
    public function update(UpdateLinkDocumentRequest $request, Document $document): RedirectResponse
    {
        abort_unless($document->kind === DocumentKind::Link, 404);

        $document->update($request->validated());

        return back();
    }
}
