// The send dialog's screenshot list (#678, ADR-0029 §9). An upload, a drop, and a paste all
// add through `addScreenshots`, which refuses a file before sending for the same reasons
// the server does (`FeedbackScreenshotImage`, `StoreFeedbackItemRequest`). Errors are lang
// keys plus the file's name, so this module has no i18n dependency.

export const MAX_SCREENSHOTS = 3;
export const MAX_SCREENSHOT_BYTES = 5 * 1024 * 1024;
export const SCREENSHOT_TYPES = ['image/png', 'image/jpeg', 'image/webp', 'image/gif'];

export interface ScreenshotError {
    key: string;
    name: string;
}

export function addScreenshots(current: File[], incoming: File[], fallbackName: string): { files: File[]; errors: ScreenshotError[] } {
    const files = [...current];
    const errors: ScreenshotError[] = [];

    for (const file of incoming) {
        const name = file.name || fallbackName;

        if (!SCREENSHOT_TYPES.includes(file.type)) {
            errors.push({ key: 'feedback.screenshots.error_type', name });
        } else if (file.size > MAX_SCREENSHOT_BYTES) {
            errors.push({ key: 'feedback.screenshots.error_size', name });
        } else if (files.length >= MAX_SCREENSHOTS) {
            errors.push({ key: 'feedback.screenshots.error_limit', name });
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

// A file size as a lang key and number: whole KB below 1 MB, one decimal in MB from 1 MB.
export function sizeLabel(bytes: number): { key: string; size: string } {
    if (bytes >= 1024 * 1024) {
        return { key: 'feedback.screenshots.size_mb', size: (bytes / (1024 * 1024)).toFixed(1) };
    }

    return { key: 'feedback.screenshots.size_kb', size: String(Math.max(1, Math.round(bytes / 1024))) };
}
