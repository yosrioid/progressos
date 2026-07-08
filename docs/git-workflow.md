# Git Workflow — Commit & Push Rules

Panduan singkat untuk commit, branch, dan push di ProgressOS. Tujuan utama: history yang bersih, mudah di-review, dan mudah di-rollback.

---

## Branch Naming

Selalu kerja di feature branch, **tidak boleh** push langsung ke `main`.

Format: `<type>/<scope>-<short-description>`

| Type | Contoh | Dipakai untuk |
| --- | --- | --- |
| `feat` | `feat/inbox-gif-support` | fitur baru |
| `fix` | `fix/theme-toggle-not-clickable` | perbaikan bug |
| `refactor` | `refactor/api-resource-base` | refactor tanpa ubah perilaku |
| `docs` | `docs/update-api-md` | perubahan dokumentasi saja |
| `chore` | `chore/bump-tailwind-4` | maintenance, dependency, tooling |
| `test` | `test/e2e-record-forms` | penambahan/perbaikan test |

Scope boleh spesifik modul (`inbox`, `theme`, `auth`, `profile`, `dashboard`) atau generic (`api`, `ui`, `docs`).

Contoh branch yang sedang aktif:
- `feature/adacode-integration`
- `rewrite/vue-pinia-rest`
- `feature/configuration-registry`

---

## Commit Message Convention

Mengikuti **Conventional Commits** (sama dengan format judul PR agar konsisten).

### Format

```
<type>(<scope>): <subject>

<body>

<footer>
```

### Subject Rules

- **Maksimum 72 karakter**.
- Pakai **imperative mood**: "add", "fix", "refactor" — bukan "added", "fixed", "refactored".
- **Huruf kecil** di awal subject (setelah colon).
- **Tidak diakhiri tanda titik**.
- Subject harus bisa melengkapi kalimat: *"If applied, this commit will \<subject\>"*.
  - Benar: `fix(auth): add theme field validation to updateProfile`
  - Salah: `Fix theme bug`, `add Theme Field`, `Added theme field.`

### Type yang Dipakai

| Type | Dipakai untuk |
| --- | --- |
| `feat` | fitur baru (user-facing) |
| `fix` | bug fix |
| `refactor` | perubahan kode tanpa ubah perilaku |
| `perf` | peningkatan performa |
| `docs` | perubahan dokumentasi saja |
| `test` | penambahan/perbaikan test |
| `chore` | maintenance (deps, config, tooling) |
| `style` | format kode (whitespace, prettier) tanpa ubah logika |
| `build` | perubahan build system atau external deps |

### Body

- Pisahkan subject dengan baris kosong.
- Jelaskan **apa** dan **kenapa**, bukan **bagaimana** (code sudah jelas menunjukkan bagaimana).
- Wrap di **72 karakter** per baris.
- Maksimum beberapa paragraf; jika lebih panjang, pertimbangkan split commit.

### Footer

- Referensi issue: `Closes #123`, `Fixes #456`, `Refs #789`.
- Breaking change: awali body atau footer dengan `BREAKING CHANGE:`.

### Contoh

```text
fix(theme): persist user theme across refreshes

The cycleTheme handler was calling the admin-only endpoint
PUT /api/admin/configuration/settings, which returned 403 for
regular users. As a result, theme preference never reached
the server and reset to 'system' on every page load.

- Use PATCH /api/profile for non-admin users
- Keep admin endpoint for admins
- Add themeOverride ref so the icon updates instantly
- Persist to localStorage under 'progressos-theme'

Closes #142
```

---

## Commit Hygiene

### Satu commit = satu perubahan logis

Pisahkan perubahan yang tidak related. Contoh buruk: satu commit berisi refactor unrelated + bug fix + formatting.

### Jangan commit

- File yang dihasilkan (build output, `node_modules/`, `vendor/`).
- File environment (`.env`, `.env.local`).
- Credentials, API keys, token, `storage/*.key`.
- File temporary atau debug log.
- Perubahan besar yang sebenarnya beberapa commit.

Cek dengan `git status` sebelum commit. Kalau ragu, tambahkan path eksplisit:

```bash
git add app/Http/Controllers/Api/AuthController.php resources/js/vue/App.vue
```

