// The section-then-parent picker (#850): the second select follows the first, and the first
// follows the form's parent, without a DOM or Inertia context.
import assert from 'node:assert/strict';
import test from 'node:test';
import { reactive } from 'vue';
import { treeIndent } from '../lib/treeIndent.ts';
import { type ParentOption, useParentPicker } from './useParentPicker.ts';

const OPTIONS: ParentOption[] = [
    { id: 2, name: 'Governance', depth: 0, sectionId: 2 },
    { id: 3, name: 'Programs', depth: 0, sectionId: 3 },
    { id: 4, name: 'Docents', depth: 1, sectionId: 3 },
    { id: 5, name: 'Class of 2026', depth: 2, sectionId: 3 },
    { id: 6, name: 'Special Projects', depth: 0, sectionId: 6 },
    { id: 7, name: 'Exhibit', depth: 1, sectionId: 6 },
];

test('the first select lists the sections, Containers and real Groups alike', () => {
    const { sections } = useParentPicker(() => OPTIONS, reactive({ parent_id: null }));

    assert.deepEqual(
        sections.value.map((option) => option.name),
        ['Governance', 'Programs', 'Special Projects'],
    );
});

test('with no parent chosen, no section is chosen and the second select is empty', () => {
    const { section, parents } = useParentPicker(() => OPTIONS, reactive({ parent_id: null }));

    assert.equal(section.value, null);
    assert.deepEqual(parents.value, []);
});

test('the form opens on the current section and parent', () => {
    const { section, parents } = useParentPicker(() => OPTIONS, reactive({ parent_id: 5 }));

    assert.equal(section.value, 3);
    assert.deepEqual(
        parents.value.map((option) => option.name),
        ['Programs', 'Docents', 'Class of 2026'],
    );
});

test('choosing a section resets the parent to it', () => {
    const form = reactive<{ parent_id: number | null }>({ parent_id: 5 });
    const { section, parents } = useParentPicker(() => OPTIONS, form);

    section.value = 6;

    assert.equal(form.parent_id, 6);
    assert.deepEqual(
        parents.value.map((option) => option.name),
        ['Special Projects', 'Exhibit'],
    );
});

test('choosing a parent inside the section keeps the section', () => {
    const form = reactive<{ parent_id: number | null }>({ parent_id: 3 });
    const { section } = useParentPicker(() => OPTIONS, form);

    form.parent_id = 4;

    assert.equal(section.value, 3);
});

test('a form reset puts the section back', () => {
    const form = reactive<{ parent_id: number | null }>({ parent_id: 7 });
    const { section } = useParentPicker(() => OPTIONS, form);

    form.parent_id = null;

    assert.equal(section.value, null);
});

test('rows indent with non-breaking spaces, the section flush', () => {
    assert.equal(treeIndent(0), '');
    assert.equal(treeIndent(2), ' '.repeat(6));
    assert.ok(!treeIndent(3).includes(' '));
});
