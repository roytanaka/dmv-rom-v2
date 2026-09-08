import { readonly, ref } from 'vue';

// Thin wrapper around the platform Clipboard API. Exposes a `copy(text)` action,
// a transient `copied` flag that resets after `timeout`ms (for a "copied!"
// affordance), and an `isSupported` flag so callers can degrade gracefully where
// `navigator.clipboard` is unavailable (insecure contexts, older browsers). No
// dependency — this is the platform API only.
export function useClipboard(timeout = 1500) {
    const isSupported = typeof navigator !== 'undefined' && !!navigator.clipboard;
    const copied = ref(false);
    let timer: ReturnType<typeof setTimeout> | undefined;

    async function copy(text: string): Promise<boolean> {
        if (!isSupported) return false;

        try {
            await navigator.clipboard.writeText(text);
            copied.value = true;
            clearTimeout(timer);
            timer = setTimeout(() => (copied.value = false), timeout);
            return true;
        } catch {
            // Permission denied or write failed — leave `copied` false so the UI
            // shows no false confirmation.
            return false;
        }
    }

    return { copy, copied: readonly(copied), isSupported };
}
