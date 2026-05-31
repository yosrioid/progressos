# ProgressOS

ProgressOS is a Laravel/Inertia personal operating system for daily progress, work logs, learning sessions, milestones, global search, and weekly/monthly reports.

## Stack

- Laravel app skeleton `v13.8.0`, Laravel framework `v13.12.0`
- PHP `8.5.5` locally, compatible with Laravel 13's PHP `8.3+` requirement
- Inertia Laravel `v2.0.24`
- React `19`
- TypeScript `5.9`
- Tailwind CSS `4`
- Vite `8`
- MySQL primary database
- Redis configured through Laravel env settings
- Pest `4.7.0`
- Playwright `1.60.0` for browser UI regression tests

Package choices: Ziggy latest stable is `v2.6.2`; no stable `v3` exists. `pest-plugin-laravel` did not declare Laravel 13 compatibility at build time, so Pest is used directly with Laravel's base test case.

## Local Setup

```bash
cd /Users/oirsoy/Downloads/Laravel/progressos
cp .env.example .env
php artisan key:generate
mysql -uroot -e "create database if not exists progressos character set utf8mb4 collate utf8mb4_unicode_ci;"
composer install
npm install
php artisan migrate --seed
npx playwright install chromium
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

If Node is installed through Laravel Herd but not on PATH, use:

```bash
export PATH="/Users/oirsoy/Library/Application Support/Herd/config/nvm/versions/node/v22.22.3/bin:$PATH"
```

Demo login after seeding:

- Email: `test@example.com`
- Password: `password`

## Development

```bash
npm run dev
php artisan serve
php artisan test
npm run test:e2e
```

The default local database is MySQL. Tests use SQLite in-memory for speed while keeping migrations MySQL-safe and avoiding PostgreSQL-specific behavior.

## Production Readiness

ProgressOS is ready for local daily use. Before exposing it publicly, apply this baseline:

```bash
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example
LOG_LEVEL=warning
CACHE_STORE=redis
SESSION_DRIVER=redis
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=redis
MAIL_MAILER=smtp
```

Operational commands:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Run these processes on the server:

- Web server serving `public/` only.
- PHP-FPM or equivalent PHP process manager.
- `php artisan queue:work --tries=3 --timeout=90` supervised by systemd, Supervisor, or a platform worker.
- `php artisan schedule:run` every minute via cron if scheduled automation is added.
- MySQL backups with restore testing, not only backup creation.
- Redis with persistence enabled if Redis is used for cache, sessions, or queues.

Security checklist:

- Generate a unique `APP_KEY`; never reuse the local key.
- Keep `.env` outside version control and restrict file permissions.
- Use HTTPS only and set `SESSION_SECURE_COOKIE=true`.
- Use a real mail provider for password resets before inviting users.
- Keep `APP_DEBUG=false` in production.
- Point the web root to `public/`, not the project root.
- Back up uploaded avatars from `storage/app/public`.
- Rotate database, Redis, SMTP, and object storage credentials if they are ever exposed.

## Architecture

- `app/Models`: user-owned domain models for daily progress, work logs, learning entries, milestones, and report snapshots.
- `app/Http/Controllers`: Inertia controllers for auth, dashboard, CRUD modules, reports, profile, and search.
- `app/Http/Requests`: Form Request validation for core write paths.
- `app/Services`: focused services for dashboard aggregation, report generation, and tag syncing.
- `resources/js`: React 19 + TypeScript Inertia UI with a responsive sidebar layout, reusable UI primitives, chart-like activity bars, filters, forms, tables, and report views.
- `database/factories` and `database/seeders`: realistic demo data for everyday review workflows.
- `tests/Feature`: Pest coverage for auth, CRUD, dashboard, search, and report export paths.
- `tests/e2e`: Playwright coverage for login, dashboard quick-add, daily progress smart links, task status updates, and responsive mobile navigation/cards.

## Feature Checklist

- Authentication: register, login, logout, forgot password, reset password, profile update, password change, avatar upload, timezone, theme preference.
- Dashboard: today summary, task counts, blockers, weekly/monthly activity charts, streaks, latest entries, milestone snapshot.
- Daily Progress: CRUD, tags, search, date filters, tag filters, archive, duplicate previous day, detail view.
- Work Log: CRUD, categories, statuses, priorities, filters, summary counts, quick-add, bulk status update, tags, detail view.
- Learning Tracker: CRUD, categories, source types, weekly/monthly totals, grouped category totals, dashboard integration.
- Milestones: CRUD, progress bars, status management, overdue indicator, dashboard integration.
- Reports: weekly/monthly on-screen reports with real derived data and CSV export.
- Global Search: grouped, navigable results across daily progress, work logs, learning, and milestones.

## Verification

```bash
php artisan test
npm run build
npm run test:e2e
curl -I http://127.0.0.1:8000/login
```

Current verification passed:

- Pest: `12 passed`, `68 assertions`
- Vite production build: passed
- Playwright: `6 passed`, `2 skipped` across desktop Chromium and mobile Chromium projects. Skips are project-specific: desktop-only quick-add save and mobile-only responsive layout checks.
- Local HTTP smoke check: `/login` returned `200 OK`
- MySQL migration and seed: passed locally

## Known Limitations

- PDF export is intentionally omitted in the MVP; CSV export is implemented cleanly without adding a heavy PDF rendering dependency.
- Milestone current-value auto-updates are manual in the MVP. The next step is linking milestones to specific metrics or tags so progress can be recalculated from logs.
- Tests run on SQLite in memory. The actual application configuration and verified local run use MySQL.
- Playwright mobile coverage verifies responsive layout, mobile daily progress, and mobile task flows. Quick-add save is tested on desktop because Chromium mobile emulation produced unreliable hit-testing on the modal action button; the mobile sheet visibility is still covered.
- Production deployment still needs environment-specific infrastructure: HTTPS termination, supervised queue workers, database backups, Redis persistence, and a real SMTP provider.

## Next Improvements

- Add metric-linked milestones with scheduled recalculation.
- Add saved report snapshots and comparison history.
- Add keyboard shortcuts for quick-add and global search focus.
- Add richer chart components when a charting dependency is justified.
- Add optional PDF export via a small, well-contained service if reporting demand warrants it.
