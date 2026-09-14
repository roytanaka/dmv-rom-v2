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

    // The Group page's tab strip sticks to the top of the viewport once the page scrolls, so
    // an element scrolled to `block: 'start'` lands under it. Back off by the strip's height,
    // plus `lead` for any heading the shot should keep above the element.
    const STICKY_STRIP_HEIGHT = 80;
    function scrollUnderStickyStrip(element, lead = 0) {
        element.scrollIntoView({ block: 'start' });
        window.scrollBy(0, -(STICKY_STRIP_HEIGHT + lead));
    }

    // Click an Inertia link and resolve once the URL has changed (or give up after five
    // seconds), so the runner shoots the page the link opens, not the page it left. A
    // short settle after the change lets the new page paint.
    function followLink(link) {
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

    // The Scheduling tab's schedule links. A seeded Schedule's id is not stable across
    // seeds, so a script cannot `nav` to its permalink; it picks a link instead. Each
    // list item is a TextLink to `/groups/<slug>/scheduling/<id>`, so a permalink is an
    // anchor whose href carries a segment after `/scheduling/`.
    function scheduleLinks() {
        return Array.from(document.querySelectorAll('a[href*="/scheduling/"]')).filter((element) =>
            /\/scheduling\/[^/]+$/.test(new URL(element.href).pathname),
        );
    }

    // Open the first Schedule's agenda from the Scheduling tab's list.
    function openFirstSchedule() {
        const link = scheduleLinks()[0];
        if (!link) return false;
        return followLink(link);
    }

    // Open the current month's Schedule — the one whose name carries this month and year, as
    // the demo seed names it — and scroll its head into view, past the My sign-ups panel that
    // tops the page. Falls back to the first Schedule when no name matches. The month
    // Schedule is the one with seats still free and the Persona's own upcoming seat, so the
    // Sign up and Drop walkthroughs shoot here.
    async function openCurrentMonthSchedule() {
        const now = new Date();
        const name = `${now.toLocaleString('en-CA', { month: 'long' })} ${now.getFullYear()}`;
        const links = scheduleLinks();
        const link = links.find((element) => element.textContent.trim() === name) ?? links[0];
        if (!link) return false;
        const moved = await followLink(link);
        if (!moved) return false;
        const back = Array.from(document.querySelectorAll('a')).find((element) => /all schedules/i.test(element.textContent.trim()));
        if (back) scrollUnderStickyStrip(back);
        return true;
    }

    // Scroll the Scheduling tab's list of schedules into view, below the My sign-ups panel.
    // The first schedule card lands under the sticky tab strip with its heading above it.
    function showScheduleList() {
        const link = scheduleLinks()[0];
        if (!link) return false;
        scrollUnderStickyStrip(link.closest('[data-slot="card"]') ?? link, 40);
        return true;
    }

    // From the Directory, open the profile of the first Member in the roster who is
    // not the signed-in Persona. Seeded ids are not stable across reseeds, so a
    // script cannot `nav` to a peer's permalink; the roster's own name links are.
    // The signed-in Member's id comes from the Inertia page props, read through the
    // mounted Vue app's `$page` (Inertia clears the `data-page` attribute once it
    // boots). The click is followed, so the runner shoots the profile, not the roster.
    function openAnotherMembersProfile() {
        const page = document.getElementById('app')?.__vue_app__?.config.globalProperties.$page;
        const self = String(page?.props?.auth?.user?.id ?? '');
        const link = Array.from(document.querySelectorAll('a[href*="/members/"]')).find(
            (element) => new URL(element.href).pathname.split('/').pop() !== self,
        );
        if (!link) return false;
        return followLink(link);
    }

    // The first button whose label matches, English chrome. `within` narrows the search to
    // elements passing a predicate (say, outside a panel).
    function buttonLabelled(pattern, within = () => true) {
        return Array.from(document.querySelectorAll('button')).find((element) => pattern.test(element.textContent.trim()) && within(element));
    }

    // Expand the rail's Browse Groups section. It is a reka-ui Collapsible, which toggles on
    // a plain click (unlike a menu, which needs the keyboard contract), but the step
    // vocabulary is nav/act only, so the click comes through a helper.
    function openBrowseGroups() {
        const trigger = buttonLabelled(/browse groups/i);
        if (!trigger) return false;
        if (trigger.getAttribute('aria-expanded') !== 'true') trigger.click();
        return true;
    }

    // Scroll the first Agenda Shift card carrying the given button label under the sticky
    // strip, so the shot shows that card and its control rather than the page head. The
    // My sign-ups panel above the Agenda holds ShiftCards too, so its buttons are skipped:
    // the walkthrough points at the Agenda. Resolves after the scroll settles.
    function showShiftWithButton(pattern) {
        const button = buttonLabelled(pattern, (element) => !element.closest('section[aria-label="My sign-ups"]'));
        if (!button) return false;
        scrollUnderStickyStrip(button.closest('[data-slot="card"]') ?? button);
        return new Promise((resolve) => setTimeout(() => resolve(true), 300));
    }

    // The first Shift the viewer can still take a seat on — the one whose card shows Sign up.
    function showFirstOpenShift() {
        return showShiftWithButton(/^sign up$/i);
    }

    // The first Shift the viewer holds a seat on — the one whose card shows Drop.
    function showMyShift() {
        return showShiftWithButton(/^drop$/i);
    }

    window.__help = {
        login,
        highlightHelpLink,
        openLanguageSwitcher,
        openNewsComposer,
        openFirstSchedule,
        openCurrentMonthSchedule,
        showScheduleList,
        openAnotherMembersProfile,
        openBrowseGroups,
        showFirstOpenShift,
        showMyShift,
    };
})();
