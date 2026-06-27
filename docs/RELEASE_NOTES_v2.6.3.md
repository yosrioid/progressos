# ProgressOS Release v2.6.3

Patch release untuk menambahkan pembuatan project langsung dari halaman Projects.

## Perubahan

- Menambahkan endpoint `POST /api/v1/projects` untuk membuat project manual.
- Menambahkan form "New project" di halaman Projects dengan pilihan warna.
- Project masih tetap bisa dibuat otomatis saat membuat Task atau Work Log dengan nama project baru.
- Validasi nama project sekarang mencegah duplikat untuk user yang sama.
- Menambahkan regression test untuk pembuatan project dan validasi duplikat.

## Verifikasi

```bash
php artisan test tests/Feature/Api/ProjectControllerTest.php
npm run build
php artisan route:list --path=api/v1/projects
```
