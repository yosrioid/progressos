# ProgressOS Release v2.6.2

Patch release untuk memperbaiki tampilan transaksi hasil import pada deployment MySQL/MariaDB.

## Perbaikan

- Memperbaiki query agregasi bulan di Money API agar memakai fungsi tanggal sesuai database driver.
- Deployment MySQL/MariaDB sekarang memakai `DATE_FORMAT(transacted_at, '%Y-%m')`, sehingga data transaksi yang sudah berhasil diimport kembali muncul di halaman Transaksi.
- SQLite lokal tetap memakai `strftime`, dan PostgreSQL memakai `to_char`.
- Menambahkan regression test untuk endpoint daftar bulan dan detail transaksi bulanan.

## Verifikasi

```bash
php artisan test tests/Feature/Api/MoneyControllerTest.php
```
