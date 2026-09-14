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
    // The XSRF-TOKEN cookie Laravel sets, echoed back in the header VerifyCsrfToken reads.
    function csrfHeaders() {
        const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);
        return {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': match ? decodeURIComponent(match[1]) : '',
        };
    }

    // Log in as a seeded Persona with a fetch, the way the runner asks for. The browser
    // daemon keeps its cookies between articles, and the login form is guest-only, so a
    // Persona left signed in by the previous article is signed out first — otherwise
    // every article after the first would silently shoot as that Persona. Prime the
    // session so Laravel sets the XSRF-TOKEN cookie (logout regenerates it), then POST
    // the credentials. The 302 to the dashboard is followed, so an ok response means we
    // hold a session cookie.
    async function login(email, password) {
        await fetch('/login', { credentials: 'same-origin' });
        await fetch('/logout', { method: 'POST', credentials: 'same-origin', headers: csrfHeaders() });
        await fetch('/login', { credentials: 'same-origin' });
        const response = await fetch('/login', {
            method: 'POST',
            credentials: 'same-origin',
            headers: csrfHeaders(),
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

    // Open the first Schedule's agenda from the Scheduling tab's list. The seeded
    // Schedule's id is not stable across seeds, so the script cannot `nav` to its
    // permalink; it clicks the first schedule link instead. Each list item is a
    // TextLink to `/groups/<slug>/scheduling/<id>`, so the permalink is the only
    // anchor whose href carries a segment after `/scheduling/`. The Inertia click
    // navigates to the agenda.
    function openFirstSchedule() {
        const link = Array.from(document.querySelectorAll('a[href*="/scheduling/"]')).find((element) =>
            /\/scheduling\/[^/]+$/.test(new URL(element.href).pathname),
        );
        if (!link) return false;
        link.click();
        return true;
    }

    // From the Directory, open the profile of the first Member in the roster who is
    // not the signed-in Persona. Seeded ids are not stable across reseeds, so a
    // script cannot `nav` to a peer's permalink; the roster's own name links are.
    // The signed-in Member's id comes from the Inertia page props, read through the
    // mounted Vue app's `$page` (Inertia clears the `data-page` attribute once it
    // boots). The Inertia click navigates, so this resolves once the URL has changed
    // (or gives up after five seconds) so the runner shoots the profile, not the roster.
    function openAnotherMembersProfile() {
        const page = document.getElementById('app')?.__vue_app__?.config.globalProperties.$page;
        const self = String(page?.props?.auth?.user?.id ?? '');
        const link = Array.from(document.querySelectorAll('a[href*="/members/"]')).find(
            (element) => new URL(element.href).pathname.split('/').pop() !== self,
        );
        if (!link) return false;
        const from = location.pathname;
        link.click();
        return new Promise((resolve) => {
            const started = Date.now();
            const poll = setInterval(() => {
                const moved = location.pathname !== from;
                if (moved || Date.now() - started > 5000) {
                    clearInterval(poll);
                    setTimeout(() => resolve(moved), 500);
                }
            }, 50);
        });
    }

    window.__help = {
        login,
        highlightHelpLink,
        openLanguageSwitcher,
        openNewsComposer,
        openFirstSchedule,
        openAnotherMembersProfile,
    };
})();
