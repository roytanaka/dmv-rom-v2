// What the image viewer (#776, spec #773) shows. A page hands the viewer a list of entries
// and the index to open on, and previous/next (#781) steps through the list. The Feedback
// item page passes its screenshots and comment images; a Document library passes every
// Document on screen, files and links.

// A stored file. A PNG, JPEG, WebP or GIF shows as the image; anything else shows a file card.
export interface ViewerFileEntry {
    kind: 'file';
    // Unique within the list, for Vue's `key`.
    key: string | number;
    filename: string;
    mimeType: string;
    // The file type for the file card, for example "PDF"; null when unknown.
    typeLabel: string | null;
    sizeBytes: number | null;
    // Loads the file inline. Every such route checks the policy and sends `nosniff`.
    src: string;
    // Saves the original file as an attachment, with its original filename.
    downloadHref: string;
}

// A link Document: a card with its title, its address, and an Open button. Nothing to save.
export interface ViewerLinkEntry {
    kind: 'link';
    key: string | number;
    title: string;
    // The web address, shown as text.
    address: string;
    // Opens the link through the gated, logged route.
    href: string;
}

export type ViewerEntry = ViewerFileEntry | ViewerLinkEntry;

// The image types every browser displays. Never an SVG, which can run script on this
// origin. The server keeps the same list (App\Support\FileResponse::INLINE_IMAGE_MIMES). A
// Feedback screenshot or comment image may be only one of these.
export const VIEWABLE_IMAGE_TYPES: readonly string[] = ['image/png', 'image/jpeg', 'image/webp', 'image/gif'];

export function isViewableImage(mimeType: string): boolean {
    return VIEWABLE_IMAGE_TYPES.includes(mimeType);
}

// Previous (-1) or next (+1) from `index` in a set of `length`, stopping at either end.
export function stepIndex(index: number, delta: number, length: number): number {
    return Math.min(Math.max(index + delta, 0), Math.max(length - 1, 0));
}
