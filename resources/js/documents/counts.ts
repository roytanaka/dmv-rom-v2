import { transChoice } from 'laravel-vue-i18n';

/**
 * What a Folder or a Document category section holds, as one line ("7 categories · 13
 * folders", "2 folders · 3 files") (#724, #726, spec #721). Each part only when there is some;
 * files also when there is nothing at all, so an empty one reads "0 files". A section has no
 * categories part.
 */
export function describeCounts({ categories = 0, folders, files }: { categories?: number; folders: number; files: number }): string {
    const part = (key: string, count: number) => transChoice(`document_categories.count.${key}`, count, { count: String(count) });

    return [
        categories > 0 ? part('categories', categories) : null,
        folders > 0 ? part('folders', folders) : null,
        files > 0 || categories + folders === 0 ? part('files', files) : null,
    ]
        .filter(Boolean)
        .join(' · ');
}
