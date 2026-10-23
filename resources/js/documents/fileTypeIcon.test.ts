/**
 * Unit tests for the file-type icon helper (#777, spec #773). Node's built-in runner
 * (`pnpm test:unit`); prior art: `resources/js/directory/groupColumns.test.ts`.
 */
import {
    PhFile,
    PhFileAudio,
    PhFileCsv,
    PhFileDoc,
    PhFileImage,
    PhFilePdf,
    PhFilePpt,
    PhFileSvg,
    PhFileTxt,
    PhFileVideo,
    PhFileXls,
    PhFileZip,
    PhLink,
} from '@phosphor-icons/vue';
import assert from 'node:assert/strict';
import test from 'node:test';
import { fileTypeIcon } from './fileTypeIcon.ts';

const file = (mimeType: string | null) => fileTypeIcon({ kind: 'file', mimeType });

test('a PDF gets the PDF icon', () => {
    assert.equal(file('application/pdf'), PhFilePdf);
});

test('an archive gets the zip icon', () => {
    assert.equal(file('application/zip'), PhFileZip);
    assert.equal(file('application/x-zip-compressed'), PhFileZip);
    assert.equal(file('application/x-7z-compressed'), PhFileZip);
    assert.equal(file('application/gzip'), PhFileZip);
});

test('a word-processing file gets the document icon', () => {
    assert.equal(file('application/msword'), PhFileDoc);
    assert.equal(file('application/vnd.openxmlformats-officedocument.wordprocessingml.document'), PhFileDoc);
    assert.equal(file('application/vnd.oasis.opendocument.text'), PhFileDoc);
});

test('a spreadsheet gets the spreadsheet icon', () => {
    assert.equal(file('application/vnd.ms-excel'), PhFileXls);
    assert.equal(file('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'), PhFileXls);
    assert.equal(file('application/vnd.oasis.opendocument.spreadsheet'), PhFileXls);
});

test('a slide deck gets the presentation icon', () => {
    assert.equal(file('application/vnd.ms-powerpoint'), PhFilePpt);
    assert.equal(file('application/vnd.openxmlformats-officedocument.presentationml.presentation'), PhFilePpt);
    assert.equal(file('application/vnd.oasis.opendocument.presentation'), PhFilePpt);
});

test('a CSV gets the CSV icon and plain text the text icon', () => {
    assert.equal(file('text/csv'), PhFileCsv);
    assert.equal(file('text/plain'), PhFileTxt);
});

test('an SVG gets the SVG icon, not the image icon', () => {
    assert.equal(file('image/svg+xml'), PhFileSvg);
});

test('other images, audio and video get their family icon', () => {
    assert.equal(file('image/png'), PhFileImage);
    assert.equal(file('image/jpeg'), PhFileImage);
    assert.equal(file('audio/mpeg'), PhFileAudio);
    assert.equal(file('video/mp4'), PhFileVideo);
});

test('a MIME type is matched whatever its case or parameters', () => {
    assert.equal(file('Application/PDF'), PhFilePdf);
    assert.equal(file('text/plain; charset=utf-8'), PhFileTxt);
});

test('anything else, or no MIME type, falls back to the plain file icon', () => {
    assert.equal(file('application/octet-stream'), PhFile);
    assert.equal(file('application/json'), PhFile);
    assert.equal(file(null), PhFile);
});

test('a link Document gets the link icon', () => {
    assert.equal(fileTypeIcon({ kind: 'link', mimeType: null }), PhLink);
});
