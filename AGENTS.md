# PlainShow Blog: notes for developers and AI agents

Blog is blog.plainshow.se: posts, photo albums, films and stories. Plain PHP,
**Swedish** UI. Sign-in through PlainShow (kit `PhpSessionAuth`).

## Layout

- `public/`: pages (`view_blog.php`, `view_gallery.php`, `profile.php`, ...)
  and assets.
- `app/src/Domain`: the logic (Posts, Albums, Comments, Search, Admin, ...).
  `app/src/Api` + `app/routes/api.php`: the JSON API behind core's gateway,
  described in `app/routes/openapi-docs.php`.
- `app/auth/plainshow.php`: sign-in, and the check with core that the person
  may still use Blog.
- `ops/deploy.sh [tag]`: builds into `/srv/plainshow/blog/releases`. Uploads
  (films, profile pictures, games) stay in the old blog folder
  (`/var/www/html/projects/hugohansson/woc`) and are linked in. Runs as www-data.

## Work

- Branches: development and releases are on **`v2`**; `main` is the old blog
  that the uploads folder is checked out on. Tag releases on `v2`.
- No automated tests; check pages by hand and `php -l` changed files.

## The PlainShow platform

PlainShow is a set of web services on one server, each in its own git repo on
GitHub (`huggan360/plainshow-*`, public). They share one account system and
one look. `plainshow-install` sets up a new server with them (any Linux, any
domain); every address comes from `/etc/plainshow/platform.json` (kit `Platform`).

| Repo | Site | What it is |
|---|---|---|
| core | plainshow.se | Dashboard, accounts, groups, roles, admin, **Updates**. Laravel + Inertia/React. |
| login | login.plainshow.se | The only sign-in. OAuth2/OpenID Connect server, 2FA, device approval. Laravel. |
| kit | (library) | Shared PHP (auth, signed requests, widgets, access rules) and UI (CSS, JS, fonts, brand, error pages). Used by every app through Composer. |
| cloud | cloud.plainshow.se | Files, photos, calendar, tasks. A frozen Nextcloud 34.0.3 fork; our app is `apps/plainshow`. |
| latex | latex.plainshow.se | Collaborative LaTeX projects. Plain PHP + a websocket server. |
| blog | blog.plainshow.se | Posts, albums, films. Plain PHP. Swedish UI. |
| split | split.plainshow.se | Shared expenses. Plain PHP. Swedish UI. |
| install | (installer) | Interactive installer for a new server: packages, apps at their newest release, certificates, first admin. |

### Versions and releases (all repos)

- Versions are semver tags `vX.Y.Z` (patch: fixes; minor: features; major:
  breaking data or API changes). Only tags are installed, never branches.
- A release is: update `CHANGELOG.md` (a `## vX.Y.Z` section at the top, in
  plain words; the Updates page shows it), commit, `git tag -a vX.Y.Z -m vX.Y.Z`,
  `git push origin HEAD vX.Y.Z`.
- Install it through **Updates** in core (Admin, Updates, Install). The cron
  runner (`php artisan plainshow:updates --run`, every minute, in
  `/srv/plainshow/core/current`) fetches tags from GitHub, builds, switches,
  health-checks and rolls back by itself on failure. From a shell you can
  queue a job: insert into `core_update_jobs` (`component`, `action` = update,
  `target` = the tag, `status` = queued) and run the command above.
- While a component installs, visitors of its site see the "updating" page
  (flag file `/srv/plainshow/updating/<host>`, served by
  `core/ops/apache/plainshow-errors.conf`). Old releases are deleted after a
  successful update; nothing legacy is kept.
- Never copy files into `/srv/plainshow` or a live checkout by hand, never
  deploy a working tree (`--worktree` exists for emergencies only), never edit
  `vendor/`. Uncommitted changes in a checkout block its updates.

### Where things are on the server

- Release apps (core, login, blog, split): `/srv/plainshow/<app>/releases/<timestamp>-<tag>`,
  `current` symlink, shared state in `/srv/plainshow/<app>/shared` (`.env` or
  `config/`, `storage/`). Each runs in its own PHP-FPM pool as `plainshow-<app>`
  (the blog runs as www-data).
- Checkout apps: cloud in `/var/www/html/cloud`, latex in
  `/var/www/html/projects/hugohansson/plainshow-latex`. Updates moves them
  forward to a tag and runs their steps (see `core/config/updates.php`).
- Development sources: `/home/hugohansson/projects/plainshow/<repo>`.
- Error and updating pages: made in kit (`ui/errors`, `php bin/errors.php`),
  installed by core's deploy into `/srv/plainshow/errors`, shown by Apache for
  every site and by the apps themselves.
- Backups go to `/mnt/texlive/backups`. **Never touch `/mnt/cloud`** (Cloud's data).

### House rules

- Accounts live in login/core only. Services read identity from Login (OIDC)
  and permissions from core; never add a local password system.
- UI text: short and plain; never use the middle dot character as a
  separator.

- Design: kit CSS (`ps-*` classes, `--ps-*` tokens), Outfit for UI text, Bree
  Serif for titles, nested "frame" boxes. The primary colour is a theme colour
  (orange `#FC5000` by default, or teal, indigo, red, yellow): use `--ps-accent`,
  `--ps-accent-text`, `--ps-gradient` and `--ps-gradient-ring`, never a fixed
  orange. Text uses `--ps-text-strong` (titles), `--ps-text`, `--ps-text-soft`,
  `--ps-text-muted`, `--ps-text-faint`: dark greys in the light theme, never black.
- Secrets: never commit `.env`, `config/*.php` with credentials, keys or
  tokens, and never print them in logs or output.
- Test what you change with the repo's own tests (below) before tagging.

### Languages (English, Dutch, Swedish)

- Every page speaks English, Dutch or Swedish. The choice is the `ps_lang`
  cookie for the whole domain, set by the language switch in every account
  menu; without it the browser's language decides, then English (kit `Lang`).
- Texts are written in one source language (English in core, login, cloud and
  latex; Swedish in blog and split) and looked up by that text in
  `lang/<code>.json` ({"Source text": "Translation"}). `{name}` placeholders
  are filled in after translating (Laravel's `__()` uses `:name`).
- Wrap every visible text: React `t('...')` and `tn(count, 'one', '{n} many')`
  from the app's `i18n.ts`; Laravel `__('...')`; plain PHP `ps_h('...')` in HTML
  and `ps_t('...')` elsewhere; plain JS `psT('...')`. Never build a sentence
  from pieces; use one text with placeholders. Dates use the chosen locale.
- After adding texts, add their translations and check:
  `python3 vendor/plainshow/kit/bin/i18n-check.py lang/nl.json <code folders>`
  (also `sv.json`, or `en.json` for blog and split).
- The account menu is the same everywhere: Account, the app's own items,
  Language, Theme, then Sign out.
