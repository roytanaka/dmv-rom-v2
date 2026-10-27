// Indent for a tree row inside a native <select> (#848, #850): an <option> takes no padding in
// any browser and collapses plain spaces, so depth is shown with non-breaking spaces per level.
// Depth counts from the section (a child of the root), which sits at 0.
export function treeIndent(depth: number): string {
    return '   '.repeat(Math.max(0, depth));
}
