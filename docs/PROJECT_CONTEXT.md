# ProgressOS Project Context

This file is the handoff source of truth for continuing ProgressOS when chat context is compacted or a new coding assistant session starts.

## Non-Negotiable Workflow

- Never push directly to `main`.
- Always work on a feature branch.
- Current active branch: `rewrite/vue-pinia-rest`.
- Push only the feature branch, then provide a PR description.
- Before push, run the relevant verification commands and mention the result.
- If changing frontend behavior, run Playwright when practical.

## Repository

- GitHub SSH remote: `git@github.com:yosrioid/progressos.git`
- Current PR branch URL:
  `https://github.com/yosrioid/progressos/pull/new/rewrite/vue-pinia-rest`

## Current Stack

- Laravel 13
- PHP 8.3+
- MySQL for local app development
- Redis configured by env when needed
- Vue 3
- Pinia
- Vue Router
- TypeScript
- Tailwind CSS 4
- Vite
- Pest
- Playwright

The app was originally built with React/Inertia, then rewritten to Vue SPA + same-origin REST API. React/Inertia frontend files and Inertia dependencies were removed on branch `rewrite/vue-pinia-rest`.

## Architecture Snapshot

- `routes/web.php`
  - Hosts `/api` auth endpoints.
  - Hosts `/api/v1` product endpoints.
  - Uses catch-all SPA route for Vue.
- `app/Http/Controllers/Api/AuthController.php`
  - JSON/session auth for login, register, logout, profile update, password update.
- `app/Http/Controllers/Api/ProgressApiController.php`
  - Dashboard, projects, quick capture, daily progress, work logs, tasks, learning, milestones, reports, CSV export, and search.
  - This controller is now functional but getting large. A future refactor can split it by resource.
- `resources/js/app.ts`
  - Vue app entrypoint.
- `resources/js/vue/App.vue`
  - Authenticated shell, sidebar, mobile nav, top search, quick capture modal.
- `resources/js/vue/router.ts`
  - Vue Router routes for dashboard, projects, records, forms, details, reports.
- `resources/js/vue/records.ts`
  - Shared record config for core module forms and serialization.
- `resources/js/vue/views/RecordForm.vue`
  - Generic create/edit form for core modules.
- `resources/js/vue/views/RecordDetail.vue`
  - Generic detail/delete view for core modules.
- `resources/js/vue/views/Records.vue`
  - Generic list view for core modules.

## Implemented

- Vue + Pinia + REST foundation.
- React/Inertia UI removed.
- Inertia/Ziggy backend packages removed.
- Dashboard page.
- Project list and project detail.
- Reports page with period picker and CSV export action.
- Quick capture modal.
- Core REST CRUD:
  - daily progress
  - work logs
  - tasks
  - learning entries
  - milestones
- Vue CRUD screens:
  - list
  - create
  - detail
  - edit
  - delete
- Record filtering, sorting, and pagination UI.
- Dedicated grouped global search page.
- Profile/settings page for profile and password updates.
- Project-scoped add actions for tasks, work logs, and milestones.
- Markdown-style link rendering in record details for `[label](url)` and raw public URLs.
- CSV report export.
- Security headers.
- Safe URL validation test.
- Pest tests for auth, CRUD, dashboard/report/search paths, update/delete flows, CSV hardening.
- Playwright tests for login, quick capture, record pages, grouped search, filters, date formatting, mobile nav, and Vue daily progress form flow.

## Verification Commands

Use these before pushing meaningful changes:

```bash
php artisan test
npm run build
npm run test:e2e
php artisan route:list --path=api/v1
```

Last known passing state:

- `php artisan test`: 12 passed, 77 assertions
- `npm run build`: passed
- `npm run test:e2e`: 7 passed, 3 skipped
- `php artisan route:list --path=api/v1`: 34 routes

If Node is not on PATH under Laravel Herd:

```bash
export PATH="/Users/oirsoy/Library/Application Support/Herd/config/nvm/versions/node/v22.22.3/bin:$PATH"
```

## Known Gaps

- Forgot password and reset password screens have not been rebuilt in Vue yet.
- Avatar upload API/UI still needs to be rebuilt.
- Saved views still need to be rebuilt in Vue.
- Record lists have filtering/sorting/pagination, but module-specific filter presets can still improve.
- Reports are improved but still use lightweight inline charting instead of a dedicated chart library.
- `ProgressApiController` should eventually be split into resource controllers.
- Rich linked-reference persistence UX is not yet rebuilt in Vue.

## Recommended Next Work

1. Rebuild forgot/reset password in Vue + JSON endpoints.
2. Rebuild avatar upload in Vue + API endpoint.
3. Add saved views and per-module filter presets.
4. Split `ProgressApiController` into resource-specific controllers after behavior settles.
5. Improve reports UI further:
   - previous-period comparison
   - better chart presentation
6. Improve linked references:
   - persisted references UI
   - safe public URL validation
   - compact link chips for saved references

## PR Description Template

```md
## Summary

Describe the branch in one or two sentences.

## Changes

- Change 1
- Change 2
- Change 3

## Verification

- `php artisan test`
- `npm run build`
- `npm run test:e2e`

## Notes

Mention limitations, follow-up work, or migration notes.
```

## Current PR Description

```md
## Summary

Rewrite ProgressOS from React/Inertia to a Vue SPA backed by same-origin REST API endpoints, then revamp core frontend flows with CRUD screens, filters, grouped search, profile settings, project-scoped actions, and report controls.

## Changes

- Replace React/Inertia entrypoint with Vue 3, Pinia, Vue Router, Axios, and Vite Vue plugin
- Remove old React/Inertia UI files and unused Inertia controllers/middleware
- Remove Inertia/Ziggy/React dependencies
- Add REST API controllers for auth, dashboard, projects, quick capture, records, reports, CSV export, and search
- Add full CRUD REST endpoints for daily progress, work logs, tasks, learning entries, and milestones
- Add Vue list, create, detail, edit, delete, filter, sort, and pagination screens for core modules
- Add grouped global search page across projects, progress, work logs, tasks, learning, and milestones
- Add profile/settings page for profile and password updates
- Add project-scoped add actions for work logs, tasks, and milestones
- Add markdown-style link rendering in record details
- Improve report UI with period picker, CSV action, category bars, blockers, and active project summaries
- Add Vue dashboard, projects, project detail, records, reports, login, and register screens
- Update Pest feature tests to validate REST API flows and update/delete behavior
- Update Playwright tests for Vue UI, quick capture, global search, filters, mobile navigation, date formatting, and daily progress form flow
- Update README with new stack, architecture, verification, and known limitations

## Verification

- `php artisan test`
- `npm run build`
- `npm run test:e2e`
- `php artisan route:list --path=api/v1`

## Notes

Remaining work: forgot/reset password Vue screens, avatar upload, saved views/filter presets, persisted references UI, richer report comparisons, and controller splitting.
```
