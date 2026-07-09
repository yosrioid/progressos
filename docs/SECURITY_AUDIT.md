# Security Audit & Adjustment — 2026-07-09

Scan menyeluruh terhadap keamanan frontend, backend, role-based access control, dan hacker attack surface.

---

## 1. ROLE-BASED ACCESS CONTROL (RBAC)

### 1.1 Role Model — Cukup `is_admin` atau `role === 'admin'`
- User model menggunakan `role` column (`'admin'` / `'user'`) tanpa table terpisah.
- `User::isAdmin()` hanya cek `role === 'admin'` — simple dan cukup untuk saat ini.
- **STATUS: OK** untuk single-user / small-team personal OS.

### 1.2 Admin Middleware — Tegas
- `EnsureIsAdmin` middleware: `abort(403, 'Access denied.')` jika bukan admin.
- `EnsureIsNotAdmin` middleware: `abort(403, 'Admin accounts cannot access user data.')` jika admin mencoba akses user routes.
- **STATUS: OK** — enforced di route level (`routes/api/admin.php`) dan frontend level (`router.ts`).

### 1.3 Admin Account Self-Protection — Sempurna
- `AdminUserController`: semua operasi (update, reset password, disable, enable, delete) melindungi admin account:
  - `abort_if($user->isAdmin(), 403, 'Cannot edit admin accounts.')`
  - `abort_if($user->isAdmin(), 403, 'Cannot delete admin accounts.')`
- **STATUS: OK** — admin tidak bisa lock diri sendiri atau delete admin lain.

### 1.4 IDOR (Insecure Direct Object Reference) — Sebagian Besar OK
- 21 model punya `ownedBy()` scope untuk user-scoping data.
- GameController, ChatController, InboxController: cek `user_id` secara eksplisit via `abort_unless()`.
- **MASALAH (MEDIUM):** `ReferenceController::store()` line 17 — `findOrFail($data['referenceable_id'])` tanpa cek ownership target. Bisa menambahkan reference ke resource milik user lain.
- **MASALAH (LOW):** `DocController::share()` — share token bisa diakses siapa saja via `/share/doc/:token`. Ini intentional (public share), tapi pastikan tidak ada file sensitive yang unintentionally shared.

### 1.5 Sanctum Token Abilities — Ada tapi Tidak Seragam
- Middleware `ability:read,write` diterapkan di banyak route.
- Beberapa route (login, logout, quick-capture) tidak pakai ability check.
- **STATUS: OK** — ability check cukup ketat untuk API routes yang ada.

---

## 2. BACKEND SECURITY

### 2.1 Authentication — Kuat
- Laravel Sanctum stateful session auth (cookie-based).
- Password hashing: `hashed` cast (bcrypt otomatis).
- Session regeneration after login (anti session fixation).
- Account disable check di login flow.
- **STATUS: OK** — auth flow solid.

### 2.2 Password Reset — Rate Limited
- `forgotPassword`: `throttle:passwords` (default 60/min).
- `resetPassword`: `throttle:passwords` (default 60/min).
- Email enumeration protection: selalu return 200 dengan pesan generic.
- **STATUS: OK** — password reset aman.

### 2.3 Registration — Disabled by Default
- `registration_enabled` default `false` via env.
- Admin account sudah dibuat via seeder (hardcoded di `config/app.php`).
- **MASALAH (LOW):** Default admin password hardcoded di `config/app.php` line 50: `'admin_password' => env('ADMIN_PASSWORD', 'changeme123')`. Jika deployment tidak mengubah `ADMIN_PASSWORD`, defaultnya `changeme123`.
- **FIX:** Hapus default value atau ganti dengan `Str::random(32)`.

### 2.4 Input Validation — Campuran
- Banyak controller pakai FormRequest classes (`DailyProgressRequest`, `TaskRequest`, dll).
- GameController, ChatController, ConfigurationController: inline `$request->validate()` — masih acceptable karena logic kompleks, tapi idealnya dipisah.
- **STATUS: ACCEPTABLE** — tidak ada critical validation gap.

### 2.5 CSRF Protection — Ada
- Sanctum stateful mode: `bootstrap/app.php` line 26 `$middleware->statefulApi()`.
- Laravel default CSRF middleware aktif di web routes.
- **MASALAH (MEDIUM):** `api.ts` tidak ada CSRF token refresh interceptor. Jika session cookie expired, POST request bisa gagal 419 tanpa retry otomatis.
- **FIX:** Tambahkan response interceptor di `api.ts` untuk auto-refresh CSRF cookie pada 419.

### 2.6 SQL Injection — Tidak Ditemukan
- Semua query pakai Eloquent ORM.
- Tidak ada `DB::raw`, `whereRaw`, atau raw SQL di controllers.
- `ApiQuery` support class: text search via `where('column', 'like', '%'.$q.'%')` — parameterized.
- **STATUS: OK** — tidak ada SQL injection risk.

