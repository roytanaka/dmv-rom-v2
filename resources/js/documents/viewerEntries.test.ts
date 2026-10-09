/**
 * Unit tests for the Document library's viewer entries (#779, spec #773). Node's built-in
 * runner (`pnpm test:unit`).
 */
import assert from 'node:assert/strict';
import test from 'node:test';
import type { LibraryDocument, LibrarySection } from '../types/index.ts';
import { libraryViewerEntries } from './viewerEntries.ts';

function doc(overrides: Partial<LibraryDocument>): LibraryDocument {
    return {
        id: 1,
        kind: 'file',
        folderId: null,
        categoryId: null,
        url: null,
        title: null,
        description: null,
        filename: 'file.png',
        extension: 'png',
        mimeType: 'image/png',
        sizeBytes: 10,
        updatedAt: '2026-10-01T00:00:00Z',
        uploader: null,
        uploadedAt: null,
        href: '/documents/1/download',
        downloadHref: '/documents/1/download?download=1',
        ...overrides,
    };
}

function section(documents: LibraryDocument[]): LibrarySection {
    return { category: null, folders: [], documents, folderCount: 0, documentCount: documents.length };
}

test('lists the file Documents of every section, in page order', () => {
    const entries = libraryViewerEntries([
        section([doc({ id: 3, filename: 'b.jpg', mimeType: 'image/jpeg', href: '/d/3', downloadHref: '/d/3?download=1' })]),
        section([doc({ id: 7, filename: 'a.pdf', mimeType: 'application/pdf', href: '/d/7', downloadHref: '/d/7?download=1' })]),
    ]);

    assert.deepEqual(entries, [
        { key: 3, filename: 'b.jpg', mimeType: 'image/jpeg', src: '/d/3', downloadHref: '/d/3?download=1' },
        { key: 7, filename: 'a.pdf', mimeType: 'application/pdf', src: '/d/7', downloadHref: '/d/7?download=1' },
    ]);
});

test('names an entry by its title when it has one', () => {
    const [entry] = libraryViewerEntries([section([doc({ title: 'Gallery map', filename: 'map.png' })])]);

    assert.equal(entry.filename, 'Gallery map');
});

test('leaves out link Documents, which have nothing to show or save', () => {
    const entries = libraryViewerEntries([
        section([doc({ id: 1 }), doc({ id: 2, kind: 'link', filename: null, mimeType: null, title: 'ROM', downloadHref: null })]),
    ]);

    assert.deepEqual(
        entries.map((entry) => entry.key),
        [1],
    );
});
