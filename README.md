# Yandex.Maps Reviews — Laravel + Vue

A small app that connects a Yandex.Maps organisation card by its link and shows
its reviews, rating and counters. Registration is not part of the app — there is
a single seed user; authentication is a Sanctum SPA (cookie) flow.

> **The parser is the heart of this task.** Yandex has no public API, protects
> its pages against bots and lazy-loads reviews on scroll. How that is handled —
> and how the code notices when it stops working — is described in detail below.

---

## What it does

- **Login** (single seed user, Sanctum SPA cookie auth).
- **Settings screen** — paste a link to an organisation on Yandex.Maps; the link
  is validated and saved; parsing starts automatically.
- **Organisation screen**
  - average rating;
  - **two distinct counters** — number of *ratings* (`оценки`) and number of
    *reviews* (`отзывы`) — kept separate, as required;
  - all available reviews (Yandex exposes roughly the last ~600), pulled in full;
  - each review: author, date, text, rating;
  - **pagination, 50 per page**, switching pages without a full reload;
  - live progress while the background parse runs, and clear error states.

---

## Architecture

```
              ┌──────────────┐      /api, /sanctum (fastcgi)     ┌──────────────┐
  browser ───▶│    nginx     │──────────────────────────────────▶│  app (php-fpm)│
              │ (SPA + proxy)│                                    │   Laravel API │
              └──────┬───────┘                                    └───┬───────┬──┘
                     │ static Vue SPA                                  │       │
                     ▼                                          dispatch job   │ Eloquent
                (Vue 3 build)                                         ▼        ▼
                                                               ┌──────────┐  ┌───────┐
                                                     queue ───▶│  queue   │  │ MySQL │
                                                     (redis)   │  worker  │  └───────┘
                                                               └────┬─────┘
                                                                    │ HTTP (NDJSON stream)
                                                                    ▼
                                                            ┌────────────────┐
                                                            │    scraper     │
                                                            │ Node+Playwright│──▶ Yandex.Maps
                                                            │ headless Chrome│
                                                            └────────────────┘
```

Key boundaries:

- **Controllers stay thin.** All scraping / external-source logic lives behind a
  `ReviewParser` interface (`app/Services/Yandex`). Controllers and the queue job
  depend only on that interface, never on scraping details.
- **Scraping runs out of process.** Laravel does not launch a browser itself; it
  calls the **scraper** micro-service, which owns Playwright/Chromium. This keeps
  PHP memory-light and lets the browser layer scale/restart independently.
- **The parse is a queued job**, never done inside an HTTP request.

### Stack

| Layer     | Tech                                                        |
|-----------|-------------------------------------------------------------|
| Backend   | Laravel 12, PHP 8.3, Sanctum (SPA auth), Eloquent, Queue     |
| Frontend  | Vue 3 (Composition API), Vue Router, Pinia, Vite, axios      |
| Scraper   | Node 20, Playwright (headless Chromium), Express (NDJSON)    |
| Infra     | MySQL 8, Redis 7, nginx, Docker Compose                      |

---

## Quick start (Docker)

```bash
docker compose up --build
# open http://localhost:8080
# login: demo@example.com / password
```

This brings up MySQL, Redis, the Laravel API (php-fpm), a queue worker, nginx
(serving the built SPA and proxying the API), and the Playwright scraper. The
`app` container migrates and seeds on boot. In Docker the parser runs in
**`headless`** mode against live Yandex.

> If `docker compose` is missing, install Docker's Compose v2 plugin
> (`docker-compose-plugin`). The older standalone `docker-compose` works too.

### Run it without a browser (fixture mode)

The parser has a **`fixture`** driver that replays a bundled real snapshot of an
organisation (captured from a live card — Twins Garden, 300 reviews). It exercises
the entire app — auth, queue, pagination, snapshots, UI — with **no network and no
browser**. It is the default for local dev and CI, and goes through the exact same
validation and storage path as the real parser.

---

## Local development (without Docker)

Requires PHP 8.2+, Composer, Node 20+. MySQL optional — SQLite works too.

