// Indent for a tree row inside a native <select> (#848): an <option> takes no padding in every
// browser, so depth is shown with a hyphen per level.
export function treeIndent(depth: number): string {
    return '- '.repeat(Math.max(0, depth));
}
