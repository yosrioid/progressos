# ProgressOS REST API

Dokumentasi interaktif tersedia di **`/api-docs`** (Swagger UI) saat server berjalan.
OpenAPI spec: `docs/openapi.yaml` atau `GET /api-docs/openapi.yaml`.

## Autentikasi

Browser session menggunakan endpoint auth:

- `POST /api/register`
- `POST /api/login`
- `POST /api/logout`
- `GET /api/me`

External tools (bot Telegram, CLI, automation) gunakan **personal API token**.

### Buat Token

`POST /api/tokens`

```json
{
  "name": "TelegramBot",
  "abilities": ["read", "write", "capture"],
  "expires_at": "2027-01-01T00:00:00+07:00"
}
```

Response menyertakan `plain_text_token` sekali saja. Simpan dengan aman.

**Abilities:**
- `read` — GET semua resource
- `write` — create / update / delete record
- `capture` — `POST /api/v1/quick-capture`
- `reports` — report dan CSV/PDF export
- `tokens` — kelola token

Untuk bot Telegram / Groq AI, gunakan `["read", "write", "capture"]`.

### Gunakan Token

```http
Authorization: Bearer pos_xxx
Accept: application/json
```

### Cabut Token

`DELETE /api/tokens/{id}`

---

## Modul API (`/api/v1`)

| Modul | Endpoints |
|-------|-----------|
| Dashboard | `GET /dashboard` |
| Analytics | `GET /analytics` |
| Standup | `GET /standup` |
| Projects | `GET /projects`, `GET /projects/{id}`, `PATCH /projects/{id}` |
| Daily Progress | CRUD `/daily-progress` |
| Work Logs | CRUD `/work-logs` |
| Tasks | CRUD `/tasks`, `/tasks/kanban`, `/tasks/overdue-count`, `/tasks/{id}/status` |
| Learning | CRUD `/learning`, `/learning/stats`, `/learning/heatmap` |
| Milestones | CRUD `/milestones`, `/milestones/{id}/history` |
| Habits | CRUD `/habits`, log/unlog, reorder |
| Goals | CRUD `/goals`, key results CRUD |
| Notifications | `/notifications`, mark-read, clear |
| Docs | CRUD `/docs`, file upload/download |
| Quick Capture | `POST /quick-capture` |
| Reports | `/reports/{weekly\|monthly}`, export CSV/PDF, snapshots |
| Search | `GET /search?q=` |
| Activity | `GET /activity` |
| Saved Views | CRUD `/saved-views`, set-default |
| References | `POST /references`, `DELETE /references/{id}` |
| Configuration | settings, auth, mail, backup |

---

## Quick Capture — Endpoint Utama untuk Bot

`POST /api/v1/quick-capture`

Endpoint ringkas untuk bot Telegram / Groq. Kirim payload parsed dari LLM:

```json
{
  "type": "task",
  "title": "Implementasi webhook Telegram",
  "project_name": "ProgressOS",
  "notes": "Gunakan python-telegram-bot v20"
}
```

`type` values:
- `task` → buat task (status: todo)
- `blocker` → buat task (status: blocked)
- `work_log` → buat work log
- `daily_progress` → buat progress harian
- `learning` → buat entri belajar

Gunakan header `Idempotency-Key` untuk deduplikasi retry (berlaku 24 jam).

Response menyertakan `record_path` (misal `/tasks/42`) untuk deep-link ke record.

---

## Response Shape

- **Item**: `{ "data": {...}, "message": "...", "<key>": {...} }`
- **Collection**: `{ "data": [...], "<key>": [...] }`
- **Paginated**: `{ "data": [...], "meta": {...}, "links": {...}, "<key>": [...] }`

Named keys per modul:
- daily progress: `entry` / `entries`
- work logs: `log` / `logs`
- tasks: `task` / `tasks`
- learning: `entry` / `entries`
- milestones: `milestone` / `milestones`

---

## Token Prefix

Default token prefix `pos_` via env `SANCTUM_TOKEN_PREFIX` — mempermudah deteksi di secret scanner.

---

## Notes Teknis

- Sanctum menyimpan token hash — plain token hanya dikembalikan saat dibuat
- Browser session diizinkan tanpa bearer token
- Rate limiter terpisah: `api-read`, `api-write`, `api-capture`, `api-export`
- `POST /api/v1/reports/{period}/snapshots` menyimpan snapshot laporan
- `php artisan backups:run-due` menjalankan backup terjadwal (dijalankan scheduler per jam)
