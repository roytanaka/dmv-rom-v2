// In-page helpers for the help screenshot runner (PRD #516, ADR-0025, #521).
//
// These functions run inside the browser page, not in Node. The runner
// (`run.sh`) injects this file into the page with agent-browser's eval, then
// calls one function by name from a step script's `act` line.
//
// Why a helper library at all: agent-browser clicks do not open Radix (reka-ui)
// overlays — a menu or dialog stays shut. Anything that needs an open overlay is
// triggered here through the component's own keyboard contract instead. A step
// that only navigates does not come through here; it is a plain `nav` line.
//
// Everything hangs off `window.__help` so the runner has one stable entry point.
// Each function returns a boolean the runner can read back: true when it did its
// job, false when the target was not on the page (a script that drifted from the
// chrome). Keep them small and self-contained — this file is injected whole.

(function () {
    // Log in as a seeded Persona with a fetch, the way the runner asks for. Prime
    // the session so Laravel sets the XSRF-TOKEN cookie, then POST the credentials
    // with that token echoed back in the header VerifyCsrfToken reads. The 302 to
    // the dashboard is followed, so an ok response means we hold a session cookie.
    async function login(email, password) {
        await fetch('/login', { credentials: 'same-origin' });
        const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);
        const token = match ? decodeURIComponent(match[1]) : '';
        const response = await fetch('/login', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': token,
            },
            body: JSON.stringify({ email, password }),
        });
        return response.ok || response.redirected;
    }

    // Highlight the top-bar "?" so a shot can point at it without a drawn arrow.
    // A synthetic hover would not set CSS :hover (browsers trust real pointers
    // only), so we paint the highlighted state on directly. The link resolves to
    // an article or the index, so it always carries a /help or /aide href.
    function highlightHelpLink() {
        const link = document.querySelector('header a[href*="/help"], header a[href*="/aide"]');
        if (!link) return false;
        link.style.color = '#ffffff';
        link.style.outline = '2px solid rgba(255, 255, 255, 0.7)';
        link.style.outlineOffset = '2px';
        link.style.borderRadius = '4px';
        return true;
    }

    // Open the desktop locale switcher (the globe menu, shown at lg+). agent-browser
    // cannot click it open, so we drive its keyboard contract: focus the trigger and
    // press Enter, which reka-ui opens the menu on. The trigger is the only top-bar
    // menu whose label is the current locale (EN or FR).
    function openLanguageSwitcher() {
        const triggers = Array.from(document.querySelectorAll('[aria-haspopup="menu"]'));
        const trigger = triggers.find((element) => /^(EN|FR)\b/.test(element.textContent.trim().toUpperCase()));
        if (!trigger) return false;
        trigger.focus();
        trigger.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
        return true;
    }

    // Reveal the "New post" form on the News page. The form is a plain v-if toggle, not a
    // Radix overlay, but the step vocabulary is nav/act only, so a mid-page click comes
    // through a helper. The trigger is the header button whose label is the new-post text
    // (English chrome, the language the shots are taken in).
    function openNewsComposer() {
        const button = Array.from(document.querySelectorAll('button')).find((element) => /new post/i.test(element.textContent.trim()));
        if (!button) return false;
        button.click();
        return true;
    }

    window.__help = { login, highlightHelpLink, openLanguageSwitcher, openNewsComposer };
})();
