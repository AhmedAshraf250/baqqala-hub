# The frontend: Flux and Tailwind v4

How the rules in [SKILL.md](../SKILL.md) are met on the frontend.

## What Tailwind sees

`resources/css/frontend/app.css` imports Tailwind with `source(none)` and names
every folder it scans: the frontend's views, `app/Frontend`, its scripts,
Flux, and each module's `Resources/views/frontend`. A class written anywhere
else is not generated. Classes appear only after `npm run build`, or while
`npm run dev` is running.

## Colour

- Use Flux's default text colour. Its `variant="subtle"` is 2.5:1 on a light
  background, and `ViewHygieneTest` rejects it.
- Bare `text-zinc-400` fails on light backgrounds; muted text is
  `text-zinc-600 dark:text-zinc-400`.
- The red scale is shifted one shade darker in `@theme`, so Flux's danger button
  and error text meet 4.5:1. Do not bring back Tailwind's own red-500.
- Flux draws a sidebar group heading in zinc-400; the stylesheet corrects it.
  A new Flux component with a hard-coded grey gets the same treatment, with a
  comment saying why.
- Dark mode is the `.dark` class; check both.

## Direction

- Logical utilities: `ms- me- ps- pe- start- end- text-start text-end border-s
  border-e`. For icons that must mirror, `rtl:rotate-180`.
- Flux's own strings are English. Pass translated ones:
  `<flux:sidebar.collapse :tooltip="…">`, `<flux:sidebar.toggle :aria-label="…">`.

## Components

- Flux first; fall back to Blade components under `frontend::ui` when Flux has
  none.
- `<flux:input label="…">` pairs its label in the browser, through `<ui-label>`.
- Flux buttons show their own loading state for `wire:click` and submit.
- Icons are Flux's (Heroicons); never an emoji.
