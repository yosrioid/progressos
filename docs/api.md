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
  "name": "Raycast"
}
```

Response includes `plain_text_token` once. Store it securely.

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

- `GET /api/v1/dashboard`
- `GET /api/v1/activity`
- `POST /api/v1/quick-capture`
- `GET /api/v1/daily-progress`
- `POST /api/v1/daily-progress`
- `GET /api/v1/work-logs`
- `POST /api/v1/work-logs`
- `GET /api/v1/tasks`
- `POST /api/v1/tasks`
- `GET /api/v1/learning`
- `POST /api/v1/learning`
- `GET /api/v1/milestones`
- `POST /api/v1/milestones`
- `GET /api/v1/reports/weekly`
- `GET /api/v1/reports/monthly`
- `GET /api/v1/search?q=term`

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

- API tokens are stored hashed with SHA-256.
- Token authentication is intentionally dependency-light and does not require Sanctum.
- Token abilities are stored for future authorization expansion, but current MVP endpoints treat valid tokens as full user API access.
