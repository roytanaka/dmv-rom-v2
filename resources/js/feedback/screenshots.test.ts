// The send dialog's screenshot list (#678, ADR-0029 §9): what an upload, a drop, or a
// paste adds, and why a file is refused before sending.
import assert from 'node:assert/strict';
import test from 'node:test';
import { addScreenshots, fileErrorMessages, MAX_SCREENSHOT_BYTES } from './screenshots.ts';

const image = (name: string, type = 'image/png', size = 1000): File => new File([new Uint8Array(size)], name, { type });

test('adds PNG, JPEG, WebP, and GIF images', () => {
    const incoming = [image('a.png'), image('b.jpg', 'image/jpeg'), image('c.webp', 'image/webp')];

    const result = addScreenshots([], incoming, 'Pasted image');

    assert.deepEqual(result.files, incoming);
    assert.deepEqual(result.errors, []);
});

test('keeps the images already in the list', () => {
    const first = image('first.gif', 'image/gif');

    const result = addScreenshots([first], [image('second.png')], 'Pasted image');

    assert.deepEqual(
        result.files.map((file) => file.name),
        ['first.gif', 'second.png'],
    );
});

test('refuses a file that is not an allowed image', () => {
    const result = addScreenshots([], [image('notes.pdf', 'application/pdf'), image('photo.heic', 'image/heic')], 'Pasted image');

    assert.deepEqual(result.files, []);
    assert.deepEqual(result.errors, [
        { key: 'feedback.screenshots.error_type', name: 'notes.pdf' },
        { key: 'feedback.screenshots.error_type', name: 'photo.heic' },
    ]);
});

test('refuses an image over 5 MB', () => {
    const result = addScreenshots(
        [],
        [image('full-page.png', 'image/png', MAX_SCREENSHOT_BYTES + 1), image('ok.png', 'image/png', MAX_SCREENSHOT_BYTES)],
        'Pasted image',
    );

    assert.deepEqual(
        result.files.map((file) => file.name),
        ['ok.png'],
    );
    assert.deepEqual(result.errors, [{ key: 'feedback.screenshots.error_size', name: 'full-page.png' }]);
});

test('refuses every image past the third', () => {
    const result = addScreenshots([image('1.png'), image('2.png')], [image('3.png'), image('4.png'), image('5.png')], 'Pasted image');

    assert.deepEqual(
        result.files.map((file) => file.name),
        ['1.png', '2.png', '3.png'],
    );
    assert.deepEqual(result.errors, [
        { key: 'feedback.screenshots.error_limit', name: '4.png' },
        { key: 'feedback.screenshots.error_limit', name: '5.png' },
    ]);
});

test('refuses an image past the third with the limit key it is given', () => {
    const result = addScreenshots(
        [image('1.png'), image('2.png'), image('3.png')],
        [image('4.png')],
        'Pasted image',
        'feedback.comments.error_limit',
    );

    assert.deepEqual(result.errors, [{ key: 'feedback.comments.error_limit', name: '4.png' }]);
});

test('names a file with no name by the fallback', () => {
    const result = addScreenshots([], [image('', 'text/plain')], 'Pasted image');

    assert.deepEqual(result.errors, [{ key: 'feedback.screenshots.error_type', name: 'Pasted image' }]);
});

test('picks the server errors for one file field: the count and each file', () => {
    const errors = {
        body: 'Add a comment, an image, or both.',
        images: 'You can add up to 3 screenshots.',
        'images.3': 'notes.pdf was not added.',
        imagesExtra: 'not this one',
    };

    assert.deepEqual(fileErrorMessages(errors, 'images'), ['You can add up to 3 screenshots.', 'notes.pdf was not added.']);
});
