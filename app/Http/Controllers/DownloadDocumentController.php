<?php

namespace App\Http\Controllers;

use App\Enums\DocumentKind;
use App\Models\Document;
use App\Policies\DocumentPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Download one Document (#712, ADR-0003, ADR-0030 §13) under its original filename. Every
 * download passes {@see DocumentPolicy::download()} and writes an access-log row before the
 * file streams. The URL `/documents/{document}/download` is stable so the legacy redirect map
 * can point at it. A logged-out visitor gets the login page, then the file (`auth` keeps the
 * intended URL).
 *
 * A link Document (#716, ADR-0030 §7) passes the same check and writes the same log row, then
 * redirects to its web address.
 */
class DownloadDocumentController extends Controller
{
    public function __invoke(Request $request, Document $document): StreamedResponse|RedirectResponse
    {
        // The capability check comes first so it holds for the super-tier too.
        abort_unless($document->group->has_documents, 404);

        Gate::authorize('download', $document);

        if ($document->kind === DocumentKind::Link) {
            abort_unless($document->url !== null, 404);
            $this->logAccess($request, $document);

            return redirect()->away($document->url);
        }

        $disk = Storage::disk('local');

        abort_unless($document->storage_path !== null && $disk->exists($document->storage_path), 404);

        $this->logAccess($request, $document);

        return $disk->download($document->storage_path, $document->original_filename, [
            'Content-Type' => $document->mime_type,
        ]);
    }

    /**
     * The access-log row: who opened which Document, and when.
     */
    private function logAccess(Request $request, Document $document): void
    {
        $document->downloads()->create([
            'member_id' => $request->user()->id,
            'downloaded_at' => now(),
        ]);
    }
}
