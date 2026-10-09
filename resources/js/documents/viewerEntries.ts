/**
 * The image viewer's entries for a Document library page (#779, #781, spec #773): every
 * Document in the list on screen, across its sections, in page order. The server has already
 * applied the category filter, and child Folders are not Documents. A click on an image
 * Document opens the viewer at its entry; previous/next then steps through the whole list.
 *
 * A file loads through the gated, logged download route: `src` inline, `downloadHref` as an
 * attachment. A link becomes a link card that opens through the same gated route.
 */
import type { LibraryDocument, LibrarySection } from '../types/index.ts';
import type { ViewerEntry } from '../viewer/entries.ts';

function toEntry(document: LibraryDocument): ViewerEntry {
    if (document.kind === 'link') {
        return { kind: 'link', key: document.id, title: document.title ?? '', address: document.url ?? '', href: document.href };
    }

    return {
        kind: 'file',
        key: document.id,
        filename: document.title ?? document.filename ?? '',
        mimeType: document.mimeType ?? '',
        typeLabel: document.extension?.toUpperCase() ?? null,
        sizeBytes: document.sizeBytes,
        src: document.href,
        downloadHref: document.downloadHref ?? document.href,
    };
}

export function libraryViewerEntries(sections: LibrarySection[]): ViewerEntry[] {
    return sections.flatMap((section) => section.documents.map(toEntry));
}
