# Uply

Uply is a monitoring web app. Developers and companies watch their websites and APIs in real time, get alerted by email, Discord or Slack when a service goes down or comes back, and share public status pages.

It is a portfolio project built like a real multi tenant product.

## Stack

- API: Laravel 13, PHP 8.5, PostgreSQL 18, Laravel Reverb
- Web: React 19, TypeScript 6, Vite 8, Tailwind CSS 4, shadcn/ui, Recharts 3
- CI: GitHub Actions, one workflow per project

## Repository

| Folder | Content |
|---|---|
| `api/` | Laravel REST API, check engine, queue workers and scheduler |
| `web/` | React SPA, from milestone 4 |

## Milestones

| # | Milestone | State |
|---|---|---|
| 1 | Monitoring engine (API) | done |
| 2 | Alerts and real time (API) | done |
| 3a | Auth, two factor, account, organizations, members, invitations, roles (API) | done |
| 3b | Product REST API and status pages backend (API) | done |
| 4 | App shell, dashboard and management screens (Web) | next |
| 5 | Public status pages and polish (Web) | planned |

## Check engine

- The scheduler claims the due monitors every 10 seconds in one `UPDATE ... FOR UPDATE SKIP LOCKED` statement and queues one job per check.
- A target guard resolves the host, refuses private, reserved and metadata addresses, and pins the connection to the checked address on every redirect hop.
- Pure rules judge the result, confirm a state after 2 consecutive results with a fast recheck after 20 seconds, and plan the next check from its scheduled time.
- Raw results live in daily PostgreSQL partitions kept 30 days, hourly stats are kept 400 days.
- Incidents open at the first failure of a confirmed outage and resolve at the first success of the recovery.

## Alerts and real time

- An opened incident creates one `down` delivery per enabled channel linked to the monitor, a resolved one an `up` delivery. A unique key per channel, incident and kind means a replayed event never sends twice.
- Each delivery is a queued job on the `alerts` queue that carries its message, so a retry sends the same text. Discord gets an embed that pings nobody, Slack an attachment with escaped texts and reader time zones, email a Markdown mail.
- Pure rules read each answer: 2xx is sent, 429 waits the delay the provider asks, 401, 403, 404 and 410 disable the channel, 5xx and transport errors retry 5 times with a growing delay. At most one message per second goes to a webhook, without spending a try.
- Webhook URLs are stored encrypted and never logged.
- Reverb broadcasts `monitor.checked`, `monitor.state-changed`, `monitor.changed`, `incident.opened` and `incident.resolved` on the private channel `organization.{id}` through the `realtime` queue.

## Auth and organizations

- The SPA logs in with a Sanctum session cookie. Fortify runs headless under `/api/v1/auth`: sign up, a verified email before anything else, password reset, and TOTP two factor with recovery codes, set up behind a confirmed password.
- Mails are queued notifications whose links point at the web app, the verification link is a signed URL under the same origin.
- An organization has four fixed roles, `owner`, `admin`, `member` and `viewer`, mapped to abilities in `config/organizations.php`. Every route goes through a policy: a non member gets 404, a member without the ability 403. The last owner can neither leave nor be demoted, an admin never grants owner.
- Invitations go by mail and expire after 7 days. Only the SHA-256 of their token is stored.
- Domain errors answer `{"message": "...", "code": "last_owner"}` with a stable code. The API allows 120 requests per minute per user, per address for guests.
- `POST /api/broadcasting/auth` signs the private channel `organization.{id}` for its members only.
- `GET /api/v1/meta` gives the forms their limits: intervals, timeouts, methods, roles and abilities, channel types and lengths.

## Product API

- Monitors live under `/api/v1/organizations/{organization}/monitors`, at most 100 per organization. A URL with another scheme or a literal private, reserved or metadata address is refused at once, names are checked by the target guard at each check. Changing the URL, the method, the accepted codes or the redirects checks at once and restarts the confirmation, while the state and an open incident stay until new results confirm. Pause and resume are a `PATCH` of `is_paused`.
- Raw checks come newest first with a cursor on the check time, inside the 30 day retention.
- Stats and the organization overview cover `24h`, `7d` and `30d` with 24 hourly, 28 six hour or 30 daily buckets in UTC. The 24 hour uptime comes from the raw results, the longer ones from the hourly stats, and a bucket without checks is `null`. Uptime is rounded down to a hundredth, so one failure never shows 100%.
- Incidents filter by state and monitor, the detail adds the timeline of the opening, the alerts and the resolution.
- Alert channels are created per type. A webhook URL goes in and never comes out: the API answers a hint with the host and the last four characters, and a `PATCH` without a URL keeps the old one. A test send answers 202 with its delivery, 429 past 5 per minute.

## Status pages

- An organization keeps up to 10 pages with up to 50 monitors each, in order and with their own display names.
- `GET /api/v1/public/status-pages/{slug}` needs no session. It gives the overall state, the 90 day uptime and 90 daily bars of each monitor, and the incidents of the last 14 days. It never shows a URL, an error message or a failure kind. Answers are cached 30 seconds and limited to 60 per minute per address.

## Run the API locally

Needs PHP 8.5 with `pdo_pgsql`, `intl`, `curl` and `mbstring`, and PostgreSQL 18 with the databases `uply` and `uply_test` owned by the user `uply`.

```bash
cd api
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

Set `MONITORING_ALLOW_PRIVATE_TARGETS=true` in `.env` to monitor local addresses, and give `REVERB_APP_ID`, `REVERB_APP_KEY` and `REVERB_APP_SECRET` random values of your own. Mail goes to the SMTP server of `.env`, Mailpit on port 1025 locally. Then start the processes, each in its own terminal:

```bash
php artisan serve
php artisan reverb:start --host=127.0.0.1 --port=6001
php artisan schedule:work
php artisan queue:work --queue=alerts,realtime,checks,default
```

One dispatch tick, the storage maintenance and a test alert for one channel can also run by hand:

```bash
php artisan checks:dispatch
php artisan monitoring:maintain
php artisan alerting:test 01jexamplechannelid0000000
```

## Demo

The demo seeder runs in the `local` environment only. It adds the verified users `demo@uply.test` (owner) and `viewer@uply.test` (viewer) with the local password `password`, an organization, a monitor on `http://127.0.0.1:8090/health` checked every 30 seconds, and an email channel to `demo@uply.test`. It adds a Discord and a Slack channel when `DEMO_ALERT_CHANNELS_FILE` points to a local JSON file with their webhook URLs:

```bash
php artisan db:seed --class=DemoSeeder
```

Serve any page on that port, run the scheduler, a worker and Reverb, then change the status code the page returns. On the dev PC an outage was confirmed 59 seconds after it started, which is the next check, the 20 second recheck and the 10 second dispatch tick. The alert mail and the `incident.opened` event both arrived less than a second after the confirming check.

## Test

```bash
composer lint
composer analyse
composer test
```

`lint` runs Pint, `analyse` runs Larastan at the max level, `test` runs Pest on the PostgreSQL database `uply_test`.

## OpenAPI

`api/openapi.json` describes every route, Fortify's included. Scramble writes it from the routes, form requests and resources:

```bash
php artisan scramble:export --path=openapi.json
```

A test exports the document again and fails when the committed file is stale. Locally Scramble also serves a browsable copy at `/docs/api`.

## License

MIT, see `LICENSE`.
