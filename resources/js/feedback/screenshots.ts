// The send dialog's screenshot list (#678, ADR-0029 §9). An upload, a drop, and a paste all
// add through `addScreenshots`, which refuses a file before sending for the same reasons
// the server does (`FeedbackScreenshotImage`, `StoreFeedbackItemRequest`). Errors are lang
// keys plus the file's name, so this module has no i18n dependency. A screenshot may be
// only an image type the viewer shows inline (`VIEWABLE_IMAGE_TYPES`), as on the server.
import { VIEWABLE_IMAGE_TYPES } from '../viewer/entries.ts';

export const MAX_SCREENSHOTS = 3;
export const MAX_SCREENSHOT_BYTES = 5 * 1024 * 1024;

export interface ScreenshotError {
    key: string;
    name: string;
}

// `limitKey` names the error for a file past the limit: the send dialog says "screenshots",
// a comment's form says "images".
export function addScreenshots(
    current: File[],
    incoming: File[],
    fallbackName: string,
    limitKey = 'feedback.screenshots.error_limit',
): { files: File[]; errors: ScreenshotError[] } {
    const files = [...current];
    const errors: ScreenshotError[] = [];

    for (const file of incoming) {
        const name = file.name || fallbackName;

        if (!VIEWABLE_IMAGE_TYPES.includes(file.type)) {
            errors.push({ key: 'feedback.screenshots.error_type', name });
        } else if (file.size > MAX_SCREENSHOT_BYTES) {
            errors.push({ key: 'feedback.screenshots.error_size', name });
        } else if (files.length >= MAX_SCREENSHOTS) {
            errors.push({ key: limitKey, name });
        } else {
            files.push(file);
        }
    }

    return { files, errors };
}

// The server's errors for one file field, in order: `field` for the count, `field.N` for
// each file. The send dialog's field is `screenshots`; a comment's is `images` (#778).
export function fileErrorMessages(errors: Record<string, string>, field: string): string[] {
    return Object.entries(errors)
        .filter(([key]) => key === field || key.startsWith(`${field}.`))
        .map(([, message]) => message);
}
