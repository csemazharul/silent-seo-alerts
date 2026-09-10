# Silent SEO Alerts

A WordPress plugin that watches your pages for SEO-affecting changes and tells you what
broke, how bad it is, and what to do about it — in plain English.

Most SEO damage is silent. A plugin update flips a page to `noindex`, a theme change drops
the canonical tag, an editor trims 800 words out of a post — and nothing tells you. You
find out weeks later, when the traffic is already gone.

This repository is the source of record for the plugin published on WordPress.org. The
compiled files under `assets/` are built from `frontend/src`; see
[Building from source](#building-from-source).

---

## What it watches

**Per page** — `noindex`/`nofollow` appearing or disappearing, robots meta changes, titles
and meta descriptions, canonical tags (changed, removed, or pointed off-site), JSON-LD
schema types, Open Graph and Twitter tags, H1 changes, word-count drops, HTTP errors, and
new or off-site redirects.

**Site-wide** — `robots.txt` changes (including a loud alert if it starts blocking
everything), the rules it sets for AI crawlers, XML sitemap health, and WordPress's own
"Discourage search engines" setting.

**AI crawlers** — records when GPTBot, ClaudeBot, PerplexityBot, Google-Extended, CCBot
and others actually visit, so you can notice when one goes quiet. No setup needed; it
reads the user agent on incoming requests.

Findings are graded critical / warning / informational, and a single email digest goes out
per run rather than one message per finding.

Everything runs on the site itself. There is no account, no API key, and no third-party
service — the plugin fetches only URLs on your own host, using the WordPress HTTP API.

---

## Requirements

| | |
| --- | --- |
| PHP | 8.2+ |
| WordPress | 5.9+ |
| Node | 20+ (only to build the frontend) |
| pnpm | 9+ (only to build the frontend) |

---

## Building from source

The plugin ships pre-built — `assets/` already contains the compiled bundle, so no build
step is needed just to run it. To reproduce those files:

```bash
pnpm install --frozen-lockfile
pnpm build:free
```

That writes `assets/main-*.js`, `assets/main-*.css` and `assets/build-code-name.txt`.
`frontend/public/` is copied into `assets/` verbatim, which is how the self-hosted Outfit
web fonts reach `assets/fonts/`.

> The Vite build sets `emptyOutDir`, so it wipes `assets/` first. Anything that must
> survive a build belongs in `frontend/public/`, not `assets/`.

To produce the distributable zip:

```bash
pnpm prod:free-zip     # -> dist/silent-seo-alerts-<version>.zip
```

`scripts/build-zip.mjs` copies an explicit allowlist, installs Composer dependencies
without dev packages, strips dev files from `vendor/`, and then re-opens the finished zip
to confirm nothing forbidden (`.env`, `.git`, `node_modules`) slipped in.

---

## Local development

Place the repository in `wp-content/plugins/silent-seo-alerts/`, then:

```bash
composer install
pnpm install
cp .env.example .env
pnpm dev:free          # Vite dev server with hot reload
```

Set these in `.env` so WordPress loads assets from Vite instead of `assets/`:

```env
DEV     = true
DEV_URL = http://localhost:3000/wp-content/plugins/silent-seo-alerts/frontend
```

Activate the plugin in **Plugins**, then open **Silent SEO Alerts** in the admin sidebar.
The first run records a baseline — nothing is reported until the run after that, because
there is nothing to compare against yet.

### Checks

```bash
pnpm lint                 # ESLint, autofixing
pnpm exec tsc --noEmit    # TypeScript
composer compat           # PHPCompatibility against PHP 8.2
composer code:analyze     # PHPStan
composer phpcs            # PSR-12 + project sniffs
```

The release gate is the official
[Plugin Check](https://wordpress.org/plugins/plugin-check/) plugin, run against the built
zip rather than the working tree — it is what WordPress.org reviewers use:

```bash
wp plugin check silent-seo-alerts
```

> Known rough edges: `composer lint` (and `pnpm lint:php`, which calls it) references a
> `.php-cs-fixer.php` config that is not in the repository, so it exits before running.
> `pnpm test` finds no test files — the Vitest setup exists but nothing uses it yet.
> `composer phpcs` currently reports a backlog of style violations; it is not yet a
> passing gate.

---

## Project structure

```
├── silent-seo-alerts.php        # plugin header, loads backend/bootstrap.php
├── uninstall.php                # drops tables, options, transients, cron on delete
├── backend/
│   ├── app/
│   │   ├── Config.php           # slug, version, prefixes, path helpers
│   │   ├── Plugin.php           # boots providers per request type
│   │   ├── HTTP/
│   │   │   ├── Controllers/     # AJAX + REST endpoints
│   │   │   └── Middleware/      # nonce + manage_options checks
│   │   ├── Models/              # Target, Finding, CheckRun, Snapshot, ...
│   │   ├── Providers/           # cron, hooks, installer, bot tracking
│   │   ├── Services/
│   │   │   ├── CheckEngine/     # fetch, extract, diff, classify severity
│   │   │   ├── Findings/        # plain-English explanations per change type
│   │   │   ├── Alerts/          # email digest
│   │   │   └── Maintenance/     # retention pruning
│   │   └── Views/               # admin shell, enqueues, email templates
│   ├── db/Migrations/           # one class per table
│   └── hooks/{ajax,api}.php     # route definitions
├── frontend/src/
│   ├── features/                # dashboard, targets, flightLog, siteWide, settings
│   ├── api/                     # React Query hooks + request layer
│   └── config/                  # server variables handed over by wp_localize_script
├── assets/                      # built output — do not edit by hand
└── scripts/build-zip.mjs        # release packaging
```

---

## Architecture notes

**Storage prefix.** Tables and options use `SEO_CHANGE_MONITOR_`, and the PHP namespace is
`SEOChangeMonitor\`. The plugin was renamed to Silent SEO Alerts after those were set; they
were deliberately left alone, because changing the namespace would mean regenerating the
whole Imposter-prefixed `vendor/` tree, and changing the storage prefix would orphan
existing installs' data. Only the public identity — name, slug, text domain — was renamed.

**Vendor isolation.** Composer dependencies are namespaced under `SEOChangeMonitor\Deps\`
by [Imposter](https://github.com/TypistTech/imposter-plugin), so a different plugin
bundling the same library cannot collide.

**Extension points.** The plugin exposes filters and actions through
`Config::withPrefix()` — `settings_defaults`, `settings_update_partial`,
`settings_for_client`, `run_finished`, `settings_updated`, `ssl_verify`. An add-on can
register settings keys, validate them, strip its own secrets before they reach the
browser, and act on a finished run without touching anything here.

**Migrations.** `InstallerProvider::migration()` lists them in run order;
`drop()` returns the same set, walked with `down()` instead of `up()`.

---

## Contributing

Match the surrounding style; the linters above are the arbiter. Keep refactoring commits
separate from behaviour changes.

The generators are still available for scaffolding:

```bash
php wp-kit make:controller ExampleController
php wp-kit make:model Tag
php wp-kit make:migration AppConnections     # also registers itself in InstallerProvider
```

> Do not run `php wp-kit plugin:init`. It is the one-time initializer from the starter
> template this project began as, and running it now would rewrite the plugin's identity.

---

## License

GPL-2.0-or-later.

The bundled Outfit typeface is licensed under the SIL Open Font License 1.1 — see
[`frontend/public/fonts/LICENSE-Outfit.txt`](frontend/public/fonts/LICENSE-Outfit.txt).
