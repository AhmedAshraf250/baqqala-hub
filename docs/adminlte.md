# AdminLTE 4 in Baqqala

Distilled from `BASE/TEMPLATES/adminLTE-v4.0.0/docs`. Read this before touching
admin markup; it is the working reference so nobody has to open the template
demos again.

`BASE/` is **read-only reference material**. We lift markup patterns from it
into Blade components. We never copy its CSS or JS, and we never paste a whole
demo page into a view.

---

## 1. How AdminLTE is installed here, and why

AdminLTE ships four install paths. Baqqala uses **npm + Vite**.

| Path | Why not |
| --- | --- |
| CDN | No bundling, no hashing, external runtime dependency. |
| Composer (`almasaeed2010/adminlte`) | Drops the same `dist/` into `vendor/`, which Vite cannot process. It exists for PHP projects with no JS build. We have one. |
| Copy the template files | Every AdminLTE upgrade becomes a manual merge, forever. |
| **npm + Vite** | Bundled, hashed, tree-shaken, upgradable with `npm update`. |

```
npm:  admin-lte@4.0.0  bootstrap@5.3  @popperjs/core  bootstrap-icons  overlayscrollbars
```

We load AdminLTE's **prebuilt CSS**, not its SCSS source. That is a deliberate
trade:

- ✅ No `sass` / `rtlcss` toolchain, no extra devDependencies.
- ✅ Colours, radii, fonts, and spacing tokens are all reachable as CSS custom
  properties in `resources/css/admin/_theme.css`.
- ❌ **Sidebar width, responsive breakpoints, and the spacing scale cannot be
  changed.** AdminLTE exposes those only as SCSS variables. Needing one of them
  is the trigger to revisit this decision — not a reason to fight it with
  `!important`.

---

## 2. The layout blueprint

Every admin page is the same five regions. `.app-wrapper` is a CSS grid
container and the rest are its grid areas, so **source order inside the wrapper
does not matter**.

```
┌──────────────────────────────────────────┐
│ .app-wrapper                             │
│ ┌────────────────────────────────────┐   │
│ │ .app-header                        │   │  ← topbar
│ ├──────────┬─────────────────────────┤   │
│ │ .app-    │ .app-main               │   │  ← page content
│ │ sidebar  │                         │   │
│ ├──────────┴─────────────────────────┤   │
│ │ .app-footer                        │   │
│ └────────────────────────────────────┘   │
└──────────────────────────────────────────┘
```

In Baqqala this lives in exactly one file: `resources/views/admin/layout/app.blade.php`.
No page re-declares it.

`.app-main` always holds two children:

```html
<main class="app-main">
  <div class="app-content-header">…</div>  <!-- title + breadcrumb -->
  <div class="app-content">…</div>          <!-- cards, tables, forms -->
</main>
```

Both wrap their children in `.container-fluid`.

### Body-level modifiers

Layout behaviour is controlled by classes on `<body>`, never by custom CSS.

| Class | Effect |
| --- | --- |
| `layout-fixed` | Sidebar scrolls independently; only `.app-main` scrolls with the page. |
| `fixed-header` | Sticky header; the sidebar pins too (since 4.0.0). |
| `fixed-footer` | Sticky footer. |
| `sidebar-expand-{sm,md,lg,xl,xxl}` | Breakpoint where the sidebar stops being off-canvas. `lg` is our default. |
| `sidebar-mini` | Collapses to an icon rail instead of hiding. |
| `sidebar-mini sidebar-collapse` | Starts collapsed. |
| `sidebar-without-hover` | Disables expand-on-hover for the mini rail. |
| `layout-rtl` | Mirrors the layout (also needs `dir="rtl"` and the RTL stylesheet). |

Baqqala uses `layout-fixed sidebar-expand-lg bg-body-tertiary`.

`.compact-mode` on `.app-wrapper` tightens padding throughout — worth reaching
for on dense account and stock screens.

---

## 3. RTL

AdminLTE ships a mirrored stylesheet generated with `rtlcss`.

**The rule: pick one stylesheet, never load both.** `<x-admin::layout.assets>`
picks it from the active locale's `direction`, and the shell sets `dir` on
`<html>` to match. Both admin layouts include that component, so the choice is
written once.

| | file |
| --- | --- |
| LTR | `admin-lte/dist/css/adminlte.css` |
| RTL | `admin-lte/dist/css/adminlte.rtl.css` |

`dir="rtl"` also switches Bootstrap 5.3 to logical properties, so `.ms-*`,
`.me-*`, `.float-start` and `.float-end` mirror automatically — **write the same
utility classes for both directions**.

### What does *not* flip automatically

- **Directional icons.** `bi-chevron-right` stays pointing right. Swap it in
  Blade: see `admin/layout/sidebar/item.blade.php`, which picks the chevron
  from `is_rtl()`.
