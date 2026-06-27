# ProgressOS Release v2.6.1

Release ini mencakup penambahan fitur baru seperti modul Lists, tracker Tagihan Bulanan, import Transaksi, fitur privasi PIN-lock, perbaikan bug krusial, pembersihan UI/UX secara menyeluruh, serta panduan deployment ke VPS.

## Ringkasan Perubahan (Changelog)

### 1. Lists Module & Kolaborasi
* **Lists dengan Atribut Lengkap**: Implementasi modul Lists baru dengan dukungan tingkat prioritas (priority), tanggal jatuh tempo (due date), dan catatan (notes).
* **DocShare Public Link**: Kemampuan membagikan Lists secara publik melalui link share (`DocShare`), lengkap dengan backend routing dan handling.

### 2. Finansial & Tracking
* **Tagihan Bulanan (Monthly Bill Tracker)**:
  * Pelacakan tagihan bulanan dengan opsi *recurring* (berulang) vs *one-off* (sekali bayar).
  * Fitur untuk melewati (*skip*) bulan tertentu pada tagihan berulang.
* **Transaksi (Money Manager Import & Viewer)**:
  * Fitur import data transaksi keuangan dengan parser XLSX aman.
  * Penambahan kartu ringkasan saldo sepanjang masa (*all-time balance summary*) di halaman Money.
  * Fix kompatibilitas SQLite menggunakan fungsi `strftime` (menggantikan `DATE_FORMAT` MySQL).
  * Perbaikan timezone bug pada header pengelompokan tanggal di Money.vue.

### 3. Keamanan & Privasi (Privacy Mode)
* **Pinia Privacy Store**: Menyimpan status mode privasi global (*hide-sensitive*).
* **PIN Passcode & Session Lock**: Pengamanan akses dengan kode PIN (akses otomatis terkunci setelah 5 menit tidak aktif).
* **PinGate Component**: Komponen wrapper pelindung untuk halaman sensitif `/money` dan `/bills`.
* **Masking Financial Amounts**: Menyembunyikan/menyensor nominal uang di Dashboard widget, Money, dan Bills saat mode privasi aktif.
* **Global Eye-Toggle**: Tombol ikon mata di topbar global untuk menyembunyikan/menampilkan data sensitif secara instan.
* **Pengaturan Privasi**: Section baru di Configuration untuk set/change/remove PIN.

### 4. UI/UX Sweep & Weekly Review
* **Konsolidasi Navigasi**: Menggabungkan menu navigasi yang tumpang tindih untuk tampilan yang lebih bersih (Tasks+Board, Activity+Analytics, Goals+Milestones, Weekly Review disatukan ke Reports).
* **Weekly Review**: Halaman refleksi mingguan terintegrasi di bawah menu Reports dengan navigasi minggu, form teks refleksi, dan statistik penyelesaian task.
* **Standardisasi Ikon Aksi**: Menggunakan ikon pensil (edit) dan tempat sampah (hapus) standar dengan class CSS khusus (`.btn-icon-edit` / `.btn-icon-delete`).
* **UI/UX Polishing**: Penyesuaian ukuran heading h1, perbaikan skeleton loaders, perbaikan touch target mobile, dan layout pagination.

### 5. Infrastruktur & Kode
* **Production Deployment Guide**: Dokumen panduan komprehensif (`docs/deploy.md`) untuk deploy ProgressOS ke Ubuntu 22.04 VPS dengan arsitektur multi-project.
* **PHPStan & Larastan Audit**: Pembersihan error PHPStan/Larastan (tipe casts model, selectRaw, env() seeder, narrowing type BillPayment).
* **Bug Fixes**:
  * Pencegahan fatal crash XML pada file XLSX korup di `XlsxParser`.
  * Pembersihan rute mati (dead routes) Analytics dan WeeklyReview di `router.ts`.
  * Integrasi transisi tab visibility auto-pause untuk menghentikan timer game Sudoku.
  * **Tiptap Dependency Pin**: Mengunci versi dependensi `@tiptap/*` secara presisi ke `3.26.0` di `package.json` & `package-lock.json` untuk mengatasi konflik instalasi npm peer dependency (`ERESOLVE`).