### 2.7 File Upload — Terproteksi
- **DocFileController:** MIME type whitelist + extension whitelist + `finfo` validation (real MIME check).
- **Avatar upload:** `image` mime type + 2MB max.
- **Inbox file:** MIME type validation via custom rule (image/video/audio/pdf/doc).
- **Filename sanitization:** `preg_replace('/[\/\\\0\x01-\x1f]/', '_', $file->getClientOriginalName())` — strip path traversal chars.
- UUID-based storage: `Str::uuid().'.'.$ext` — tidak bisa predict filename.
- **STATUS: OK** — file upload security solid.

### 2.8 Rate Limiting — Cukup
- `api-read`: 60/min (default Laravel throttle).
- `api-write`: 60/min.
- `api-capture`: 30/min.
- `api-export`: 10/min.
- `ai-stream`: 60/min.
- `auth`: 5/min (login).
- `auth-register`: 10/min.
- `passwords`: 5/min.
- `api-tokens`: 10/min.
- **MASALAH (LOW):** `searchUsers` di InboxController tidak rate-limited selain `throttle:api-read` (60/min). Bisa di-abuse untuk user enumeration.
- **STATUS: ACCEPTABLE** — rate limiting cukup untuk personal OS.

---

## 3. FRONTEND SECURITY

### 3.1 XSS Prevention — Baik
- `Search.vue`: DOMPurify untuk `v-html` highlight (only `<mark>` tag allowed).
- `DocDetail.vue` & `DocShare.vue`: DOMPurify untuk `v-html` description.
- URL validation: `safeUrl()` function — only `http:` and `https:` protocols allowed.
- `target="_blank"` links: sebagian besar punya `rel="noopener noreferrer"`.
- **MASALAH (LOW):** Beberapa `target="_blank"` tanpa `rel="noopener noreferrer"`:
  - `QuotaExceededBanner.vue:65` — external link ke `adacode.ai/billing` (trusted domain).
  - `ChatBubble.vue:327` — AI response links (DOMPurified).
  - `Reports.vue:134` — internal PDF export link.
  - `Inbox.vue:308` — GIF links.
  - `RecordDetail.vue:459` — `rel="noreferrer"` (missing `noopener`).
- **STATUS: OK** — XSS protection cukup kuat.

### 3.2 Auth State — Aman
- Token disimpan di session cookie (tidak di localStorage).
- No `console.log` yang mengekspos sensitive data.
- `auth.ts` boot: fetch `/api/me` via cookie-based session.
- **STATUS: OK** — token handling aman.

### 3.3 Route Guards — Ada
- `router.ts` beforeEach:
  - Guest routes redirect authenticated users.
  - Protected routes redirect unauthenticated users to `/login`.
  - Admin routes: admin → `/admin/users`, non-admin → `/dashboard`.
- **MASALAH (LOW):** Router guards tidak blocking direct API calls. User bisa membuka DevTools dan memanggil `/api/admin/*` secara manual — ini di-handle oleh backend middleware, tapi frontend tidak mencegah affordance.
- **STATUS: ACCEPTABLE** — backend enforcement sudah cukup.

### 3.4 Console Logging — Minimal
- Hanya 2 `console.error` di `configuration.ts` (failed AI usage/quota fetch).
- Tidak ada sensitive data di log.
- **STATUS: OK** — minimal console usage.

### 3.5 Error Handling — Perlu Perbaikan
- `api.ts`: 401 redirect ke `/login` — OK.
- **MASALAH (MEDIUM):** Tidak ada global error handler untuk axios errors. Setiap view yang pakai `.then(unwrap)` tanpa `.catch()` bisa stuck loading jika API gagal.
- **STATUS: NEEDS FIX** — tambahkan global error handler di `api.ts`.

---

## 4. SECURITY HEADERS — Sudah Baik

### 4.1 Headers — Lengkap
- `X-Frame-Options: SAMEORIGIN` — anti-clickjacking.
- `Cross-Origin-Opener-Policy: same-origin` — COOP.
- `X-Content-Type-Options: nosniff` — anti-MIME sniffing.
- `Referrer-Policy: strict-origin-when-cross-origin` — referrer control.
- `Permissions-Policy: camera=(), microphone=(), geolocation=()` — feature restriction.
- `X-XSS-Protection: 1; mode=block` — legacy XSS filter.
- `X-Download-Options: noopen` — legacy IE protection.
- `X-Permitted-Cross-Domain-Policies: none` — no cross-domain.
- `Strict-Transport-Security` — HSTS over HTTPS.
- `Content-Security-Policy` — comprehensive:
  - `default-src 'self'` — no external resources by default.
  - `script-src 'self'` — no inline scripts (Vue compiles to .js).
  - `style-src 'self' 'unsafe-inline'` — Tailwind needs inline styles.
  - `img-src 'self' data: blob: https:` — images from anywhere.
  - `connect-src 'self'` — API calls only to same origin.
  - `frame-ancestors 'none'` — stronger than X-Frame-Options.
  - `form-action 'self'` — form submissions only to same origin.
  - `object-src 'none'` — no plugins.