- **Third-party widgets** (charts, calendars, data grids) — each needs its own
  RTL option.
- **Digits.** Numbers stay LTR inside Arabic text. That is correct Unicode bidi
  behaviour, not a bug. `<x-admin::ui.money>` sets `dir="ltr"` for this reason.

---

## 4. Colour mode

Bootstrap 5.3's `data-bs-theme` attribute on `<html>`, with three choices:
`light`, `dark`, `auto`.

The implementation is split deliberately:

- `admin/layout/color-mode-script.blade.php` runs **inline in `<head>`**. It has
  to: deferring it would let the browser paint a light page before the dark
  theme lands.
- `resources/js/admin/modules/color-mode.js` handles the dropdown and reacts to
  OS changes while the user is on `auto`.

Both read the same storage key, published once from
`config('admin.theme_storage_key')`.

Dark-mode overrides are scoped in `_theme.css`:

```css
[data-bs-theme='dark'] { --bs-body-bg: #14171c; }
```

---

## 5. The JavaScript plugins

AdminLTE bundles seven plugins. **All of them self-bind from `data-lte-*`
attributes on `DOMContentLoaded`** — a page should never need its own script tag
to use one.

| Plugin | Data attribute | Use it for |
| --- | --- | --- |
| PushMenu | `data-lte-toggle="sidebar"` | The hamburger. |
| Treeview | `data-lte-toggle="treeview"` on the parent `<ul>` | Nested sidebar menus. |
| CardWidget | `data-lte-toggle="card-collapse\|card-remove\|card-maximize"` | Collapsible / maximizable cards. |
| DirectChat | `data-lte-toggle="chat-pane"` | Chat-style panels. |
| FullScreen | `data-lte-toggle="fullscreen"` | Fullscreen toggle. |
| Layout | auto-applied to `<body>` | Layout transitions. |
| AccessibilityManager | `initAccessibility()` | Runs automatically. |

PushMenu and Treeview use event delegation, so they keep working on markup
Livewire injects after load.

### Configuration via data attributes

```html
<aside class="app-sidebar"
       data-enable-persistence="true"     <!-- remember collapsed state -->
       data-sidebar-breakpoint="992">     <!-- mobile threshold in px -->
```

```html
<ul class="sidebar-menu" data-lte-toggle="treeview" data-accordion="false">
```

### Events

Every plugin emits a bubbling event. Listen on `document`.

| Event | Fires when |
| --- | --- |
| `open.lte.push-menu` / `collapse.lte.push-menu` | Sidebar expanded / collapsed |
| `expanded.lte.treeview` / `collapsed.lte.treeview` | Submenu opened / closed |
| `expanded.lte.card-widget` / `collapsed.lte.card-widget` | Card toggled |
| `maximized.lte.card-widget` / `minimized.lte.card-widget` | Card resized |
| `remove.lte.card-widget` | Card removed |
| `maximized.lte.fullscreen` / `minimized.lte.fullscreen` | Fullscreen toggled |

**This is how you keep a chart honest when the sidebar moves** — anything that
measures its container on init needs to re-measure after the transition:

```js
document.addEventListener('collapse.lte.push-menu', () => {
    setTimeout(() => chart.updateOptions({}), 350) // transition is ~300ms
})
```

### Accessibility, for free

`initAccessibility()` runs on load and provides WCAG 2.1 AA behaviour we would
otherwise hand-roll: skip links, focus trapping in modals, arrow-key menu
navigation, `prefers-reduced-motion`, live-region announcements, and automatic
`scope` attributes on table headers.

Available programmatically:

```js
import { initAccessibility } from 'admin-lte'
const a11y = initAccessibility()
a11y.announce('تم حفظ الحركة', 'polite')
```

**One thing it gets wrong.** On load it gives every `.nav` and `.navbar-nav`
that has no `role` the role `navigation`, and every one without an
`aria-label` the label "Navigation 1", "Navigation 2"… in English. A `<ul>`
with role `navigation` is no longer a list, so its `<li>` items are orphaned
for a screen reader. It leaves alone whatever already has both, so every such
list here carries `role="list"` and a translated `aria-label` — see
`admin/layout/header.blade.php`. `tests/Feature/Admin/AccessibilityTest.php`
fails on one that does not.

### Colours that follow the mode

Bootstrap's `text-secondary` is one fixed grey: 4.3:1 on the light page and
3.8:1 on the dark one, both below WCAG AA. Muted text is `text-body-secondary`,
which follows `data-bs-theme`. Text on a `bg-*-subtle` surface is
`text-*-emphasis`, the pair Bootstrap designed for it.

---

## 6. Markup patterns → Baqqala components

Never write these by hand. If a pattern is missing, add the component rather
than inlining the markup.

