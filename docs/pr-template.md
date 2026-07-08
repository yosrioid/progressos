# Pull Request Template

Gunakan template ini untuk setiap PR yang dibuat ke repository ProgressOS.

---

## Judul PR

Pastikan judul mengikuti format:
```
[type(scope): description

Contoh:
- fix(auth): tambahkan validasi field theme di updateProfile
- feat(theme): perbaiki toggle dark/light mode dan persistensi localStorage
- refactor(ui): konsolidasi komponen card ke pattern terpusat
- docs: update API documentation untuk endpoint baru
```

## Tipe Perubahan

Pilih salah satu (tandai dengan `[x]`):

- [ ] **Bug fix** — perbaikan issue yang sudah ada
- [ ] **New feature** — fitur baru yang belum ada sebelumnya
- [ ] **Breaking change** — perubahan yang tidak backward-compatible
- [ ] **Refactor** — perbaikan struktur tanpa perubahan fungsional
- [ ] **Documentation** — update dokumentasi saja
- [ ] **Performance** — peningkatan performa tanpa perubahan fungsional
- [ ] **Tests** — penambahan atau perbaikan test saja
- [ ] **Chore** — perbaikan kecil lain-lain

## Deskripsi

Jelaskan secara singkat dan jelas apa yang diubah dan kenapa. Sertakan link issue jika ada.

Contoh:
- Issue terkait: #123
- Sebelumnya tombol toggle theme tidak berfungsi untuk user biasa karena memanggil endpoint admin-only.
- Update frontend dan backend agar theme preference tersimpan per-user dan persist di localStorage.

## Perubahan

### File yang Diubah

- `app/Http/Controllers/Api/AuthController.php` — tambah validasi `theme`
- `resources/js/vue/App.vue` — refaktor cycleTheme, tambah themeOverride, fix activeTheme computed
- `routes/api/auth.php` — (jika ada) update route validation

### Penjelasan Detail

- **Backend**:
  - `updateProfile()` sekarang menerima field `theme` (`in:system,light,dark`).
  - User theme tersimpan di kolom `users.theme` (sudah ada di migration).

- **Frontend**:
  - `cycleTheme()` pakai `PATCH /api/profile` untuk user biasa, `PUT /api/admin/configuration/settings` untuk admin.
  - `activeTheme` computed prioritas: `themeOverride` → `localStorage` → `configuration.appearance.theme` → `auth.user.theme` → `'system'`.
  - Theme icon swap instan berkat `themeOverride` ref, tanpa menunggu server response.
  - `localStorage` key `progressos-theme` menyimpan preferensi user untuk persistensi refresh.

## Test

### Manual Test

- [ ] Login sebagai user biasa, klik toggle theme beberapa kali — icon harus swap (sun → moon → monitor).
- [ ] Refresh browser — mode harus tetap sesuai pilihan terakhir.
- [ ] Coba mode `system` → ganti OS theme preference → app harus mengikuti.
- [ ] Login sebagai admin — test juga toggle dan refresh.

### Automated Test

Jika ada test yang dijalankan, sertakan hasil:

```bash
php artisan test --filter=Profile
# Tests: 1 passed (4 assertions)
# Duration: 0.38s

npm run build
# vite build ... ✓ built in 1.37s
```

## Checklist

Pastikan semua item di checklist sudah terpenuhi sebelum merge:

- [ ] Kode mengikuti standard di `docs/code-standards.md`
- [ ] Bahasa Indonesia untuk user-facing text
- [ ] Tidak ada console error/warning di browser
- [ ] Build sukses: `npm run build`
- [ ] Test backend sukses: `php artisan test`
- [ ] Jika ada perubahan UI, test e2e sukses: `npm run test:e2e`
- [ ] Jika ada endpoint API baru, update `docs/openapi.yaml` dan jalankan `php artisan docs:sync`
- [ ] PR judul dan deskripsi mengikuti template ini

## Breaking Changes

Jika ada perubahan yang berdampak pada backward-compatibility, jelaskan di sini. Jika tidak ada, tulis "Tidak ada breaking changes."

## Catatan Tambahan

Sertakan informasi tambahan yang relevan, misalnya:
- Keputusan arsitektur yang perlu dipertimbangkan
- Area yang masih perlu perhatian di masa depan
- Referensi ke design decision document

---

**Reviewers**: @username (jika perlu mention reviewer khusus)
**Labels**: (pilih label dari repo, contoh: bug, enhancement, frontend, backend)