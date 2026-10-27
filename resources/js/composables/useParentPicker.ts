import { computed } from 'vue';

// One row of the parent picker, as DmvSettingsController sends it: tree order, the root left out.
export interface ParentOption {
    id: number;
    name: string;
    // Below the section: the section itself is 0.
    depth: number;
    // The root's child whose subtree the row is in; a section names itself.
    sectionId: number;
    // The structure form only: the Group's current parent, archived or ended, kept but not offered.
    inactive?: boolean;
}

/**
 * The section-then-parent picker (#850): two selects over one list. The first lists the
 * sections (the root's active children, Containers and real Groups alike); the second the
 * chosen section's subtree, the section first. The section is read off the form's parent and
 * written back as the parent, so choosing a section resets the parent to it, and a form reset
 * puts both back. Filtered here from the list the page already has; no request.
 */
export function useParentPicker(options: () => ParentOption[], form: { parent_id: number | null }) {
    const sections = computed(() => options().filter((option) => option.id === option.sectionId));
    const section = computed<number | null>({
        get: () => options().find((option) => option.id === form.parent_id)?.sectionId ?? null,
        set: (id) => {
            form.parent_id = id;
        },
    });
    const parents = computed(() => (section.value === null ? [] : options().filter((option) => option.sectionId === section.value)));

    return { sections, section, parents };
}
