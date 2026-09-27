// The Tester's remembered name (ADR-0029 §5), shared by the Send feedback dialog and the
// comment form (#677). Kept in localStorage. Every access is wrapped: a browser that
// blocks storage still sends, it just forgets the name.
//
// The storage is passed in (a function, so reading `window.localStorage` itself can
// throw inside the try), which keeps this testable outside a browser.

type StorageSource = () => Pick<Storage, 'getItem' | 'setItem'>;

const NAME_KEY = 'dmv.feedback.testerName';

const browserStorage: StorageSource = () => window.localStorage;

export function rememberedName(storage: StorageSource = browserStorage): string {
    try {
        return storage().getItem(NAME_KEY) ?? '';
    } catch {
        return '';
    }
}

export function rememberName(name: string, storage: StorageSource = browserStorage): void {
    try {
        storage().setItem(NAME_KEY, name);
    } catch {
        // Storage blocked: the send still worked, the name is just not kept.
    }
}
