# ProgressOS Release v2.6.6

Patch release untuk memperbaiki navigasi bulan di Tagihan Bulanan.

## Perubahan

- Mengganti perhitungan bulan di `Bills.vue` dari `toISOString()` UTC ke format bulan lokal.
- Tombol bulan sebelumnya/berikutnya sekarang bergerak satu bulan secara konsisten di timezone lokal.
- Label bulan dan label ringkas bulan dibuat dari `new Date(year, monthIndex, day)` agar tidak bergeser karena parsing UTC.
- Perhitungan `last_payment` backend sekarang selalu dihitung dari awal bulan dengan `subMonthNoOverflow()`.

## Verifikasi

```bash
php artisan test tests/Feature/Api/BillControllerTest.php tests/Feature/Api/MoneyControllerTest.php
npm run build
```