| AdminLTE pattern | Baqqala component |
| --- | --- |
| `.card` with header/tools/footer | `<x-admin::ui.card>` |
| `.small-box` KPI tile | `<x-admin::ui.small-box>` |
| `.info-box` stat row | `<x-admin::ui.info-box>` |
| Alerts | `<x-admin::ui.alert>`, `<x-admin::ui.flash-messages>` |
| Badges | `<x-admin::ui.badge>` |
| Money output | `<x-admin::ui.money>` |
| Empty states | `<x-admin::ui.empty-state>` |
| `.table-responsive` + `.table` | `<x-admin::table.table>` + `.heading` / `.empty-row` / `.row-actions` |
| Form controls + validation state | `<x-admin::form.input\|money\|select\|textarea\|checkbox>` |
| Sidebar tree with headers and badges | `<x-admin::layout.sidebar.item>`, driven by `AdminNavigation` |
| `.app-content-header` + breadcrumb | `<x-admin::layout.content-header>` |

Most of these are templates alone: everything they show arrives as
attributes. The pieces that read the navigation tree, the signed-in
administrator, or the session — the sidebar, the breadcrumbs, the content
header, the tab title, the user menu (`app/Admin/View/Components/Layout/`), and
the flash messages (`Ui/FlashMessages`) — have a class that prepares the data;
their templates only render it. A new component that needs data gets a class
there, never an `app(…)` or `session()` in its template.

### Sidebar structure (reference)

```html
<li class="nav-header">SECTION</li>
<li class="nav-item menu-open">           <!-- menu-open = start expanded -->
  <a href="#" class="nav-link active">
    <i class="nav-icon bi bi-folder"></i>
    <p>
      Label
      <span class="nav-badge badge text-bg-secondary me-3">6</span>
      <i class="nav-arrow bi bi-chevron-right"></i>
    </p>
  </a>
  <ul class="nav nav-treeview">…</ul>
</li>
```

Baqqala renders this from `App\Admin\Navigation\AdminNavigation`, which assembles
it from the running modules; `App\Admin\View\Components\Layout\Sidebar` hands
it to the template. A module joins the sidebar by implementing
`App\Admin\Contracts\Navigation\ProvidesAdminNavigationInterface` and returning a
`NavigationSection` — **do not edit the sidebar Blade.**

---

## 7. Third-party integrations

AdminLTE deliberately ships a lean dependency tree and recommends these. All are
MIT and jQuery-free. **Install via npm and import in the admin bundle — never
add a CDN tag.**

| Need | Library |
| --- | --- |
| Searchable select / tag input / remote autocomplete | **Tom Select** (ships a `tom-select.bootstrap5.css` theme) |
| Date / time picker | Flatpickr |
| Input mask (phones, money) | IMask |
| Interactive data grid (sort/filter/paginate/export) | Tabulator (`tabulator_bootstrap5.css`) |
| Charts | ApexCharts (used by the AdminLTE demos) or Chart.js |
| Calendar | FullCalendar |
| Drag and drop | SortableJS |
| File upload | FilePond or Dropzone |
| Rich text | Quill |

> For the fast customer lookup Baqqala needs, **Tom Select with remote data** is
> the intended starting point — it is Bootstrap-themed, keyboard-first, and
> handles remote search natively.

Check each library's RTL support before adopting it; their internals do not
react to `dir`.

---

## 8. Template files worth reading, and what to skip

Useful references in the template, read-only:

| Path | For |
| --- | --- |
| `BASE/TEMPLATES/adminLTE-v4.0.0/layout/layout-rtl.html` | The RTL shell |
| `BASE/TEMPLATES/adminLTE-v4.0.0/docs/components/main-sidebar.html` | Sidebar structure |
| `BASE/TEMPLATES/adminLTE-v4.0.0/docs/components/main-header.html` | Header structure |
| `BASE/TEMPLATES/adminLTE-v4.0.0/widgets/small-box.html`, `info-box.html`, `cards.html` | Stat and card markup |
| `BASE/TEMPLATES/adminLTE-v4.0.0/forms/elements.html`, `validation.html` | Form controls and validation states |
| `BASE/TEMPLATES/adminLTE-v4.0.0/tables/simple.html`, `data.html` | Table markup, Tabulator wiring |
| `BASE/TEMPLATES/adminLTE-v4.0.0/pages/invoice.html` | A good base for account statements |
| `BASE/TEMPLATES/adminLTE-v4.0.0/examples/login.html`, `register.html` | Auth screens |

Skip until there is a real feature need: `index.html` / `index2.html` /
`index3.html` demo dashboards, `mailbox/`, `pages/chat.html`, `kanban.html`,
`file-manager.html`, `calendar.html`, `projects.html`, `generate/`.

Do not bulk-copy `assets/img/` — those are demo photos.
