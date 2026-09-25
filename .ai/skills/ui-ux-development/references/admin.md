# The admin area: AdminLTE 4 and Bootstrap 5.3

How the rules in [SKILL.md](../SKILL.md) are met in the admin. The full
AdminLTE reference is [docs/adminlte.md](../../../../docs/adminlte.md); this is
the part that decides how a screen looks and reads.

## Use the components

Every AdminLTE pattern has a component — the table in docs/adminlte.md §6.
They already carry the rules: `<x-admin::form.*>` wires the label, the error
id, `aria-invalid`, and `aria-describedby`; `<x-admin::ui.money>` keeps an
amount left-to-right in tabular digits; `<x-admin::ui.empty-state>` and
`<x-admin::table.empty-row>` cover the empty state. A missing pattern becomes a
component before its markup appears a third time.

## Colour

- Muted text is `text-body-secondary`, which follows `data-bs-theme`. Never
  `text-secondary`: it is one grey, 4.3:1 on the light page and 3.8:1 on the
  dark. `ViewHygieneTest` rejects it.
- Text on a `bg-*-subtle` surface is `text-*-emphasis`, the pair Bootstrap
  designed for it.
- Tokens live in `resources/css/admin/_theme.css` as `--bs-*` overrides.

## Direction

- Bootstrap's logical utilities flip with `dir="rtl"`: `ms-* me-* ps-* pe-*
  text-start text-end float-start float-end`. Write the same class for both
  directions.
- AdminLTE ships separate LTR and RTL stylesheets; the layout picks one. Never
  load both.
- Directional icons are chosen from `is_rtl()` in the component (see
  `admin/layout/sidebar/item.blade.php`).

## The traps already found

- AdminLTE's accessibility script gives any `.nav` or `.navbar-nav` without a
  `role` the role `navigation` (so it stops being a list) and an English
  "Navigation 1" label. Every such element carries `role="list"` (or its real
  role, such as `tablist`) and a translated `aria-label`.
- The AdminLTE demo pages put `role="navigation"` on the sidebar `<ul>` — the
  same fault, copied in by hand.

## Behaviour

- Use AdminLTE's `data-lte-*` plugins and Bootstrap's `data-bs-*` API rather
  than writing JavaScript.
- A button that runs a Livewire action disables itself while it runs:
  `wire:loading.attr="disabled"`, plus a spinner with `wire:loading`.
- Icons are Bootstrap Icons, `aria-hidden="true"` when beside text.
- Stock and account screens can use `.compact-mode` for density.