```bash
# 1. Backend
cd backend
cp .env.example .env
# For the simplest run, use SQLite + the fixture parser:
#   set DB_CONNECTION=sqlite  and  touch database/database.sqlite
#   set QUEUE_CONNECTION=database, YANDEX_PARSER_DRIVER=fixture
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve --port=8000        # API on :8000
php artisan queue:work               # in a second terminal

# 2. Frontend
cd ../frontend
npm install
npm run dev                          # SPA on :5173, proxies /api → :8000

# open http://localhost:5173  (demo@example.com / password)

# 3. Scraper (only needed for YANDEX_PARSER_DRIVER=headless)
cd ../scraper
npm install && npx playwright install chromium
node src/index.js                    # on :3000
# quick manual test:
node src/cli.js "https://yandex.ru/maps/org/twins_garden/192990200894/" 120
```

### Key environment variables (`backend/.env`)

| Variable                   | Meaning                                                        |
|----------------------------|----------------------------------------------------------------|
| `YANDEX_PARSER_DRIVER`     | `fixture` (replay snapshot) or `headless` (real scraper)        |
| `YANDEX_SCRAPER_URL`       | Base URL of the scraper service                                |
| `YANDEX_MAX_REVIEWS`       | Safety cap on reviews pulled per organisation (default 600)     |
| `SANCTUM_STATEFUL_DOMAINS` | Domains allowed to use cookie auth (the SPA origin)            |
| `SEED_USER_EMAIL/PASSWORD` | Credentials of the single seed user                            |

---

## The parser

### Chosen approach: headless browser (and why)

Getting the reviews requires two things: reading the organisation card, and
paging through the lazily-loaded reviews. I looked at both realistic options.

**Option A — replay Yandex's internal JSON endpoint.**
The reviews page is server-rendered with the app state embedded in a
`<script class="state-view">` blob. That blob already contains the org meta
(rating, both counters) **and the first ~50 reviews**, with no anti-bot friction —
a plain HTTP GET is enough. Further pages come from an internal endpoint,
`/maps/api/business/fetchReviews`.

The catch is that this endpoint is **signed**. A real request carries a rotating
`csrfToken`, a `sessionId`, and an `s=<number>` signature computed in-page. I
verified this directly: calling `fetchReviews` with only a fresh `csrfToken`
returns `400 Bad Request` — the `s` signature is mandatory and is generated by
Yandex's own JavaScript. Reproducing that signing server-side is possible but
extremely brittle: it breaks the moment Yandex changes the algorithm or the token
scheme, which they do often.

**Option B — headless browser (chosen).**
Drive a real headless Chromium, let *the page itself* issue its own signed
`fetchReviews` requests as we scroll, and simply **harvest the JSON responses**.
We never reproduce the signature — the page does it for us.

| Criterion              | Internal JSON (A)                          | Headless browser (B) ✅            |
|------------------------|--------------------------------------------|-----------------------------------|
| Speed / resources      | **Fast, light** (no browser)               | Heavier (a Chromium per parse)    |
| Fragility to signing   | **High** — must reproduce `s`/session      | **Low** — page signs its own calls|
| Fragility to markup    | Medium (JSON keys can change)              | Medium (same)                     |
| Anti-bot resistance    | Low — trivially fingerprinted              | Higher — a real browser           |
| Implementation effort  | High (reverse-engineer signing)            | Moderate                          |

For a source that actively fights scrapers and changes its signing, **robustness
wins over raw speed**, so the implemented parser is the headless browser. To keep
the best of A, the scraper still reads the embedded `state-view` for the org meta
and the first page (cheap and reliable) and only uses the browser's scrolling to
pull the rest.

**How the scraper pulls everything** (`scraper/src/yandexScraper.js`):

1. Open the organisation's `/reviews/` page in headless Chromium (ru locale,
   realistic UA, `navigator.webdriver` stripped).
2. Parse `<script class="state-view">` → org meta + first ~50 reviews.
3. Register a response listener for the page's own `fetchReviews` XHRs
   (reviews arrive as `{data:{reviews:[…]}}`), de-duplicated by review id.
