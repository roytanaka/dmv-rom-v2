<?php

namespace App\Http\Controllers;

use App\Enums\DocumentKind;
use App\Http\Requests\StoreLinkDocumentRequest;
use App\Models\Group;
use Illuminate\Http\RedirectResponse;

/**
 * The Librarian's writes for link Documents (#716, spec #290, ADR-0030 §7): a title and a
 * web address in place of a stored file. Opening one goes through the download route
 * ({@see DownloadDocumentController}), which checks access, logs, then redirects.
 * Edit, delete, move and Tags are shared with file Documents on {@see DocumentController}.
 */
class LinkDocumentController extends Controller
{
    /**
     * Add a link Document to the library root, or to the Folder named by `folder_id` (#714).
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
}
