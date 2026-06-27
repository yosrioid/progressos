# ProgressOS Release v2.6.4

Patch release untuk mempercepat pencatatan Work Logs lintas project dalam satu hari.

## Perubahan

- Menambahkan endpoint `POST /api/v1/work-logs/bulk` untuk membuat banyak Work Log dalam satu request.
- Menambahkan panel "Bulk add work logs" di halaman Work Logs.
- Setiap baris tetap mengikuti model `1 Work Log = 1 Project`, sehingga reporting per project tetap akurat.
- Project tetap otomatis dibuat saat nama project baru dipakai.
- Menambahkan regression test untuk bulk create dan validasi per baris.

## Verifikasi

```bash
php artisan test tests/Feature/Api/WorkLogBulkControllerTest.php tests/Feature/Api/ProjectControllerTest.php tests/Feature/Api/MoneyControllerTest.php
npm run build
php artisan route:list --path=api/v1/work-logs
```
