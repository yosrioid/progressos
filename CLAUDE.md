# ProgressOS Assistant Handoff

Use this file as the first context source when continuing work in this repository with Claude Code or another coding assistant.

## Working Style

- Communicate with the user in Indonesian unless they explicitly switch language.
- Keep responses direct, pragmatic, and implementation-focused.
- Prefer making the change and verifying it over only proposing a plan.
- Keep edits scoped to the requested feature or bug. Avoid unrelated refactors and dependency churn.
- Do not revert user changes. Check `git status --short` before and after meaningful edits.
- Never push directly to `main`. Work on a feature branch.
- Current expected branch: `rewrite/vue-pinia-rest`.
- Before pushing or handing off substantial work, run relevant verification and report exact results.

## Primary Context

- Read `docs/PROJECT_CONTEXT.md` for the current branch state, implemented features, known gaps, and PR description template.
- Read `README.md` for setup, architecture, feature checklist, production notes, and latest known verification.
- API docs live in `docs/api.md` and `docs/openapi.yaml`.
- **Code standards**: `docs/code-standards.md` — aturan ApiResponse, unwrap, toast, bahasa, TypeScript.
- **Scaffold modul baru**: `docs/new-module.md` — checklist lengkap dari migration sampai nav sidebar.
- **UI patterns**: `docs/ui-patterns.md` — class `.card`, `.btn`, `.pill`, `.field`, dark mode, empty state, modal.

## Stack Snapshot

- Laravel 13, PHP 8.3+
- Sanctum session auth and bearer token support
- MySQL for local app use; tests use SQLite in memory
- Vue 3, Pinia, Vue Router, TypeScript
- Tailwind CSS 4, Vite
- Pest, Laravel Pint, Larastan/PHPStan
- Playwright for browser UI regression tests

If Node is not on PATH, use:

```bash
export PATH="/Users/oirsoy/Library/Application Support/Herd/config/nvm/versions/node/v22.22.3/bin:$PATH"
```

## Useful Commands

```bash
php artisan test
composer lint
composer analyse
npm run build
npm run test:e2e
php artisan route:list --path=api/v1
php artisan serve --host=127.0.0.1 --port=8000
npm run dev
```

For frontend changes, run at least `npm run build`; run Playwright when behavior, routing, forms, navigation, date display, or responsive UI changes.

## Backend Patterns

- API routes are split across `routes/api/auth.php`, `routes/api/tokens.php`, and `routes/api/v1.php`.
- Product API routes live under `/api/v1` with `auth:sanctum`, ability middleware, and throttling.
- Prefer dedicated controllers in `app/Http/Controllers/Api`.
- Use Form Request classes in `app/Http/Requests` for write validation.
- Return JSON through `App\Support\ApiResponse` where practical.
- Use API Resources in `app/Http/Resources` for record payloads.
- Preserve ownership checks through policies and `ownedBy($request->user())` query scopes.
- Use `App\Support\ApiQuery` for text search, sorting, pagination, and CSV hardening instead of reimplementing local variants.
- Keep services focused in `app/Services`; use them for aggregation, report generation, tag syncing, backup export/sync, and project resolution logic.
- For CRUD modules, keep naming consistent:
  - daily progress payload key: `entry`
  - work logs payload key: `log`
  - tasks payload key: `task`
  - learning payload key: `entry`
  - milestones payload key: `milestone`

## Frontend Patterns

- Vue entrypoint: `resources/js/app.ts`.
- App shell, global search, sidebar/mobile nav, quick capture, and shortcuts: `resources/js/vue/App.vue`.
- Routes: `resources/js/vue/router.ts`.
- Shared API client: `resources/js/vue/api.ts`.
- Generic record configs and form serialization: `resources/js/vue/records.ts`.
- Core generic views:
  - `resources/js/vue/views/Records.vue`
  - `resources/js/vue/views/RecordForm.vue`
  - `resources/js/vue/views/RecordDetail.vue`
- Prefer extending existing generic record config before creating module-specific duplicated screens.
- Keep TypeScript simple and explicit. Existing code uses small local types, plain objects, and Composition API.
- Keep UI restrained, dense, and work-focused. This is a personal operating system, not a marketing site.
- Reuse existing Tailwind class patterns and component structure. Do not introduce a design system dependency unless the feature clearly needs it.
- Avoid decorative UI changes that do not improve the workflow.
- Ensure mobile navigation and quick capture keep working after shell or route changes.

## Testing Expectations

- Backend feature changes: add or update Pest tests in `tests/Feature`.
- Service logic: add focused unit tests in `tests/Unit` when useful.
- Frontend behavior: update `tests/e2e/progressos.spec.ts` when user-visible flows change.
- Authentication-sensitive work should cover both successful and unauthorized cases where relevant.
- Security-related URL, CSV, auth, upload, or token changes need explicit tests.

## Current Product Direction

Prioritize improvements listed in `docs/PROJECT_CONTEXT.md` and `README.md`:

- Per-module default filter presets.
- Saved report snapshots and comparison history.
- Richer reference presentation by type.
- Metric-linked milestones and scheduled recalculation.
- Deeper timeline/activity layouts.
- Optional PDF export only if demand justifies a contained dependency.

## Handoff Checklist

Before ending a substantial task, include:

- Files changed.
- Verification commands run and whether they passed.
- Any tests skipped or not run, with reason.
- Any follow-up work that remains.

