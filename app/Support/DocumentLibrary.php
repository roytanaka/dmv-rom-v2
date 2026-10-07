<?php

namespace App\Support;

use App\Models\Document;
use App\Models\DocumentFolder;
use App\Models\Group;
use App\Models\Member;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * The Documents tab's payload (#712, #714, #715, spec #290, ADR-0030): one Folder of a
 * Group's Document library, or the library root, as the viewer may read it. Built by
 * {@see self::for()}; {@see self::empty()} is the same shape for every other tab.
 *
 * Every Folder and Document passes the policy's read check, so a non-member sees only what the
 * Group shares with every Member. The uploader, the upload time and the move destinations ride
 * only for a manager.
 */
class DocumentLibrary
{
    /**
     * The payload for every tab other than Documents.
     *
     * @return array{folder: null, breadcrumb: list<never>, folders: list<never>, destinations: list<never>, maxDepth: int, documents: list<never>}
     */
    public static function empty(): array
    {
        return ['folder' => null, 'breadcrumb' => [], 'folders' => [], 'destinations' => [], 'maxDepth' => DocumentFolder::MAX_DEPTH, 'documents' => []];
    }

    /**
     * One Folder of the library (the root when `$folder` is null): its breadcrumb, child
     * Folders and Documents, each sorted by name (a Document's title, or its filename when
     * there is none). Aborts 403 for a Folder the viewer may not read.
     *
     * @return array{folder: array<string, mixed>|null, breadcrumb: list<array<string, mixed>>, folders: list<array<string, mixed>>, destinations: list<array<string, mixed>>, maxDepth: int, documents: list<array<string, mixed>>}
     */
    public static function for(Member $viewer, Group $group, ?DocumentFolder $folder): array
    {
        $canManage = $viewer->can('manage', [Document::class, $group]);

        // Every Folder of the Group, fetched once with its ancestors preloaded, so each
        // visibility check (on Folders and on Documents, through their Folder) and the
        // breadcrumb answer from memory (#715). The open Folder is swapped for its copy here.
        $tree = $group->documentFolders()->get()
            ->each(fn (DocumentFolder $item) => $item->setRelation('group', $group))
            ->keyBy('id');
        DocumentFolder::preloadAncestors($tree);
        $folder = $folder === null ? null : $tree[$folder->id];

        abort_if($folder !== null && $viewer->cannot('view', $folder), 403);

        $folderRow = fn (DocumentFolder $item) => [
            'id' => $item->id,
            // Content, as-authored (ADR-0004).
            'name' => $item->name,
            'href' => route('groups.documents.folder', ['group' => $group, 'folder' => $item], absolute: false),
            // Who reads it (#715, ADR-0030 §5): its top-level Folder's setting.
            'visibility' => $item->visibility()->value,
        ];

        // The open Folder's child Folders (#714).
        $folders = $tree
            ->where('parent_id', $folder?->id)
            ->filter(fn (DocumentFolder $child) => $viewer->can('view', $child))
            ->sortBy(fn (DocumentFolder $child) => mb_strtolower($child->name), SORT_NATURAL)
            ->values();

        $documents = self::readable($viewer, $group, $tree, $group->documents()
            ->where('folder_id', $folder?->id)
            ->with('uploadedBy'))
            ->sortBy(fn (Document $document) => mb_strtolower($document->displayName()), SORT_NATURAL)
            ->values();

        return [
            'folder' => $folder === null ? null : $folderRow($folder),
            // The Folders above the current one, top-level first; the client adds the root.
            'breadcrumb' => $folder === null ? [] : $folder->ancestors()->map($folderRow)->values()->all(),
            'folders' => $folders->map($folderRow)->all(),
            'destinations' => $canManage ? self::destinations($tree->filter(fn (DocumentFolder $item) => $viewer->can('view', $item))) : [],
            // The depth limit, so the client offers only the Folder actions the server will accept.
            'maxDepth' => DocumentFolder::MAX_DEPTH,
            'documents' => $documents
                ->map(fn (Document $document) => [
                    'id' => $document->id,
                    'kind' => $document->kind->value,
                    // The Folder it sits in (#714), for the move picker; null at the root.
                    'folderId' => $document->folder_id,
                    // A link's address, for a manager's edit form only; readers open it
                    // through `href`, so every open is checked and logged (#716).
                    'url' => $canManage ? $document->url : null,
                    // Content, as-authored (ADR-0004); the client falls back to the filename.
                    'title' => $document->title,
                    'description' => $document->description,
                    'filename' => $document->original_filename,
                    'extension' => $document->extension(),
                    'sizeBytes' => $document->size_bytes,
                    'updatedAt' => $document->updated_at->toIso8601String(),
                    'uploader' => $canManage ? $document->uploadedBy?->fullName() : null,
                    // When the current file (or link) was put up (story 51), managers only.
                    'uploadedAt' => $canManage ? $document->uploaded_at?->toIso8601String() : null,
                    'href' => route('documents.download', $document, absolute: false),
                ])
                ->all(),
        ];
    }

    /**
     * The Documents of `$query` the viewer may download. Each row's Group and Folder come from
     * memory (the preloaded tree), so the visibility check costs no query.
     *
     * @param  Collection<int, DocumentFolder>  $tree  the Group's Folders, keyed by id, ancestors preloaded
     * @param  HasMany<Document, Group>  $query
     * @return Collection<int, Document>
     */
    private static function readable(Member $viewer, Group $group, Collection $tree, HasMany $query): Collection
    {
        return $query->get()
            ->each(fn (Document $document) => $document
                ->setRelation('group', $group)
                ->setRelation('folder', $document->folder_id === null ? null : $tree[$document->folder_id]))
            ->filter(fn (Document $document) => $viewer->can('download', $document))
            ->toBase();
    }

    /**
     * The Group's Folders as move destinations (#714): id, parent, depth and full path of
     * names, in tree order. Sent to a manager only, and only the Folders they may read (#715);
     * the move Form Requests re-check the Group, the cycle and the depth limit.
     *
     * @param  Collection<int, DocumentFolder>  $folders
     * @return list<array{id: int, parentId: int|null, depth: int, path: list<string>}>
     */
    private static function destinations(Collection $folders): array
    {
        $children = $folders->groupBy(fn (DocumentFolder $folder) => $folder->parent_id ?? 0);
        $rows = [];

        $walk = function (int $parentId, array $path) use (&$walk, &$rows, $children): void {
            $siblings = ($children[$parentId] ?? collect())
                ->sortBy(fn (DocumentFolder $folder) => mb_strtolower($folder->name), SORT_NATURAL);

            foreach ($siblings as $folder) {
                $folderPath = [...$path, $folder->name];
                $rows[] = ['id' => $folder->id, 'parentId' => $folder->parent_id, 'depth' => count($folderPath), 'path' => $folderPath];
                $walk($folder->id, $folderPath);
            }
        };
        $walk(0, []);

        return $rows;
    }
}