- `Cache-Control: no-store` — API responses not cached.
- **STATUS: EXCELLENT** — security headers sangat komprehensif.

---

## 5. CONFIGURATION & DATA PROTECTION

### 5.1 Encrypted Values — Ada
- `Configuration` model: `encrypted_value` cast `'encrypted:array'`.
- AI API keys, mail passwords, Google OAuth secrets disimpan encrypted.
- **STATUS: OK** — sensitive config terenkripsi.

### 5.2 Admin Credentials — Masalah
- `config/app.php` line 50: `'admin_password' => env('ADMIN_PASSWORD', 'changeme123')`.
- **FIX:** Hapus default value `'changeme123'`.

### 5.3 Session Configuration — Perlu Perhatian
- `SESSION_LIFETIME` default 120 menit — session akan expired cukup cepat.
- `SESSION_SECURE_COOKIE` — tergantung env (production harus true).
- `SESSION_SAME_SITE` — default `'lax'` — OK untuk SPA.
- `SESSION_ENCRYPT` — default false — OK.
- `SESSION_SERIALIZATION` — `'json'` — aman (tidak serialize PHP objects).
- **STATUS: OK** — session config reasonable.

---

## 6. ACTION ITEMS (Prioritized)

### 🔴 CRITICAL
| # | Issue | File | Fix |
|---|-------|------|-----|
| 1 | Default admin password `changeme123` | `config/app.php:50` | Hapus default value: `env('ADMIN_PASSWORD')` tanpa fallback |
| 2 | ReferenceController IDOR | `ReferenceController.php:17` | Tambah ownership check: `abort_unless($target->user_id === $request->user()->id, 403)` |

### 🟡 HIGH
| # | Issue | File | Fix |
|---|-------|------|-----|
| 3 | No CSRF 419 retry interceptor | `api.ts` | Tambahkan response interceptor untuk auto-refresh CSRF cookie |
| 4 | No global error handler | `api.ts` | Tambahkan error handler untuk mencegah stuck loading states |
| 5 | `abort(403)` tanpa pesan | `GameController.php:93,111,284,460,610,708,804` | Ganti dengan `abort(403, 'descriptive message')` |
| 6 | `abort(403)` tanpa pesan | `InboxController.php:229` | Ganti dengan `abort(403, 'Not a participant')` |
| 7 | `abort(403)` tanpa pesan | `DocFileController.php:118` | Ganti dengan `abort(403, 'Not authorized')` |

### 🟢 MEDIUM
| # | Issue | File | Fix |
|---|-------|------|-----|
| 8 | `target="_blank"` tanpa `rel="noopener noreferrer"` | `ChatBubble.vue`, `Reports.vue`, `Inbox.vue`, `RecordDetail.vue` | Tambah `rel="noopener noreferrer"` |
| 9 | `searchUsers` endpoint bisa abused untuk enumeration | `InboxController.php:204` | Tambah stricter rate limit (10/min) |
| 10 | No `console.log` stripping in prod | `vite.config.ts` | Pastikan `esbuild: { drop: ['console', 'debugger'] }` di production |

### ℹ️ INFO / LOW
| # | Issue | Keterangan |
|---|-------|-----------|
| 11 | `console.error` di `configuration.ts` | Tidak expose sensitive data — acceptable |
| 12 | DocShare publicly accessible | Intentional — share feature |
| 13 | `X-XSS-Protection` header | Deprecated tapi harmless — bisa dihapus nanti |
| 14 | `style-src 'unsafe-inline'` di CSP | Diperlukan Tailwind — unavoidable |

---

## 7. SUMMARY

| Category | Rating | Notes |
|----------|--------|-------|
| Authentication | A+ | Sanctum stateful, password hashing, session regeneration |
| RBAC | A | Admin/user separation solid, self-protection for admin accounts |
| IDOR Protection | B+ | Most models protected, ReferenceController needs fix |
| Input Validation | A | FormRequest + inline validation adequate |
| File Upload | A | MIME whitelist, extension check, UUID storage |
| CSRF | B+ | Middleware present, needs interceptor for 419 recovery |
| XSS | A | DOMPurify used consistently, URL validation good |
| Rate Limiting | B+ | Throttles in place, some endpoints need stricter limits |
| Security Headers | A+ | Comprehensive CSP, HSTS, COOP, Permissions-Policy |
| Encryption | A | Sensitive config encrypted, bcrypt password hashing |
| Error Handling | B | Needs global error handler, some stuck-loading states possible |
| Overall | **A-** | Security posture sangat baik untuk personal OS |
