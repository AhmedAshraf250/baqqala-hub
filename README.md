# Baqqala

*Baqqala* is Arabic for the corner grocery — and this is the system for running
one. The products on the shelves, the people who buy them, the suppliers who
restock them, and the part every shopkeeper keeps in a notebook: who took what
on account, and how much they still owe.

That notebook is where it starts. Customers and their running accounts are
built underneath; their screens come next, then the catalog, suppliers,
purchases, sales, and reports — each already has its place in the sidebar.

Arabic first, English right beside it, and both directions done properly.

## Two doors

- **`/admin`** — the back office, for the owner and whoever works the counter.
- **`/`** — the shop's own site. A customer the shop gives a login sees their
  account under `/account`. There is no sign-up: people are added at the
  counter, not by filling in a form.

## How it's put together

```
app/
├── Foundation/      the ground floor: the two areas and their separate logins,
│                    money, the two languages, and how a module plugs in
├── Admin/           the back office, on AdminLTE
├── Frontend/        the site and /account, on Flux
└── Modules/         the shop itself — each one a folder that can come and go
    ├── Customers/   the people the shop deals with
    ├── Accounts/    the tab: what each of them owes
    ├── Catalog/     what's on the shelves
    ├── Suppliers/   who restocks them
    ├── Purchases/   goods coming in
    ├── Sales/       goods going out
    └── Reports/
```

Each layer leans only on the one below it — a module on the areas and the
foundation, never the other way round — and a test fails the day that changes.
Inside, a module keeps only the folders it needs. Accounts needs most of them:

```
app/Modules/Accounts/
├── Contracts/       what other modules are allowed to use
├── Domain/          the rules: posting to the tab, the credit limit
├── Database/        its tables and models
├── Areas/           its screens, one folder per area
├── Authorization/   who may do what with it
├── Config/          its settings
└── Resources/       its views and its strings, in Arabic and English
```

## Running it

```
composer run setup      # dependencies, .env, key, tables, assets
php artisan db:seed     # one login for each door
composer run dev        # server, queue, logs and Vite together
```

Then sign in at `/admin` as `admin@example.test`, or at `/login` as
`customer@example.test`. The password for both is `password`.

On a new machine, check PHP itself once — `php.ini` belongs to the machine, so
nothing in the repository can carry it:

```
php artisan about --only=foundation   # the OPcache line must say "on"
php --ini                             # which php.ini to edit, if it does not
```

In that `php.ini`, remove the `;` from `zend_extension=opcache` and
`opcache.enable=1`, set `realpath_cache_size=4096k`, and restart
`composer run dev`. Without it every page recompiles PHP: about 0.5 s instead
of 0.15 s. Leave `opcache.enable_cli` at 0 — the console gains nothing from it.
Never run `php artisan optimize` on a development machine: it caches the
configuration, and the tests then refuse to run (they would otherwise use, and
wipe, the real database).

The browser tests need Chromium once per machine:
`npx playwright install chromium`. Before sending anything anywhere:

```
vendor/bin/pint
vendor/bin/phpstan analyse
php artisan test
```

## Finding your way

- [ARCHITECTURE.md](ARCHITECTURE.md) — why it is built the way it is.
- [docs/adding-a-module.md](docs/adding-a-module.md) — adding something new,
  deliveries say.
- `php artisan list app` — the project's own commands.

Everything that is about groceries lives in `app/Modules`. What sits under it —
the two areas, logins, money, the two languages — knows nothing about
groceries. The `foundation-v1.0.0` tag marks that point, in case the next shop
turns out to be a school.
