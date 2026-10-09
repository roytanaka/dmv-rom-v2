/**
 * The image viewer's entries for a Document library page (#779, spec #773): every file
 * Document in the list on screen, across its sections, in page order. A click on an image
 * Document opens the viewer at that Document's entry. The viewer shows only the PNG, JPEG,
 * WebP and GIF ones as images; previous/next (#781) steps through the whole list, and adds
 * link Documents here once the viewer has a card for them.
 *
 * Each entry loads through the gated, logged download route: `src` inline, `downloadHref`
 * as an attachment.
 */
import type { LibrarySection } from '../types/index.ts';
import type { ViewerEntry } from '../viewer/entries.ts';

export function libraryViewerEntries(sections: LibrarySection[]): ViewerEntry[] {
    return sections.flatMap((section) =>
        section.documents
            .filter((document) => document.kind === 'file' && document.downloadHref !== null)
            .map((document) => ({
                key: document.id,
                filename: document.title ?? document.filename ?? '',
                mimeType: document.mimeType ?? '',
                src: document.href,
                downloadHref: document.downloadHref ?? '',
            })),
    );
}
