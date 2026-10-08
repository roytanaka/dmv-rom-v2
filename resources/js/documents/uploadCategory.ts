/**
 * The upload's starting Document category (#728, spec #721, ADR-0030 §4). One Document
 * category covers the whole upload batch; it starts at the active Category filter when that
 * names one of the open Folder's Document categories, else at "No category" (null). The
 * filter id comes from `?category=` as asked, so it may name another Folder's or none.
 */
export function defaultUploadCategory(categories: { id: number }[], filter: number | null): number | null {
    return filter !== null && categories.some((category) => category.id === filter) ? filter : null;
}
