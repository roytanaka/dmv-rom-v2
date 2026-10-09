<?php

namespace App\Support;

use App\Enums\DocumentKind;
use App\Models\Document;
use App\Models\DocumentCategory;
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
     * @return array{folder: null, breadcrumb: list<never>, categories: list<never>, category: null, sections: list<never>, folderCount: int, documentCount: int, destinations: list<never>, rootCategories: list<never>, maxDepth: int}
     */
    public static function empty(): array
    {
        return ['folder' => null, 'breadcrumb' => [], 'categories' => [], 'category' => null, 'sections' => [], 'folderCount' => 0, 'documentCount' => 0, 'destinations' => [], 'rootCategories' => [], 'maxDepth' => DocumentFolder::MAX_DEPTH];
    }

    /**
     * One Folder of the library (the root when `$folder` is null): its breadcrumb, its
     * Document categories (#724) and its child Folders and Documents in sections
     * ({@see self::sections()}), narrowed to one Document category when `$category` is given
     * (#725). Aborts 403 for a Folder the viewer may not read.
     *
     * @return array{folder: array<string, mixed>|null, breadcrumb: list<array<string, mixed>>, categories: list<array{id: int, name: string}>, category: int|null, sections: list<array<string, mixed>>, folderCount: int, documentCount: int, destinations: list<array<string, mixed>>, rootCategories: list<array{id: int, name: string}>, maxDepth: int}
     */
    public static function for(Member $viewer, Group $group, ?DocumentFolder $folder, ?int $category = null): array
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
            // Its section in its parent Folder (#724); null is Other.
            'categoryId' => $item->category_id,
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

        // The Document category lists, keyed by Folder id, 0 for the root: every Folder's for a
        // manager's move dialog (#728), which picks from the destination's list; only the open
        // Folder's for a reader.
        $lists = self::categoryLists($canManage
            ? $group->documentCategories()
            : $group->documentCategories()->where('folder_id', $folder?->id));

        // The open Folder's own Document categories (#724, ADR-0030 §4), sorted by name.
        $categories = $lists[$folder?->id ?? 0] ?? [];

        $documentRow = fn (Document $document) => [
            'id' => $document->id,
            'kind' => $document->kind->value,
            // The Folder it sits in (#714), for the move picker; null at the root.
            'folderId' => $document->folder_id,
            // Its section in that Folder (#724); null is Other.
            'categoryId' => $document->category_id,
            // A link's address, for a manager's edit form only; readers open it
            // through `href`, so every open is checked and logged (#716).
            'url' => $canManage ? $document->url : null,
            // Content, as-authored (ADR-0004); the client falls back to the filename.
            'title' => $document->title,
            'description' => $document->description,
            'filename' => $document->original_filename,
            'extension' => $document->extension(),
            // Picks the row's file-type icon (#777); null for a link.
            'mimeType' => $document->mime_type,
            'sizeBytes' => $document->size_bytes,
            'updatedAt' => $document->updated_at->toIso8601String(),
            'uploader' => $canManage ? $document->uploadedBy?->fullName() : null,
            // When the current file (or link) was put up (story 51), managers only.
            'uploadedAt' => $canManage ? $document->uploaded_at?->toIso8601String() : null,
            'href' => route('documents.download', $document, absolute: false),
            // The image viewer's Download button (#779): the same gated, logged route, as an
            // attachment. A link has nothing to save.
            'downloadHref' => $document->kind === DocumentKind::Link
                ? null
                : route('documents.download', [$document, 'download' => 1], absolute: false),
        ];

        $sections = self::sections($categories, $folders->map($folderRow)->all(), $documents->map($documentRow)->all());

        // A reader who cannot manage the library never sees a Document category with nothing
        // they may read (#725, ADR-0030 §4): not as a section, not in the filter. So the names
        // of members-only sections stay hidden from a non-member. A manager keeps every one.
        if (! $canManage) {
            $sections = array_values(array_filter($sections, self::holdsItems(...)));
            $shown = array_map(fn (array $section) => $section['category']['id'] ?? null, $sections);
            $categories = array_values(array_filter($categories, fn (array $category) => in_array($category['id'], $shown, true)));
        }

        // The Category filter (#725): only that Document category's section. An id that is not
        // one of the shown Document categories (another Folder's, or one hidden above) leaves
        // nothing, never an error.
        if ($category !== null) {
            $sections = array_values(array_filter($sections, fn (array $section) => ($section['category']['id'] ?? null) === $category));
        }

        return [
            'folder' => $folder === null ? null : $folderRow($folder),
            // The Folders above the current one, top-level first; the client adds the root.
            'breadcrumb' => $folder === null ? [] : $folder->ancestors()->map($folderRow)->values()->all(),
            'categories' => $categories,
            // The active Category filter (#725): the `?category=` id as asked, or null for all.
            'category' => $category,
            'sections' => $sections,
            // What the open Folder holds, for its header (#726): every readable child, whichever
            // section it sits in. The Category filter leaves these whole.
            'folderCount' => $folders->count(),
            'documentCount' => $documents->count(),
            'destinations' => $canManage ? self::destinations($tree->filter(fn (DocumentFolder $item) => $viewer->can('view', $item)), $lists) : [],
            // The library root's Document categories, for a move to the top level (#728).
            'rootCategories' => $canManage ? $lists[0] ?? [] : [],
            // The depth limit, so the client offers only the Folder actions the server will accept.
            'maxDepth' => DocumentFolder::MAX_DEPTH,
        ];
    }

    /**
     * The open Folder's rows in sections (#724, ADR-0030 §4): one per Document category, in
     * the order given (by name), then Other (`category` null) for the rows with none. Other
     * appears only when it holds something, so a Folder with no Document categories is one
     * plain section and an empty Folder has none. Each section keeps its rows' order (Folders,
     * then Documents, each by name) and counts them. A row whose Document category is not in
     * the list falls to Other.
     *
     * @param  list<array{id: int, name: string}>  $categories
     * @param  list<array<string, mixed>>  $folders  rows carrying `categoryId`
     * @param  list<array<string, mixed>>  $documents  rows carrying `categoryId`
     * @return list<array{category: array{id: int, name: string}|null, folders: list<array<string, mixed>>, documents: list<array<string, mixed>>, folderCount: int, documentCount: int}>
     */
    private static function sections(array $categories, array $folders, array $documents): array
    {
        $known = array_column($categories, 'id');
        $keyOf = fn (array $row) => in_array($row['categoryId'], $known, true) ? $row['categoryId'] : 0;
        $foldersBy = collect($folders)->groupBy($keyOf);
        $documentsBy = collect($documents)->groupBy($keyOf);

        $section = function (?array $category, int $key) use ($foldersBy, $documentsBy): array {
            $sectionFolders = ($foldersBy[$key] ?? collect())->values()->all();
            $sectionDocuments = ($documentsBy[$key] ?? collect())->values()->all();

            return [
                'category' => $category,
                'folders' => $sectionFolders,
                'documents' => $sectionDocuments,
                'folderCount' => count($sectionFolders),
                'documentCount' => count($sectionDocuments),
            ];
        };

        $sections = array_map(fn (array $category) => $section($category, $category['id']), $categories);
        $other = $section(null, 0);

        if (self::holdsItems($other)) {
            $sections[] = $other;
        }

        return $sections;
    }

    /**
     * Whether a section holds a Folder or a Document.
     *
     * @param  array{folderCount: int, documentCount: int}  $section
     */
    private static function holdsItems(array $section): bool
    {
        return $section['folderCount'] + $section['documentCount'] > 0;
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
     * the move Form Requests re-check the Group, the cycle and the depth limit. Each carries its
     * Document categories (#728), so the move can file the item in its new Folder.
     *
     * @param  Collection<int, DocumentFolder>  $folders
     * @param  Collection<int, list<array{id: int, name: string}>>  $lists  {@see self::categoryLists()}
     * @return list<array{id: int, parentId: int|null, depth: int, path: list<string>, categories: list<array{id: int, name: string}>}>
     */
    private static function destinations(Collection $folders, Collection $lists): array
    {
        $children = $folders->groupBy(fn (DocumentFolder $folder) => $folder->parent_id ?? 0);
        $rows = [];

        $walk = function (int $parentId, array $path) use (&$walk, &$rows, $children, $lists): void {
            $siblings = ($children[$parentId] ?? collect())
                ->sortBy(fn (DocumentFolder $folder) => mb_strtolower($folder->name), SORT_NATURAL);

            foreach ($siblings as $folder) {
                $folderPath = [...$path, $folder->name];
                $rows[] = [
                    'id' => $folder->id,
                    'parentId' => $folder->parent_id,
                    'depth' => count($folderPath),
                    'path' => $folderPath,
                    'categories' => $lists[$folder->id] ?? [],
                ];
                $walk($folder->id, $folderPath);
            }
        };
        $walk(0, []);

        return $rows;
    }

    /**
     * The Document categories of `$query` as one list per Folder (#728), keyed by Folder id (0
     * for the library root), each sorted by name. One query.
     *
     * @param  HasMany<DocumentCategory, Group>  $query
     * @return Collection<int, list<array{id: int, name: string}>>
     */
    private static function categoryLists(HasMany $query): Collection
    {
        return $query
            ->get(['id', 'folder_id', 'name'])
            ->sortBy(fn (DocumentCategory $category) => mb_strtolower($category->name), SORT_NATURAL)
            ->groupBy(fn (DocumentCategory $category) => $category->folder_id ?? 0)
            ->map(fn (Collection $list) => $list->map(fn (DocumentCategory $category) => ['id' => $category->id, 'name' => $category->name])->values()->all());
    }
}