4. **Scroll the reviews list with real wheel events** — a programmatic
   `scrollTop` alone does *not* trigger Yandex's infinite scroll; a genuine
   `mouse.wheel` over the list does. Repeat, with polite jitter, until we have
   them all (or hit `YANDEX_MAX_REVIEWS`), reporting progress as we go.

This is verified working against live Yandex — pulling hundreds of reviews with
every field (author, rating, text, date) populated.

### 1. Resilience to markup / endpoint changes — how the parser knows it broke

A scraper's worst failure is the *silent* one: Yandex changes a key, a selector
returns `null`, and we cheerfully store an empty or garbage result. Every parse
result is therefore run through a single self-check, `ResponseValidator`
(`backend/app/Services/Yandex/ResponseValidator.php`), which asserts the
invariants a healthy response must satisfy and throws a **typed** exception when
one fails, instead of returning junk:

- **no organisation object / no id** → `MarkupChangedException` (the page
  structure we depend on is gone);
- **neither a rating nor any counter present** → `MarkupChangedException`;
- **rating outside 0–5** → `MarkupChangedException` (parsed the wrong field);
- **the counter says N > 0 reviews but we extracted none** →
  `EmptyResultException` (lazy-loading or the reviews endpoint broke — do **not**
  persist a misleading "0 reviews").

The scraper side does the same for HTTP 403/429 and captcha pages
(`BlockedException`). Each exception carries a short machine reason
(`markup_changed`, `blocked`, `empty_result`, …). The result is visible in three
places: the **logs**, the organisation's **`parse_status` / `parse_error_reason`**
in the DB, and a **clear message in the UI** — never a silent empty page. Changes
that can't succeed on retry (`markup_changed`) fail fast; transient ones are
retried (see below).

### 2. Justification of the approach

See the A-vs-B comparison above. Short version: the internal JSON endpoint is
faster but requires reproducing Yandex's request signature (`s`/session), which is
the single most fragile thing you can depend on. The headless browser lets the
page sign its own requests, trading CPU/RAM for resilience — the right trade for a
source that actively changes its anti-bot layer.

### 3. Scale & background processing

Parsing is a **queued job** (`ParseOrganizationReviews`), never inline in a
request. Posting a link returns immediately with status `queued`; the UI polls
`/status` and shows a progress bar fed by the job.

- **Retries with backoff:** `tries = 3`, `backoff = [30, 120, 300]s`. Transient
  failures (network, soft blocks) are retried; `markup_changed` is not (pointless).
- **Progress:** the scraper streams NDJSON progress events; the job persists them
  to `parse_progress`.
- **At the scale in the brief** (~50 branches × ~600 reviews) you simply dispatch
  one job per organisation onto the queue and run several workers; each branch is
  independent and isolated. With Redis as the queue backend (as configured in
  Docker), throughput scales by adding workers. Per-organisation throttling keeps
  any single card from hammering Yandex (see anti-ban).

### 4. Anti-ban on volume

Implemented today: realistic `ru-RU` browser context, real UA, `navigator.webdriver`
stripped, randomised pauses between scrolls, a per-parse review cap, and explicit
detection of 403/429/captcha (surfaced as `blocked`, which backs off rather than
retrying instantly).

For regular large-scale parsing, the levers are, in priority order:

