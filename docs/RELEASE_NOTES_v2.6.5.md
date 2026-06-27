# ProgressOS Release v2.6.5

Patch release untuk mengganti flow bulk Work Logs menjadi input Work Log biasa dengan multi-select project.

## Perubahan

- Menghapus endpoint khusus `POST /api/v1/work-logs/bulk` dan panel bulk di halaman Work Logs.
- Form `New Work Log` sekarang memakai multi-select Projects.
- Satu submit dengan beberapa project membuat beberapa Work Log terpisah dengan tanggal, judul, kategori, status, prioritas, durasi, dan catatan yang sama.
- Mode edit Work Log tetap memakai satu project agar tidak mengubah banyak record sekaligus.
- Model data tetap `1 Work Log = 1 Project`, sehingga reporting per project tetap akurat.

## Verifikasi

```bash
php artisan test tests/Feature/Api/WorkLogControllerTest.php tests/Feature/Api/ProjectControllerTest.php
npm run build
php artisan route:list --path=api/v1/work-logs
```
