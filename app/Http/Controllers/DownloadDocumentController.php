<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Policies\DocumentPolicy;
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
 */
class DownloadDocumentController extends Controller
{
    public function __invoke(Request $request, Document $document): StreamedResponse
    {
        // The capability check comes first so it holds for the super-tier too.
        abort_unless($document->group->has_documents, 404);

        Gate::authorize('download', $document);

        $disk = Storage::disk('local');

        abort_unless($document->storage_path !== null && $disk->exists($document->storage_path), 404);

        $document->downloads()->create([
            'member_id' => $request->user()->id,
            'downloaded_at' => now(),
        ]);

        return $disk->download($document->storage_path, $document->original_filename, [
            'Content-Type' => $document->mime_type,
        ]);
    }
}
