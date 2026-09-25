---
name: ui-ux-development
description: "Use for any work that changes how a screen looks, reads, or behaves — building, changing, or reviewing pages, components, forms, tables, navigation, empty/loading/error states, colour, typography, motion, or accessibility — in either area, admin or frontend, including markup adopted from a ready-made template. Stack-neutral UX rules first, then how this codebase applies them: AdminLTE and Bootstrap in the admin, Flux and Tailwind on the frontend, Arabic and English in both. Refines AGENTS.md and never overrides it."
license: MIT
metadata:
  author: project
---

# UI/UX Development

These rules hold for any product built on this architecture, whatever it is
selling or teaching. A screen is done when it is quick to operate, reads
correctly in both directions and both colour modes, and leaves no doubt about
what a figure means.

The rules are in priority order. [AGENTS.md](../../../AGENTS.md) and
[docs/adminlte.md](../../../docs/adminlte.md) win over anything here.

## Where the details are

- [references/guidelines.md](references/guidelines.md) — the full web rule set
  by category. Read it for a review or an audit.
- [references/admin.md](references/admin.md) — AdminLTE 4 and Bootstrap 5.3:
  which component and class to use, and the traps already found.
- [references/frontend.md](references/frontend.md) — Flux and Tailwind v4: the
  same, for the frontend.

## Already enforced — do not re-check by hand

- `accessibilityProblems()` in `tests/Pest.php` runs on every screen listed in
  `adminScreens()` and `customerScreens()`: every link and button has a name,
  every field a label, every referenced id exists, every image has `alt`.
  **A new screen joins one of those lists** and every check below covers it.
- `tests/Browser/AccessibilityTest.php` opens each of those screens in a real
  browser, in Arabic and English, light and dark: axe finds no serious issue
  (contrast included), no script throws, and nothing scrolls sideways at 375px.
- `tests/Feature/Foundation/ViewHygieneTest.php` rejects colour classes known to
  fail contrast in one of the modes.
- When a rule below can be decided from markup alone, add it to one of those
  tests rather than relying on this page.

## 1. Two languages, two directions — critical

- Every string is a translation key in `ar` and `en`, **including** `aria-label`,
  `title`, `placeholder`, and tooltips. Component libraries default some of
  these to English; pass them yourself.
- Write logical sides (start/end), never physical ones (left/right): the
  physical ones do not flip.
- Directional icons flip (chevrons, back/next arrows); others do not.
- Digits stay left-to-right: amounts, phone numbers, codes, and emails get
  `dir="ltr"` on their own element, not on the whole row.
- Free text a person typed (a note, a name) may be in either script: show it
  with `dir="auto"`.
- Arabic is drawn in Cairo in both areas. A new face keeps Cairo after it, and
  is loaded through Vite, never from a CDN.
- Never set letter-spacing on text that may be Arabic — it breaks the joined
  letters. Arabic has no italic and no capitals: do not use either for emphasis.
- Labels change length between languages. Never size a button or column to fit
  the English word.

## 2. Accessibility — critical

- An icon-only control has a translated `aria-label`. An icon beside text is
  decorative: `aria-hidden="true"`.
- Every field has a visible label; its error sits under it and is tied to it
  with `aria-describedby`, and the field is marked `aria-invalid`. A
  placeholder is never the only label.
- Colour never carries meaning alone: a status says itself in words, and an
  amount coloured by its sign also says which way it goes.
- Contrast at least 4.5:1 for text and 3:1 for large text and controls — in
  **both** colour modes, checked separately.
- Focus stays visible. Never remove an outline without replacing it.
- Everything works from the keyboard, in the order it appears on screen.
- Motion stops under `prefers-reduced-motion`.
- Headings go in order: one page title, then each level in turn.

## 3. Forms

- The right input for the job: `type="email"`, `type="tel"`,
  `inputmode="numeric"` for quantities and codes, `inputmode="decimal"` for
  amounts, `autocomplete` on identity fields.
- Long forms and sign-in screens also show an error summary at the top.
- A submit button shows it is working and cannot be pressed twice.
- A failed submit keeps what was typed.
- Anything destructive or irreversible asks first and names what will happen.

## 4. The states every screen has

- **Loading** reserves its space; nothing jumps when the data arrives.
- **Empty** says what belongs here and offers the next action.
- **Error** says what happened and what to do next — never a raw exception or
  an untranslated key.
- **Success** is confirmed briefly, once.
- **No search results** says so and repeats the query.

## 5. Tables and figures

- On a narrow screen a table scrolls inside its container; the page never
  scrolls sideways.
- Figure columns align to the end with tabular digits, so values line up by
  their last digit in both directions.
- Long names wrap, or truncate with the full value reachable. A figure is never
  truncated.
- Row actions are labelled; the destructive one is last and confirmed.
- Dates and numbers are locale-aware: relative for recent activity, exact where
  a record matters.

## 6. Layout, density, colour

- The admin is dense by design; the frontend is mobile-first, with touch targets
  of at least 44px and body text of at least 16px.
- Colours are tokens, never hex in a view, and stay business-neutral, so a new
  product changes tokens rather than components.
- One icon family per area. Never an emoji as an icon.

## 7. Adopting a ready-made template or component

A template, a demo page, or a starter kit is a source of markup, not of
correctness. The ones this project started from arrived with a list given
`role="navigation"`, light-grey text below 3:1, English labels on buttons, and
physical left/right classes. Before lifting markup:

- Replace its components with this codebase's own where one exists — see the
  two references.
- Translate every string, including hidden ones.
- Swap physical sides for logical ones, and hex colours for tokens.
- Add the screen to `adminScreens()` or `customerScreens()` and let the tests
  report what is left.

## Before calling a screen done

- [ ] Seen in Arabic and English, with nothing English left on the Arabic page
      (tooltips and `aria-label`s included)
- [ ] Seen in light and dark
- [ ] At 375px and at desktop width, with no sideways page scroll
- [ ] Every action reached with Tab alone, focus visible throughout
- [ ] Empty, loading, error, and success states seen
- [ ] Figures left-to-right, aligned, never coloured without words
- [ ] The screen is in `adminScreens()` or `customerScreens()`

## Source

Distilled from
[ui-ux-pro-max](https://github.com/nextlevelbuilder/ui-ux-pro-max-skill) (MIT):
its web guidelines, priority order, and pre-delivery checklist, kept to what
applies to server-rendered screens in two languages. Its native-app, React,
and landing-page advice does not apply here.
