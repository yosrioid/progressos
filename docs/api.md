# ProgressOS REST API

ProgressOS exposes JSON endpoints under `/api` for the Vue client and future local integrations such as CLI tools, Raycast, or small automation clients.

## Authentication

Browser sessions use the existing auth endpoints:

- `POST /api/register`
- `POST /api/login`
- `POST /api/logout`
- `GET /api/me`

External tools can use personal API tokens.

### Create Token

`POST /api/tokens`

```json
{
  "name": "Raycast",
  "abilities": ["read", "capture"],
  "expires_at": "2026-12-31T23:59:59+07:00"
}
```

Response includes `plain_text_token` once. Store it securely.

If `abilities` is omitted, new tokens receive `read`, `write`, `capture`, and `reports`. Use `tokens` only for trusted tools that need to manage tokens. Use `*` only for local development or fully trusted automation.

### Use Token

Send the token as a bearer token:

```http
Authorization: Bearer pos_xxx
Accept: application/json
```

### Revoke Token

`DELETE /api/tokens/{id}`

Revoked tokens stop authenticating immediately.

## Core Endpoints

- `read`: `GET /api/me`, dashboard, activity, projects, record lists/details, search, saved views
- `write`: profile updates, project updates, CRUD for daily progress, work logs, tasks, learning, milestones, saved views, references
- `capture`: `POST /api/v1/quick-capture`
- `reports`: `GET /api/v1/reports/{weekly|monthly}` and CSV exports
- `tokens`: `GET|POST|DELETE /api/tokens`

## Response Shape

JSON responses use a standard envelope:

- Item responses include `data`, `message` when useful, and a backwards-compatible named key such as `task`, `entry`, `log`, or `project`.
- Collection responses include `data` and the existing named collection key such as `projects` or `saved_views`.
- Paginated responses include `data`, `meta`, `links`, and the existing Laravel paginator key such as `tasks`, `logs`, `entries`, or `milestones`.

The named keys keep the Vue client stable while external clients can use the consistent `data` / `meta` / `links` contract.

## Quick Capture

`POST /api/v1/quick-capture`

```json
{
  "type": "work_log",
  "title": "Ship API token support",
  "project_name": "ProgressOS",
  "duration_minutes": 45,
  "notes": "Added bearer token auth for external tools."
}
```

Supported `type` values:

- `task`
- `blocker`
- `work_log`
- `daily_progress`
- `learning`

## Notes

- API token auth uses Laravel Sanctum personal access tokens.
- Sanctum stores token secrets hashed in `personal_access_tokens`; the plain token is only returned at creation time.
- The default token prefix is `pos_` through `SANCTUM_TOKEN_PREFIX` so leaked tokens are easier to identify in secret scanners.
- Browser session requests are allowed through the same routes without requiring a personal access token.
- Bearer token requests are checked against route abilities.
- Auth, token management, read, write, quick-capture, and export routes use separate Laravel rate limiters.
- `docs/openapi.yaml` contains the OpenAPI contract for auth, token management, core resources, capture, reports, search, activity, saved views, and references.
- Routes are split into `routes/api/auth.php`, `routes/api/tokens.php`, and `routes/api/v1.php`; `routes/api.php` only loads those files.
