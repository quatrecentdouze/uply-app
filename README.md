<p align="center"><img src="web/public/favicon.svg" width="96" alt="Uply"></p>
<h1 align="center">Uply</h1>
<p align="center">Uptime monitoring for websites and APIs, with alerts and public status pages.</p>
<p align="center"><img src="https://img.shields.io/badge/Laravel-13-555?style=flat" alt="Laravel 13"> <img src="https://img.shields.io/badge/React-19-555?style=flat" alt="React 19"> <img src="https://img.shields.io/badge/PostgreSQL-18-555?style=flat" alt="PostgreSQL 18"> <a href="LICENSE"><img src="https://img.shields.io/badge/license-MIT-555?style=flat" alt="MIT"></a></p>

## Features

- HTTP and HTTPS checks every 30 s to 1 h: status code, response time, DNS, connect, TLS and first byte timings
- `GET`, `HEAD`, `POST`, `PUT`, `PATCH`, request headers and body stored encrypted, keyword present or absent in the body
- Down and up confirmed after 2 results (1 to 10 per monitor), incidents opened and resolved on their own
- Alerts by email, Discord and Slack: down, up, reminders while an incident stays open, TLS certificate 14, 7 and 1 days before expiry
- Maintenance windows: checks keep running, no incident or alert, results left out of uptime
- Flapping: one alert after 5 state changes in 30 minutes, then one once stable for 30 minutes
- Real time dashboard over WebSockets (Reverb), uptime over 24 h, 7 days and 30 days
- Public status pages with 90 daily bars and the incidents of the last 14 days
- Organizations with invitations, roles `owner`, `admin`, `member`, `viewer` and TOTP two factor
- Target guard on every hop, redirects included: private, reserved and metadata addresses refused

## Screenshots

![Dashboard](.github/readme/dashboard.png)

![Monitor detail](.github/readme/monitor.png)

![Public status page](.github/readme/status-page.png)

## Stack

- API: Laravel 13, PHP 8.5, PostgreSQL 18, Reverb, Sanctum, Fortify
- Web: React 19, TypeScript 6, Vite 8, Tailwind CSS 4, shadcn/ui, Recharts 3, TanStack Router and Query
- CI: GitHub Actions, one workflow per project plus end to end tests

## Run locally

PHP 8.5 (`pdo_pgsql`, `intl`, `curl`, `mbstring`), PostgreSQL 18 with databases `uply` and `uply_test` owned by `uply`, Node 24, Mailpit on SMTP port 1025.

```bash
cd api
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed --class=DemoSeeder
```

In `api/.env`: `MONITORING_ALLOW_PRIVATE_TARGETS=true`, random values for `REVERB_APP_ID`, `REVERB_APP_KEY` and `REVERB_APP_SECRET`.

`DemoSeeder` runs in the `local` environment only: users `demo@uply.test` (owner) and `viewer@uply.test` (viewer) with the local password `password`, a monitor on `http://127.0.0.1:8090/health` every 30 s, an email channel.

Processes, one terminal each, in `api`:

```bash
php artisan serve
php artisan reverb:start --host=127.0.0.1 --port=6001
php artisan schedule:work
php artisan queue:work --queue=alerts,realtime,checks,default
```

Web, in `web`:

```bash
npm ci
npm run dev
```

Open `http://localhost:5173`. Vite proxies `/api` and `/sanctum` to `127.0.0.1:8000`.

## Configuration

Defaults live in `api/config/<family>.php`, the web forms read their limits from `GET /api/v1/meta`.

<details><summary>Main defaults</summary>

| Setting | Default | File |
|---|---|---|
| Check intervals | 30, 60, 120, 300, 600, 900, 1800, 3600 s, 60 s by default | `monitoring.php` |
| Request timeout | 10 s, up to 30 s per monitor, connect 5 s, 5 redirects | `monitoring.php` |
| Confirmation | 2 failures, 2 successes (1 to 10 per monitor), recheck after 20 s | `monitoring.php` |
| Dispatch | every 10 s, 500 monitors per tick | `monitoring.php` |
| Retention | raw results 30 days, hourly stats 400 days | `monitoring.php` |
| Monitors | 100 per organization | `monitoring.php` |
| Request | 20 headers, body up to 10 000 characters, body read up to 1 MiB | `monitoring.php` |
| Certificate alerts | 14, 7 and 1 days before expiry | `monitoring.php` |
| Flapping | 5 changes in 30 min, ends after 30 min without a change | `monitoring.php` |
| Alert delivery | 5 tries, backoff 10, 30, 60, 120 s, 1 message per second per webhook, 5 test sends per minute | `alerting.php` |
| Reminders | off by default, every 15 min to 24 h, 24 per incident and channel | `alerting.php` |
| Maintenance windows | up to 7 days, start up to 90 days ahead, 50 open per organization, 100 monitors each | `maintenance.php` |
| Status pages | 10 per organization, 50 monitors each, public page cached 30 s, 60 requests per minute per address | `status_pages.php` |
| Organizations | 10 owned per user, invitations valid 7 days | `organizations.php` |
| API | 120 requests per minute per user, 25 items per page (100 max) | `api.php` |

</details>

## Test

```bash
cd api
composer lint
composer analyse
composer test
```

```bash
cd web
npm run lint
npm run typecheck
npm test
npm run build
```

`npm run e2e` in `web`: Playwright on Chromium (`npx playwright install chromium`) against the running stack seeded with `DemoSeeder`, Mailpit API on `127.0.0.1:8025`.

## Limits

- HTTP and HTTPS only, from one location, no TCP, ping or cron checks
- No hosted version, no billing or plans
- English UI, dark theme only
- Four fixed roles, no teams or custom permissions
- Status pages on `/status/{slug}` only, no custom domain, up to 30 s behind (cache)
- A 30 s monitor confirms an outage 20 to 70 s after it starts (10 s dispatch tick)
- Queue, cache and sessions in PostgreSQL, no Redis: not sized for thousands of monitors
- Discord and Slack senders tested against faked HTTP only

## Status

Portfolio project. V1 done, API milestones 6 (check depth) and 7 (alert depth) done, milestone 7 screens (maintenance windows, reminders, flapping) next.

## License

MIT, see [LICENSE](LICENSE).
