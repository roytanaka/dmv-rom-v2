// The Tester's remembered name (ADR-0029 §5), shared by the Send feedback dialog and the
// comment form (#677), and their remembered Follower email (#857, §15), the same way.
// Kept in localStorage. Every access is wrapped: a browser that
// blocks storage still sends, it just forgets the name.
//
// The storage is passed in (a function, so reading `window.localStorage` itself can
// throw inside the try), which keeps this testable outside a browser.

type StorageSource = () => Pick<Storage, 'getItem' | 'setItem'>;

const NAME_KEY = 'dmv.feedback.testerName';
const EMAIL_KEY = 'dmv.feedback.followerEmail';

const browserStorage: StorageSource = () => window.localStorage;

function remembered(key: string, storage: StorageSource): string {
    try {
        return storage().getItem(key) ?? '';
    } catch {
        return '';
    }
}

function remember(key: string, value: string, storage: StorageSource): void {
    try {
        storage().setItem(key, value);
    } catch {
        // Storage blocked: the send still worked, the value is just not kept.
    }
}

export function rememberedName(storage: StorageSource = browserStorage): string {
    return remembered(NAME_KEY, storage);
}

export function rememberName(name: string, storage: StorageSource = browserStorage): void {
    remember(NAME_KEY, name, storage);
}

export function rememberedEmail(storage: StorageSource = browserStorage): string {
    return remembered(EMAIL_KEY, storage);
}

export function rememberEmail(email: string, storage: StorageSource = browserStorage): void {
    remember(EMAIL_KEY, email, storage);
}
