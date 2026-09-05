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
| 2 | Alerts and real time (API) | next |
| 3 | Auth, organizations, REST API and status pages backend (API) | planned |
| 4 | App shell, dashboard and management screens (Web) | planned |
| 5 | Public status pages and polish (Web) | planned |

## Check engine

- The scheduler claims the due monitors every 10 seconds in one `UPDATE ... FOR UPDATE SKIP LOCKED` statement and queues one job per check.
- A target guard resolves the host, refuses private, reserved and metadata addresses, and pins the connection to the checked address on every redirect hop.
- Pure rules judge the result, confirm a state after 2 consecutive results with a fast recheck after 20 seconds, and plan the next check from its scheduled time.
- Raw results live in daily PostgreSQL partitions kept 30 days, hourly stats are kept 400 days.
- Incidents open at the first failure of a confirmed outage and resolve at the first success of the recovery.

## Run the API locally

Needs PHP 8.5 with `pdo_pgsql`, `intl`, `curl` and `mbstring`, and PostgreSQL 18 with the databases `uply` and `uply_test` owned by the user `uply`.

```bash
cd api
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

Set `MONITORING_ALLOW_PRIVATE_TARGETS=true` in `.env` to monitor local addresses. Then start the processes, each in its own terminal:

```bash
php artisan serve
php artisan schedule:work
php artisan queue:work --queue=alerts,realtime,checks,default
```

One dispatch tick and the storage maintenance can also run by hand:

```bash
php artisan checks:dispatch
php artisan monitoring:maintain
```

## Demo

The demo seeder adds an organization and a monitor on `http://127.0.0.1:8090/health`, checked every 30 seconds:

```bash
php artisan db:seed --class=DemoSeeder
```

Serve any page on that port, run the scheduler and a worker, then change the status code the page returns. On the dev PC an outage was confirmed 59 seconds after it started and the recovery 59 seconds after it, which is the next check, the 20 second recheck and the 10 second dispatch tick.

## Test

```bash
composer lint
composer analyse
composer test
```

`lint` runs Pint, `analyse` runs Larastan at the max level, `test` runs Pest on the PostgreSQL database `uply_test`.

## License

MIT, see `LICENSE`.