### Jangan amend commit yang sudah di-push

Jika commit sudah ada di remote branch yang dipakai orang lain (atau sudah dipakai sebagai basis PR), buat commit baru. `--amend` dan `force-push` hanya aman untuk branch lokal yang belum di-share.

### Pesan commit dalam bahasa Inggris

Codebase pakai English untuk kode dan dokumentasi user-facing. Pesan commit konsisten English agar mudah di-grep lintas developer.

---

## Anomaly Detection

Sebelum commit, AI assistant (atau developer) wajib cek perubahan yang **tidak related** ke scope commit. Tujuannya supaya:

- Commit tidak ikut membawa file random/aneh.
- Reviewer tidak bingung kenapa file X berubah.
- History tetap mudah di-trace per fitur.

### Pola yang Wajib Di-flag

**1. File konfigurasi lokal / IDE**

- `.env`, `.env.local`, `.env.*` (kecuali `.env.example`)
- `.idea/`, `.vscode/`, `.cursor/`, `.zed/`, `.nova/`
- `*.swp`, `*.swo`, `*.bak`, `*.tmp`, `*.orig`, `*.rej`
- `Thumbs.db`, `.DS_Store`

Tindakan: pastikan masuk `.gitignore`, jangan di-stage.

**2. Generated files**

- `public/build/*` (Vite output)
- `node_modules/`, `vendor/`
- `bootstrap/cache/*.php` (kecuali yang memang tracked)
- `public/storage/`, `public/hot`
- `storage/framework/cache/data/*`, `storage/framework/views/*.php` (compiled Blade)
- `storage/logs/*.log`
- `public/fonts-manifest.dev.json`

Tindakan: pastikan masuk `.gitignore`. Kalau muncul di `git status`, jangan commit.

**3. File yang tidak nyambung dengan pesan commit**

Contoh anomali:
- Commit pesan `fix(theme): persist user theme` tapi ada file `app/Services/AiProviderManager.php` yang ikut berubah.
- Commit pesan `feat(auth): add login rate limit` tapi ada `resources/js/vue/components/AiQuotaStatus.vue` yang baru di-add.

Tindakan: pisah jadi commit terpisah, atau `--reset` file tersebut lalu tanya developer.

**4. File yang Boleh tapi Wajib Di-sadari**

Tidak semua file unexpected itu salah. Beberapa kasus legitimate:

- `.env.example` modify untuk tambah env var baru — OK, tapi verify tidak ada key asli yang tertinggal.
- `.gitignore` modify untuk ignore file baru — OK.
- `.valetignore` / `Homestead.yaml` untuk konfigurasi local dev environment — OK, tapi verify isinya sensible.
- `composer.json` / `composer.lock` / `package.json` / `package-lock.json` untuk dependency baru — OK, tapi cek apakah lock file up-to-date.
- `database/migrations/*` untuk migration baru — OK.

**5. File sensitif / secret**

- API key, token, password yang ke-commit (lihat di diff)
- Private key, certificate
- File dengan data user / production data
- File `auth.json` Composer (Pest/Heroku tokens)

Tindakan: **JANGAN COMMIT**. Kalau sudah terlanjur, follow "Salah commit ke main" di bawah (revert + rotate secret).

### Cara AI Membantu Deteksi

Sebelum commit, AI wajib menjalankan checklist ini dan report ke developer:

```bash
# 1. Lihat semua perubahan
git status --short

# 2. Filter file mencurigakan
git status --short | grep -E '\.(env|swp|swo|bak|orig|rej|log|sqlite)$|^(.idea|.vscode|.cursor|.zed|.nova)/|Thumbs\.db|\.DS_Store|public/build/|node_modules/|vendor/'

# 3. Cek staged file apakah sesuai dengan scope commit
git diff --cached --name-only

# 4. Verifikasi tidak ada secret di staged diff
git diff --cached | grep -iE 'password|api[_-]?key|secret|token' | head
```

Kalau ada yang ke-flag:
1. Tampilkan ke developer dengan konteks: file apa, kenapa mencurigakan, apa yang harus dilakukan.
2. Tanya konfirmasi: commit terpisah, exclude, atau ignore.
3. Jangan auto-commit file anomali tanpa konfirmasi.

