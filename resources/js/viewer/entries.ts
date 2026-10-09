// What the image viewer (#776, spec #773) shows. A page hands the viewer a list of entries
// and the index to open on. The Feedback item page passes its screenshots; comment images
// (#778), image Documents (#779), and previous/next (#781) reuse the same shape.

export interface ViewerEntry {
    // Unique within the list, for Vue's `key`.
    key: string | number;
    filename: string;
    mimeType: string;
    // Loads the file inline. Every such route checks the policy and sends `nosniff`.
    src: string;
    // Saves the original file as an attachment, with its original filename.
    downloadHref: string;
}

// The image types every browser displays. Never an SVG, which can run script on this
// origin. The server keeps the same list (App\Rules\FeedbackScreenshotImage::ALLOWED_MIMES).
export const VIEWABLE_IMAGE_TYPES: readonly string[] = ['image/png', 'image/jpeg', 'image/webp', 'image/gif'];

export function isViewableImage(mimeType: string): boolean {
    return VIEWABLE_IMAGE_TYPES.includes(mimeType);
}
