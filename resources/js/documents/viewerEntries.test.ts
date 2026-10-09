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

test('lists every file Document of every section, in page order, with its type and size', () => {
    const entries = libraryViewerEntries([
        section([
            doc({
                id: 3,
                filename: 'b.jpg',
                extension: 'jpg',
                mimeType: 'image/jpeg',
                sizeBytes: 2048,
                href: '/d/3',
                downloadHref: '/d/3?download=1',
            }),
        ]),
        section([
            doc({
                id: 7,
                filename: 'a.pdf',
                extension: 'pdf',
                mimeType: 'application/pdf',
                sizeBytes: 99,
                href: '/d/7',
                downloadHref: '/d/7?download=1',
            }),
        ]),
    ]);

    assert.deepEqual(entries, [
        {
            kind: 'file',
            key: 3,
            filename: 'b.jpg',
            mimeType: 'image/jpeg',
            typeLabel: 'JPG',
            sizeBytes: 2048,
            src: '/d/3',
            downloadHref: '/d/3?download=1',
        },
        {
            kind: 'file',
            key: 7,
            filename: 'a.pdf',
            mimeType: 'application/pdf',
            typeLabel: 'PDF',
            sizeBytes: 99,
            src: '/d/7',
            downloadHref: '/d/7?download=1',
        },
    ]);
});

test('names an entry by its title when it has one', () => {
    const [entry] = libraryViewerEntries([section([doc({ title: 'Gallery map', filename: 'map.png' })])]);

    assert.equal(entry.kind === 'file' && entry.filename, 'Gallery map');
});

test('keeps a link Document in its place, as a link card with its title, address and gated open route', () => {
    const entries = libraryViewerEntries([
        section([
            doc({ id: 1 }),
            doc({
                id: 2,
                kind: 'link',
                filename: null,
                extension: null,
                mimeType: null,
                sizeBytes: null,
                title: 'ROM',
                url: 'https://www.rom.on.ca',
                href: '/documents/2/download',
                downloadHref: null,
            }),
            doc({ id: 3 }),
        ]),
    ]);

    assert.deepEqual(
        entries.map((entry) => entry.key),
        [1, 2, 3],
    );
    assert.deepEqual(entries[1], { kind: 'link', key: 2, title: 'ROM', address: 'https://www.rom.on.ca', href: '/documents/2/download' });
});
