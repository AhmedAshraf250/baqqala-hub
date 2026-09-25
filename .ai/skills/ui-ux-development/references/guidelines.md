# Web UI/UX guidelines

The rule set of [ui-ux-pro-max](https://github.com/nextlevelbuilder/ui-ux-pro-max-skill)
(MIT), kept to what applies to server-rendered web screens and ordered by
impact. Native-app rules (safe areas, haptics, platform gestures) are left out.
Use it as the checklist for a review: go down the categories in order and stop
at the first that fails.

## 1. Accessibility — critical

- Text contrast at least 4.5:1, large text 3:1; meaningful icons and control
  borders 3:1. Check light and dark separately.
- Focus is visible on every interactive element, and not hidden under sticky
  headers, banners, or overlays.
- Tab order follows the visual order; every action works without a pointer.
- Icon-only controls have an accessible name. Decorative icons beside text are
  hidden from assistive technology. Meaningful images have `alt`.
- Every field has a `<label>`; placeholders are hints, not labels.
- Headings run h1 → h6 without skipping a level.
- Information is never carried by colour alone — add an icon or words.
- `prefers-reduced-motion` reduces or removes animation.
- Modals and multi-step flows have a clear way out.
- Pointer targets are at least 24×24 CSS px on desktop, 44px on touch.
- Sign-in allows password managers and paste.
- A changed count or status is announced once, as a whole phrase ("3 items"),
  without moving focus.

## 2. Interaction — critical

- Primary actions work by click or tap; never by hover alone.
- A button disables itself and shows progress while its action runs.
- An error appears next to what caused it.
- Clickable things look clickable and change on hover, press, and focus.
- Disabled controls look disabled and carry the `disabled` attribute.

## 3. Performance — high

- Images declare their size (or aspect ratio) so the page does not jump.
- Below-the-fold images load lazily.
- Fonts use `font-display: swap`; only the body weight is preloaded.
- Async content reserves its space; skeletons for waits over a second.
- Third-party scripts load `async`/`defer`, or not at all.
- High-frequency events (scroll, resize, typing into search) are debounced.

## 4. Consistency — high

- One visual style across every screen of an area.
- One icon family, one stroke weight, one filled-or-outline choice per level.
- One primary action per screen; the others visibly secondary.
- One elevation scale for cards, dropdowns, and modals.
- Light and dark designed together, not one derived from the other.

## 5. Layout and responsive — high

- `width=device-width, initial-scale=1`; zoom is never disabled.
- No sideways scroll at 375px. Test 375, 768, 1024, 1440.
- Body text at least 16px on phones; 60–75 characters per line on desktop.
- A spacing scale (4/8px steps), not arbitrary values.
- A layered z-index scale, not ad-hoc numbers.
- Fixed bars leave room for the content under them.
- Hierarchy comes from size, weight, and spacing — not colour alone.
- Chips and badges wrap before they shrink; a `+n` overflow can be opened.

## 6. Typography and colour — medium

- Line height 1.5–1.75 for body text.
- A consistent type scale and weight hierarchy (bold headings, regular body,
  medium labels).
- Semantic colour tokens (primary, danger, surface, muted) — never raw hex in a
  component.
- Error and success colours still meet 4.5:1.
- Tabular digits in figure columns, prices, and timers.
- Wrap rather than truncate; when truncating, the full text is reachable.
- URLs and identifiers wrap with `overflow-wrap: anywhere`; prose never uses
  `word-break: break-all`.

## 7. Motion — medium

- Animate `transform` and `opacity`, never width, height, or position.
- One or two animated elements per view; motion explains cause and effect.
- Exits are faster than entrances. Animations never block input and can be
  interrupted; rapid changes set the final state directly.
- Loading feedback matches the wait: nothing for instant work, progress for
  long work.

## 8. Forms and feedback — medium

- Visible labels, required fields marked, helper text for complex inputs.
- Validate on blur, not on every keystroke.
- The error says the cause and the fix ("Enter a phone number of 11 digits"),
  sits under its field, and is tied to it with `aria-describedby`.
- After a failed submit with several errors, focus a summary at the top whose
  items link to the fields.
- Semantic input types and `autocomplete`, so phones show the right keyboard
  and browsers can fill.
- Confirm destructive actions; separate them visually from the primary one.
- Toasts do not steal focus, are announced politely, and leave after 3–5 s.
- Long forms do not lose what was typed; closing one with unsaved changes asks
  first.
- Read-only looks different from disabled.

## 9. Navigation — high

- The current location is highlighted in the navigation.
- Every key screen has its own URL; back behaves predictably and restores
  scroll, filters, and input.
- Navigation stays in the same place on every screen; destructive items (sign
  out, delete) are set apart.
- Breadcrumbs from three levels deep.
- Navigation items carry a label, not an icon alone.
- An unavailable destination explains why rather than disappearing.
- After a page change, focus moves to the main content.

## 10. Charts and data — low

- The chart type fits the data: trend → line, comparison → bar, share → pie
  (five slices at most).
- Legends near the chart; exact values on hover and on keyboard focus.
- Never red against green alone; add patterns or labels.
- A table or text summary alongside, for screen readers.
- Axes labelled with units; numbers and dates locale-aware.
- Empty, loading, and error states — never a blank frame.
- Entrance animation respects reduced motion.