### Template Laporan Anomali

Saat AI mendeteksi file mencurigakan, gunakan format ini:

```
⚠️ Anomali Terdeteksi

File-file berikut berubah tapi tidak related dengan scope commit saat ini:

- .valetignore (new) — Valet/Herd config, OK to commit tapi bukan bagian dari feat
- .env.example (modified) — Tambah env var untuk AI provider, OK tapi verify no real key

Rekomendasi:
- Pisahkan .valetignore ke commit terpisah: chore(dev): add .valetignore
- Keep .env.example di commit utama karena terkait AI provider config

Lanjut dengan rekomendasi ini, atau adjust?
```

---

## Push Rules

### Sebelum Push

Selalu jalankan checklist ini di branch lokal:

```bash
# 1. Lihat apa yang akan di-push
git status
git diff --stat

# 2. Cek bahwa tidak ada perubahan di main yang belum di-merge ke branch
git fetch origin
git log --oneline origin/main..HEAD

# 3. Verifikasi branch tujuan
git branch --show-current
```

### Perintah Push

```bash
# Push branch baru (pertama kali)
git push -u origin <branch-name>

# Push commit tambahan
git push
```

### Yang Dilarang

- `git push origin main` — **tidak pernah**. Selalu lewat PR.
- `git push --force` atau `git push -f` ke branch yang sudah di-share.
  - Pengecualian: branch pribadi yang belum dibuka PR dan belum ada reviewer. Tetap lebih prefer `--force-with-lease` agar tidak menimpa commit orang lain.
- `git push --no-verify` — jangan skip hooks kecuali sudah disepakati tim.

### Kalau Push Gagal (Rejected)

Biasanya karena branch di remote sudah maju. Jangan langsung force-push:

```bash
git fetch origin
git rebase origin/<branch-name>   # atau merge, sesuai preferensi
git push
```

---

## Pull Request

Setelah push, buka PR menggunakan template di `docs/pr-template.md`.

### Sebelum Buka PR

Pastikan:

- [ ] Branch sudah ter-push ke `origin`
- [ ] `npm run build` sukses
- [ ] `php artisan test` sukses (atau test yang relevan)
- [ ] `composer lint` dan `composer analyse` sukses kalau ada perubahan PHP
- [ ] `npm run test:e2e` sukses kalau ada perubahan UI
- [ ] `docs/openapi.yaml` di-update dan `php artisan docs:sync` dijalankan kalau ada endpoint baru
- [ ] Judul PR mengikuti format Conventional Commits
- [ ] Body PR menggunakan template `docs/pr-template.md`

### Setelah Review Approve

Pilih salah satu:

- **Squash merge** — default untuk PR dengan banyak small commits.
- **Rebase merge** — untuk PR dengan commit history yang sudah bersih.
- **Merge commit** — jarang dipakai; biasanya untuk branch panjang.

---

## Recovery Patterns

### Salah commit ke branch

```bash
# Undo commit terakhir, keep changes staged
git reset --soft HEAD~1

# Undo commit terakhir, keep changes unstaged
git reset HEAD~1

# Undo commit terakhir, buang changes (HATI-HATI)
git reset --hard HEAD~1
```

### Salah commit ke main

**Tidak boleh** reset `main` lokal lalu force-push. Cara yang benar:

1. Revert commit dengan `git revert <commit-sha>`.
2. Push revert commit.
3. Buka PR untuk revert.

### Lupa branch

```bash
# Lihat branch dan commit terakhir
git branch -a
git log --oneline -5

# Pindah ke branch yang benar
git checkout <branch-name>
```

---

## TL;DR

```bash
# 1. Bikin branch dari main terbaru
git checkout main && git pull
git checkout -b fix/theme-toggle-not-clickable

# 2. Kerja, commit sering dengan pesan yang jelas
git add <files>
git commit -m "fix(theme): use user-level endpoint for theme persistence"

# 3. Sebelum push, verify
git status
npm run build
php artisan test --filter=Profile

# 4. Push dan buka PR
git push -u origin fix/theme-toggle-not-clickable
gh pr create --title "fix(theme): persist user theme across refreshes" --body-file .github/pr-body.md
```
