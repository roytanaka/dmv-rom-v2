---
status: accepted
date: 2026-10-08
accepted: 2026-10-08
---

# Phone type and tap sizes

Raised on [#742](https://github.com/roytanaka/dmv-rom-v2/issues/742), from the mobile audit ([#738](https://github.com/roytanaka/dmv-rom-v2/issues/738), `docs/research/mobile-qc-audit.md` § I and § J), under [#737](https://github.com/roytanaka/dmv-rom-v2/issues/737). It **keeps [ADR-0014](0014-design-token-decisions.md)**: the rem scale, the 16px floor, and the `text-xs` exception for passive labels. It changes the body size on phones only.

## Context

The type scale was sized up for the DMV's older volunteers. Body text (`--text-base`) is 18px on every screen. The heading steps (`--text-2xl` to `--text-5xl`) already shrink on small screens through `clamp()`.

The audit measured every text element on every page at 390×844:

| Size | Share |
|---|---|
| 13px (`text-xs`) | 16% |
| 16px (`text-sm`) | 65% |
| 18px (`text-base`) | 16% |
| 20px and up | 2% |

Some pages are mostly 13px: Directory 49%, Group meeting hours 43%, Group Documents 34%, Group Members 29%. Most of that 13px text is content, not the passive labels that ADR-0014 allows.

The audit also found small tap targets. 114 of 177 `Button` uses take `size="sm"`, which is 36px tall. The default size is 40px. Checkboxes and switches are about 20px. The audit used 44×44px as the target: that is WCAG 2.2 Target Size (Enhanced, AAA) and Apple's guideline. All buttons pass the WCAG 2.2 AA minimum of 24px. Some checkboxes and switches do not.

The 18px body is a trade on a phone. The app is dense with information: rosters, schedules, hours, documents. On a 390px screen, 18px text shows fewer rows and wraps more. We expect the people who use the app on a phone to be younger than the volunteer base as a whole. The research on #742 argued against a smaller size: CSS pixels already allow for viewing distance, and presbyopia makes close-up text harder to read. We accept that cost for more information on each screen. Users who need larger text can zoom or set a larger browser font size, and the rem scale follows that setting.

## Decision

**Body text is 16px on phones. Nothing a person reads or taps is under 16px. Touch screens get 44px tap targets.**

1. **Body is 16px below `sm`.** Below 640px, `--text-base` is 1rem (16px). At 640px and up it stays 1.125rem (18px). The change is one override of the token on `:root` in a width media query. It is a clean step, not a `clamp()`, because body text at in-between sizes such as 17.2px reads worse than a whole size.
2. **Only `text-base` changes.** `text-sm` and `text-base` are both 16px on a phone. `text-lg` (20px) and `text-xl` (23px) do not change. The heading steps stay fluid as before.
3. **The floor is 16px.** Text a person reads or acts on is never under 16px, on any screen. This restates ADR-0014 §2. `docs/conventions.md` § Styling still says "15px floor" in the Table entry. That line is wrong.
4. **`text-xs` is for passive labels only.** The 13px step stays. Use it only for table column heads, eyebrows and timestamps. Other uses change to `text-sm`. The pages the audit names go first: Directory, Group meeting hours, Group Documents, Group Members.
5. **Form controls stay at 16px or more.** Safari on iOS zooms the page when an input with text under 16px gets focus. `Input` and `Textarea` use `text-base`, so on a phone they become 16px. That is the lowest size allowed. Never set a form control's text under `text-base`.
6. **Touch screens get 44px tap targets.** The `Button` `sm`, `default` and `icon` sizes become 44px under Tailwind's `pointer-coarse:` variant (for example `h-9 pointer-coarse:h-11`). This variant targets touch input, not screen width. Phones and tablets get the larger targets, and desktops keep the current sizes. Checkboxes and switches get a 44px touch area the same way.
7. **User preference is rem plus zoom.** The scale stays in rem, so it follows the browser font-size setting. Pinch zoom stays on. We do not opt in to iOS Dynamic Type with the `-apple-system-body` font. It works only in Safari, it changes the font family, and it overrides our scale. The in-app "larger text" control from ADR-0014 stays out of scope until a need is shown.

## Consequences

- A phone shows more rows and less wrapping. The text is smaller than the 18px design for the older users who use a phone.
- On a phone, `text-sm` and `text-base` look the same. A design that needs two text levels on a phone must use weight or color, not size.
- On a phone, `text-lg` and `text-xl` are larger in relation to body text. If card titles look too heavy, make those steps smaller below `sm` with a new decision.
- Touch layouts get taller. Rows of `sm` buttons take more space on a tablet. French button rows that already overflow (audit § G) need a check after the change.
- Docs to update with the code: the `--text-*` comment in `resources/css/app.css`, `docs/conventions.md` § Styling (the "Responsive type scale" entry and the "15px floor" in the Table entry), and the `/design-system` typography section.
- Implementation tickets go under #737: phone body size and floor text, `text-xs` cleanup, `Button` touch sizes, checkbox and switch touch areas.