1. **Throttle & pace** — cap concurrent parses, add jitter and per-domain rate
   limits (Laravel's `RateLimiter` / `Redis::throttle` around the job), and
   **exponential backoff** when blocks appear.
2. **Rotate identity** — a pool of residential/mobile **proxies** and UAs, one
   sticky proxy per organisation for a run (the scraper's `newContext` is the
   single place a `proxy:{server,username,password}` option plugs in).
3. **Spread over time** — schedule re-parses off-peak and stagger branches
   instead of bursting.
4. **Detect & react to bans** — on captcha/429, pause that proxy, back off, retry
   later; alert if the ban rate crosses a threshold.
5. **Cache aggressively** — never re-parse more often than the data changes
   (reviews move slowly), which is the cheapest way to cut request volume.

### 5. Idempotency & history (было → стало)

Re-parsing the same organisation **never creates duplicates**. Reviews are upserted
on `(organization_id, external_id)`; a per-review `content_hash` distinguishes
*new* from *changed* from *unchanged* (`ReviewStore`).

Every successful parse also writes an **`organization_snapshots`** row: rating,
both counters, how many reviews were scraped, and how many were **added** /
**updated** versus the previous parse, plus a hash of the whole review set. That
aggregate history is what lets you answer *what changed between two parses*
(было → стало) cheaply, without storing a full copy of every review each time.
`GET /organizations/{id}/snapshots` returns this timeline.

---

## Pagination decision (what to load when)

The parse result is **cached in our own DB**, and pagination reads from there — we
do **not** re-scrape Yandex on every page turn. Rationale:

- Yandex is slow, rate-limited and anti-bot-guarded — scraping per page-turn would
  be fragile and rude, and page turns would take seconds.
- ~600 reviews is a few hundred KB — trivial to store, and the source changes
  slowly, so a cached snapshot is fresh enough between explicit re-parses.

So: **scrape once (in the background), serve pages from an indexed DB query.** Page
turns are instant and hit only our own database; `Обновить` triggers a fresh parse
when you want up-to-date data.

---

## API

All under `/api`, cookie-authenticated (Sanctum SPA) except `POST /login`.

| Method | Path                                | Purpose                              |
|--------|-------------------------------------|--------------------------------------|
| POST   | `/login`                            | Log in (sets session cookie)         |
| POST   | `/logout`                           | Log out                              |
| GET    | `/user`                             | Current user                         |
| GET    | `/organizations`                    | List connected organisations         |
| POST   | `/organizations`                    | Save a link + start parsing          |
| GET    | `/organizations/{id}`               | Organisation with rating & counters  |
| POST   | `/organizations/{id}/parse`         | Re-parse                             |
| GET    | `/organizations/{id}/status`        | Poll parse status / progress         |
| GET    | `/organizations/{id}/reviews?page=` | Reviews, 50 per page                 |
| GET    | `/organizations/{id}/snapshots`     | Parse history (было → стало)         |

(SPA also calls `GET /sanctum/csrf-cookie` once before logging in.)

## Data model

- **organizations** — `url`, `yandex_id` (unique), `title`, `address`,
  `categories`, `rating`, `ratings_count`, `reviews_count`, parse lifecycle
  (`parse_status`, `parse_progress`, `parse_error_reason`, `last_parsed_at`).
- **reviews** — `organization_id`, `external_id`, `author`, `rating`, `text`,
  `reviewed_at`, `content_hash`; unique `(organization_id, external_id)`.
- **organization_snapshots** — aggregate per parse: `rating`, both counters,
  `reviews_scraped`, `reviews_added`, `reviews_updated`, `reviews_hash`.

## Tests

```bash
cd backend && php artisan test
```

25 tests cover URL validation, the response self-check (markup-change / empty-result
detection), auth, save-and-parse, pagination (50/page), idempotency and snapshot
history. They run against the fixture driver with a sync queue.

---

## What I'd do with more time

- **Run the scraper concurrency for real at ~50 branches** — add per-domain
  `Redis::throttle`, a proxy pool wired into `newContext`, and a small dashboard
  of parse jobs (progress, last error, ban rate).
- **Diff view in the UI** — surface the snapshot history as an actual была→стало
  timeline (rating/counter sparkline, "+N new reviews since last parse").
- **Short-link resolution** — follow `/maps/-/xxxx` redirects in the scraper to
  fill in the org id.
- **More parser hardening** — schema-version the `state-view` shape and alert on
  drift; store the raw payload for a few parses to debug markup changes quickly.
- **E2E tests** for the scraper against recorded HAR fixtures, and a small
  contract test between the Laravel `HeadlessBrowserParser` and the scraper's
  NDJSON protocol.
- **Auth niceties** — rate-limit feedback, "remember me", CSRF-refresh on 419.
