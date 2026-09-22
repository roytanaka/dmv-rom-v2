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

    // Open a Radix menu. agent-browser cannot click one open, so we drive its keyboard
    // contract: focus the trigger and press Enter, which reka-ui opens the menu on.
    function pressEnterOn(trigger) {
        if (!trigger) return false;
        trigger.focus();
        trigger.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
        return true;
    }

    // Open the desktop locale switcher (the globe menu, shown at lg+). The trigger is the
    // only top-bar menu whose label is the current locale (EN or FR).
    function openLanguageSwitcher() {
        const triggers = Array.from(document.querySelectorAll('[aria-haspopup="menu"]'));
        return pressEnterOn(triggers.find((element) => /^(EN|FR)\b/.test(element.textContent.trim().toUpperCase())));
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

    // The Group page's tab strip sticks under the top bar once the page scrolls, so an element
    // scrolled to `block: 'start'` lands under both. Back off by their height together (the
    // 64 px top bar plus the 68 px tab strip, measured at 1280 px), plus `lead` for any heading
    // the shot should keep above the element.
    const STICKY_HEADER_HEIGHT = 132;
    function scrollUnderStickyStrip(element, lead = 0) {
        element.scrollIntoView({ block: 'start' });
        window.scrollBy(0, -(STICKY_HEADER_HEIGHT + lead));
    }

    // Resolve true after a pause, so a scroll or an opening overlay settles before the shot.
    function settle(ms = 300) {
        return new Promise((resolve) => setTimeout(() => resolve(true), ms));
    }

    // The My sign-ups panel tops the Scheduling tab and holds ShiftCards of its own, so a
    // walkthrough that points at the page's own controls skips anything inside it.
    function outsideMySignUps(element) {
        return !element.closest('section[aria-label="My sign-ups"]');
    }

    // Resolve once `test` returns something truthy (or give up after five seconds), then settle
    // so the change paints. For a step that waits on the page: a new URL, the Email menu's
    // Audiences, the composer's recipients, or the Sent step after a send.
    function waitFor(test) {
        return new Promise((resolve) => {
            const started = Date.now();
            const poll = setInterval(() => {
                const found = test();
                if (found || Date.now() - started > 5000) {
                    clearInterval(poll);
                    setTimeout(() => resolve(Boolean(found)), 500);
                }
            }, 50);
        });
    }

    // Click an Inertia link and resolve once the URL has changed, so the runner shoots the
    // page the link opens, not the page it left.
    function followLink(link) {
        const from = location.pathname;
        link.click();
        return waitFor(() => location.pathname !== from);
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

    // Follow a schedule link, then scroll the opened Schedule's head into view, past the My
    // sign-ups panel that tops the page.
    async function openSchedule(link) {
        if (!link) return false;
        const moved = await followLink(link);
        if (!moved) return false;
        const back = Array.from(document.querySelectorAll('a')).find((element) => /all schedules/i.test(element.textContent.trim()));
        if (back) scrollUnderStickyStrip(back);
        return true;
    }

    // Open the current month's Schedule — the one whose name carries this month and year, as
    // the demo seed names it. Falls back to the first Schedule when no name matches. The month
    // Schedule is the one with seats still free and the Persona's own upcoming seat, so the
    // Sign up and Drop walkthroughs shoot here.
    function openCurrentMonthSchedule() {
        const now = new Date();
        const name = `${now.toLocaleString('en-CA', { month: 'long' })} ${now.getFullYear()}`;
        const links = scheduleLinks();
        return openSchedule(links.find((element) => element.textContent.trim() === name) ?? links[0]);
    }

    // Open the draft Schedule — the one whose list card carries the Draft badge. The demo seed
    // drafts next month on Docents, empty, the state a Scheduler is in right after creating it.
    // Only a schedule admin sees a draft, so this helper is for the officer walkthroughs.
    function openDraftSchedule() {
        const isDraft = (link) =>
            Array.from(link.closest('[data-slot="card"]')?.querySelectorAll('*') ?? []).some(
                (element) => element.children.length === 0 && element.textContent.trim() === 'Draft',
            );
        return openSchedule(scheduleLinks().find(isDraft));
    }

    // Open last month's Schedule — named for last month and year, as the demo seed names it.
    // Every tour on it was worked and signed out, so its seats carry filed visitor counts and
    // the correction walkthrough shoots here.
    function openLastMonthSchedule() {
        const now = new Date();
        const last = new Date(now.getFullYear(), now.getMonth() - 1, 1);
        const name = `${last.toLocaleString('en-CA', { month: 'long' })} ${last.getFullYear()}`;
        return openSchedule(scheduleLinks().find((element) => element.textContent.trim() === name));
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
        const button = pageButtonLabelled(pattern);
        if (!button) return false;
        scrollUnderStickyStrip(button.closest('[data-slot="card"]') ?? button);
        return settle();
    }

    // Click a button, then give an overlay or inline form time to open before the shot. Dialogs
    // here are controlled by a plain click handler, so a DOM click opens them; only a Radix
    // menu needs the keyboard contract (see pressEnterOn).
    function clickAndSettle(button) {
        if (!button) return false;
        button.click();
        return settle(500);
    }

    // Click, then wait for what the click opens rather than a fixed pause: a fetch or a send
    // decides how long it takes.
    function clickAndWaitFor(element, test) {
        if (!element) return false;
        element.click();
        return waitFor(test);
    }

    // Open a Radix menu and give it time to paint.
    function openMenuFrom(trigger) {
        if (!pressEnterOn(trigger)) return false;
        return settle(400);
    }

    // The items of the open Radix menu.
    function menuItems() {
        return Array.from(document.querySelectorAll('[role="menuitem"]'));
    }

    // The first button with this label outside the My sign-ups panel, so a walkthrough points at
    // the page's own controls, not the panel's copies.
    function pageButtonLabelled(pattern) {
        return buttonLabelled(pattern, outsideMySignUps);
    }

    // The Scheduling tab's list view, as a schedule admin: the New schedule button, then the
    // Shift reminders and Empty-desk alert cards below it.
    function showNewScheduleButton() {
        const button = pageButtonLabelled(/^new schedule$/i);
        if (!button) return false;
        scrollUnderStickyStrip(button, 24);
        return settle();
    }

    function openNewScheduleDialog() {
        return clickAndSettle(pageButtonLabelled(/^new schedule$/i));
    }

    // An opened Schedule's authoring controls.
    function openNewShiftDialog() {
        return clickAndSettle(pageButtonLabelled(/^new shift$/i));
    }

    function openBulkShiftsDialog() {
        return clickAndSettle(pageButtonLabelled(/^bulk shifts$/i));
    }

    // A settings card on the Scheduling tab's list view, found by its title. The Shift
    // reminders and Empty-desk alert cards show only to a schedule admin.
    function showCardTitled(pattern) {
        const card = Array.from(document.querySelectorAll('[data-slot="card"]')).find((element) => pattern.test(element.textContent.trim()));
        if (!card) return false;
        scrollUnderStickyStrip(card, 16);
        return settle();
    }

    function showShiftReminders() {
        return showCardTitled(/^shift reminders/i);
    }

    function showEmptyDeskAlert() {
        return showCardTitled(/^empty-desk alert/i);
    }

    // The first Agenda Shift still ahead that a schedule admin can place a Member on, scrolled
    // so its day heading stays in frame. Each Agenda day is a block led by an h3 such as
    // "Tuesday, September 8", read against today in the current year.
    function showUpcomingPlaceAMember() {
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const button = Array.from(document.querySelectorAll('button'))
            .filter((element) => /^place a member$/i.test(element.textContent.trim()) && outsideMySignUps(element))
            .find((element) => {
                const heading = element.closest('[data-slot="card"]')?.parentElement?.querySelector('h3');
                return heading && new Date(`${heading.textContent.trim()} ${today.getFullYear()}`) >= today;
            });
        if (!button) return false;
        const heading = button.closest('[data-slot="card"]').parentElement.querySelector('h3');
        scrollUnderStickyStrip(heading, 16);
        return settle();
    }

    // Open the picker from the Shift the previous helper framed: the first Place a member button
    // below the sticky header.
    function openPlaceAMemberDialog() {
        const button = Array.from(document.querySelectorAll('button')).find(
            (element) =>
                /^place a member$/i.test(element.textContent.trim()) &&
                outsideMySignUps(element) &&
                element.getBoundingClientRect().top > STICKY_HEADER_HEIGHT,
        );
        return clickAndSettle(button);
    }

    // The pencil a schedule admin sees on every seat of an opened Schedule, outside the My
    // sign-ups panel. It is an icon button, so it is found by its accessible label.
    function correctionPencil() {
        return Array.from(document.querySelectorAll('button[aria-label="Correct visitor count"]')).find(outsideMySignUps);
    }

    function showCorrectionPencil() {
        const pencil = correctionPencil();
        if (!pencil) return false;
        scrollUnderStickyStrip(pencil.closest('[data-slot="card"]') ?? pencil, 40);
        return settle();
    }

    function openCorrectionForm() {
        return clickAndSettle(correctionPencil());
    }

    // The roster's officer controls: Add member opens a dialog on a plain click; each row's
    // three-dot button is a Radix menu, opened through its keyboard contract.
    function openAddMemberDialog() {
        return clickAndSettle(pageButtonLabelled(/^add member$/i));
    }

    function openRosterRowMenu() {
        return openMenuFrom(document.querySelector('tbody [aria-haspopup="menu"]'));
    }

    // Open the first row's menu, then pick Manage, which opens the Manage membership dialog.
    async function openManageMembershipDialog() {
        if (!(await openRosterRowMenu())) return false;
        return clickAndSettle(menuItems().find((element) => /^manage$/i.test(element.textContent.trim())));
    }

    // The Meetings tab's New meeting control, for a Secretary or Chair.
    function openNewMeetingDialog() {
        return clickAndSettle(pageButtonLabelled(/^new meeting$/i));
    }

    // The first Shift the viewer can still take a seat on — the one whose card shows Sign up.
    function showFirstOpenShift() {
        return showShiftWithButton(/^sign up$/i);
    }

    // The first Shift the viewer holds a seat on — the one whose card shows Drop.
    function showMyShift() {
        return showShiftWithButton(/^drop$/i);
    }

    // The open dialog: the composer sheet, once the Email menu or the Message button opens it.
    function openDialog() {
        return document.querySelector('[role="dialog"]');
    }

    function dialogButtonLabelled(pattern) {
        return buttonLabelled(pattern, (element) => openDialog()?.contains(element));
    }

    // Open the "Email ▾" menu, a Radix menu, and wait for its Audiences to load: the menu
    // fetches them, with their counts, the first time it opens. Enter toggles the menu, so a
    // menu a previous step left open is left alone.
    function openEmailMenu() {
        const trigger = Array.from(document.querySelectorAll('[aria-haspopup="menu"]')).find((element) =>
            /^email$/i.test(element.textContent.trim()),
        );
        if (!trigger) return false;
        if (trigger.getAttribute('aria-expanded') !== 'true') pressEnterOn(trigger);
        return waitFor(() => menuItems().length);
    }

    // Open the menu, pick the Audience whose name matches, and wait for the composer's To field
    // to fill. The name is an item's first span; its count is the second.
    async function pickAudience(pattern) {
        if (!(await openEmailMenu())) return false;
        const item = menuItems().find((element) => pattern.test((element.querySelector('span') ?? element).textContent.trim()));
        return clickAndWaitFor(item, () => openDialog()?.querySelector('button[aria-label^="Remove "]'));
    }

    function openWholeGroupComposer() {
        return pickAudience(/^whole group$/i);
    }

    function openOfficersComposer() {
        return pickAudience(/officers$/i);
    }

    // "Pick people…" opens the composer on an empty To field with the roster panel open.
    async function openHandPickComposer() {
        if (!(await openEmailMenu())) return false;
        const item = menuItems().find((element) => /^pick people/i.test(element.textContent.trim()));
        return clickAndWaitFor(item, () => openDialog()?.querySelector('input'));
    }

    // Remove the first name from the To field, so the Audience name gains its "1 removed" note.
    function removeFirstRecipient() {
        return clickAndSettle(openDialog()?.querySelector('button[aria-label^="Remove "]'));
    }

    // The profile header's Message button opens the composer on its one fixed recipient.
    function openMessageComposer() {
        return clickAndWaitFor(buttonLabelled(/^message\b/i), () => dialogButtonLabelled(/^next$/i));
    }

    function goToMessageStep() {
        return clickAndWaitFor(dialogButtonLabelled(/^next$/i), () => document.getElementById('composer-subject'));
    }

    // Fill the Message step as a sender would. The subject is a v-model input and the body a
    // contenteditable that emits on `input`, so each gets its value and then the event.
    function writeSampleEmail() {
        const subject = document.getElementById('composer-subject');
        const body = openDialog()?.querySelector('[contenteditable="true"]');
        if (!subject || !body) return false;
        subject.value = 'Spring training dates';
        subject.dispatchEvent(new Event('input', { bubbles: true }));
        body.innerHTML =
            '<p>Hello,</p><p>The spring training sessions are now on the Schedule. Please sign up for one session by the end of the month.</p><p>Thank you!</p>';
        body.dispatchEvent(new Event('input', { bubbles: true }));
        return settle();
    }

    // Attach a stand-in PDF through the hidden file input, as the file picker would.
    function attachSampleFile() {
        const input = openDialog()?.querySelector('input[type="file"]');
        if (!input) return false;
        const transfer = new DataTransfer();
        transfer.items.add(new File([new Uint8Array(250 * 1024)], 'Spring training schedule.pdf', { type: 'application/pdf' }));
        input.files = transfer.files;
        input.dispatchEvent(new Event('change', { bubbles: true }));
        return settle();
    }

    // Send for real and wait for the Sent step. The local app queues the mail, so a shoot
    // leaves pending Deliveries until the queue drains.
    function sendEmail() {
        return clickAndWaitFor(dialogButtonLabelled(/^send$/i), () => dialogButtonLabelled(/^done$/i));
    }

    // The avatar menu in the top bar, which holds My Profile, Mail status, and Log out.
    function openAccountMenu() {
        return openMenuFrom(document.querySelector('button[aria-label="Account menu"]'));
    }

    // The Role-switcher's menu trigger: the detective button when idle, "Switch ▾" while active.
    function openRoleSwitcher() {
        return openMenuFrom(document.querySelector('button[aria-label="Impersonate (dev)"]') ?? buttonLabelled(/^switch\b/i));
    }

    // Become Amara Abara, the Persona the Member walkthroughs shoot as, and wait for the active
    // toolbar. The pick posts through Inertia, so the page reloads its props in place.
    async function becomeAmaraAbara() {
        if (!(await openRoleSwitcher())) return false;
        const item = menuItems().find((element) => /amara abara/i.test(element.textContent));
        return clickAndWaitFor(item, () => buttonLabelled(/^stop$/i));
    }

    // Stop impersonating, so the runner leaves the operator signed in as themself.
    function stopImpersonating() {
        return clickAndWaitFor(buttonLabelled(/^stop$/i), () => !buttonLabelled(/^stop$/i));
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
        openDraftSchedule,
        openLastMonthSchedule,
        showNewScheduleButton,
        openNewScheduleDialog,
        openNewShiftDialog,
        openBulkShiftsDialog,
        showShiftReminders,
        showEmptyDeskAlert,
        showUpcomingPlaceAMember,
        openPlaceAMemberDialog,
        showCorrectionPencil,
        openCorrectionForm,
        openAddMemberDialog,
        openRosterRowMenu,
        openManageMembershipDialog,
        openNewMeetingDialog,
        openEmailMenu,
        openWholeGroupComposer,
        openOfficersComposer,
        openHandPickComposer,
        removeFirstRecipient,
        openMessageComposer,
        goToMessageStep,
        writeSampleEmail,
        attachSampleFile,
        sendEmail,
        openAccountMenu,
        openRoleSwitcher,
        becomeAmaraAbara,
        stopImpersonating,
    };
})();
