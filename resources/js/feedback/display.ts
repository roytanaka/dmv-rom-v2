// Display helpers shared by the Feedback page and the item page (#676, #677, ADR-0029).
import { type BadgeVariants } from '@/components/ui/badge';
import { sizeLabel } from '@/feedback/screenshots';
import { trans } from 'laravel-vue-i18n';

// A Feedback type or status as the server lists it for a picker (#679): the stored value
// and its label's lang key.
export interface FeedbackOption {
    value: string;
    labelKey: string;
}

// Status tones (ADR-0029 design): New info, Confirmed warning, Fixed success, Won't fix
// secondary, Duplicate outline.
export const STATUS_TONES: Record<string, BadgeVariants['variant']> = {
    new: 'info',
    confirmed: 'warning',
    fixed: 'success',
    'wont-fix': 'secondary',
    duplicate: 'outline',
};

// Sent dates read in the org timezone, like every other instant in the app.
export const formatFeedbackDate = (iso: string, locale: string, timeZone: string): string =>
    new Intl.DateTimeFormat(locale, { dateStyle: 'medium', timeStyle: 'short', timeZone }).format(new Date(iso));

// A screenshot's size in the page's language (#678).
export function screenshotSize(bytes: number): string {
    const { key, size } = sizeLabel(bytes);

    return trans(key, { size });
}
