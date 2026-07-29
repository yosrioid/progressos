# ProgressOS

ProgressOS is a Laravel REST API + Vue personal operating system for daily progress, work logs, learning sessions, milestones, global search, and weekly/monthly reports.

## Stack

- Laravel app skeleton `v13.8.0`, Laravel framework `v13.12.0`
- Laravel Sanctum `v4.3.2` for personal API access tokens
- Google API Client `v2.19.3` for service-account Google Sheets backup sync
- PHP `8.5.5` locally, compatible with Laravel 13's PHP `8.3+` requirement
- Vue `3.5.35`
- Pinia `3.0.4`
- Vue Router `5.1.0`
- Axios `1.16.1`
- TypeScript `5.9`
- Tailwind CSS `4`
- Vite `8`
- MySQL primary database
- Redis configured through Laravel env settings
- Pest `4.7.0`
- Playwright `1.60.0` for browser UI regression tests
- Laravel Pint for code style checks
- Larastan/PHPStan for static analysis

Package choices: the original Inertia/React client was replaced with a Vue SPA backed by same-origin REST endpoints. `pest-plugin-laravel` did not declare Laravel 13 compatibility at build time, so Pest is used directly with Laravel's base test case.

## Local Setup

> ⚠️ **DISCLAIMER: Project ini terdaftar di Laravel Herd. JANGAN gunakan `php artisan serve` untuk project ini.**
> Gunakan Herd untuk menjalankan project ini. Akses melalui `http://progressos.test` atau domain Herd yang sesuai.

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
php artisan test
composer lint
composer analyse
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

- `app/Models`: user-owned domain models for daily progress, work logs, learning entries, milestones, report snapshots, backup connections, backup sync schedules, and backup run history.
- `routes/api.php`: Laravel API entrypoint that loads `routes/api/auth.php`, `routes/api/tokens.php`, and `routes/api/v1.php`.
- `routes/web.php`: Vue SPA catch-all route only.
- `app/Http/Controllers/Api`: resource-specific same-origin JSON controllers for auth, dashboard, projects, capture, records, reports, CSV export, search, activity, saved views, and references.
- `app/Http/Requests`: Form Request validation for core write paths.
- `app/Http/Resources`: API response resources for core record details and mutations.
- `app/Support/ApiResponse.php`: standard JSON response envelope helpers for item, collection, and paginated responses.
- `app/Http/Middleware/EnsureApiTokenCan.php`: Sanctum token ability enforcement that still allows first-party browser sessions.
- `app/Support/ApiQuery.php`: shared search, sorting, pagination, and CSV hardening helpers.
- `app/Services`: focused services for dashboard aggregation, report generation, backup CSV export, and tag syncing.
- `docs/api.md` and `docs/openapi.yaml`: REST API usage notes and starter OpenAPI contract for external clients.
- `resources/js/vue`: Vue 3 + Pinia + TypeScript SPA with a responsive sidebar/mobile navigation, dashboard cards, record lists, project views, quick capture, and report views.
- `database/factories` and `database/seeders`: realistic demo data for everyday review workflows.
- `tests/Feature`: Pest coverage for auth, CRUD, dashboard, search, and report export paths.
- `tests/e2e`: Playwright coverage for login, dashboard quick-add, API-backed record pages, date formatting, and responsive mobile navigation/quick capture.

## Feature Checklist

- Authentication API: register, login, logout, forgot password, reset password, profile update, password change, avatar upload, timezone, and theme preference.
- Vue SPA shell: responsive sidebar, mobile bottom navigation, top search, dark mode, keyboard shortcuts, and quick capture modal.
- Dashboard: today summary, task counts, blockers, weekly/monthly activity, trends, latest work, and project snapshot.
- REST API modules: full CRUD for daily progress, work logs, tasks, learning entries, and milestones; project update; reports; CSV export; grouped search; list filtering, sorting, pagination, Sanctum token abilities, route rate limits, and ownership policies.
- Vue CRUD screens: list, create, detail, edit, delete, filters, sorting, and pagination flows for daily progress, work logs, tasks, learning entries, and milestones.
- Global search: dedicated grouped Vue search page across projects, daily progress, work logs, tasks, learning, and milestones.
- Project workspace: project-scoped add actions for work logs, tasks, and milestones.
- Saved views and references: filter presets, per-module quick-filter preset templates, persisted reference links on record details with auto-type detection from URL.
- Work logging flow: quick capture can create a done work log directly into an existing or newly resolved project.
- Reports: weekly/monthly on-screen reports with Chart.js bar charts, period picker, period-over-period delta chart, trend comparison, CSV and PDF export, and saved snapshots.
- Activity timeline: full audit log with server-side type and date range filters, Today/This week/This month presets, and type-count summary stats.
- Notifications: in-app notification bell with daily digest email via queue (task overdue, due soon, habit reminders, milestone completion, habit streaks).
- Milestone auto-tracking: source_type links to work log count/minutes, learning minutes, progress streak, or task count; source_filter keyword narrows which records count; daily recalculation via scheduler.
- Configuration: encrypted Google Sheets service account settings, dynamic daily/weekly/monthly backup sync rows per module, manual run-now Google Sheets sync with local CSV backup artifact, scheduled due-sync command, and backup run history.

## Verification

```bash
php artisan test
npm run build
npm run test:e2e
curl -I http://127.0.0.1:8000/login
```

Current verification passed (2026-06-27):

- Pest: `38 passed`, `357 assertions`
- Pint: passed
- Larastan/PHPStan: passed
- Vite production build: passed
- Playwright: `7 passed`, `3 skipped` across desktop Chromium and mobile Chromium projects. Skips are project-specific viewport checks.
- Local HTTP smoke check: `/login` returned `200 OK`
- MySQL migration and seed: passed locally

## Known Limitations

- Backup sync uses Google service account credentials to append rows to Google Sheets and also writes a local CSV backup artifact. The target spreadsheet must be shared with the configured service account email.
- Tests run on SQLite in memory. The actual application configuration and verified local run use MySQL.
- Playwright coverage verifies desktop quick-add, API-backed records, grouped global search, record filters, mobile navigation, mobile quick capture, Vue form flows, and date formatting.
- Production deployment still needs environment-specific infrastructure: HTTPS termination, supervised queue workers, database backups, Redis persistence, and a real SMTP provider.
- Email notification digest requires a working SMTP provider configured in `.env` (`MAIL_MAILER`, `MAIL_HOST`, etc.) and a running queue worker (`php artisan queue:work`).
