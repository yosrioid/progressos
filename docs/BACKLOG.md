# ProgressOS — Backlog & Brainstorm

Daftar ini dibuat 2026-06-29 sebagai hasil brainstorm menyeluruh terhadap kondisi project saat ini.
Diurutkan per prioritas dan ukuran effort.

---

## 🔴 High Impact — Langsung Terasa

### 1. Dashboard lebih hidup
- Contextual nudges: belum ada daily progress hari ini → muncul prompt; ada task overdue → highlight
- Widget streak habits langsung di dashboard (sekarang harus buka /habits)
- Shortcut aksi langsung dari dashboard (bukan hanya lihat data)
- "Hari ini: X task selesai, Y habit, Z menit belajar" — summary angka real-time

### 2. Journal — tampilan hasil analisa lebih visual
- `ai_insight` dan `ai_saran` sekarang plain text panjang → buat jadi card dengan icon / checklist
- Visualisasi mood trend dari semua jurnal (sparkline/grafik sederhana di halaman list)
- Kalender heatmap jurnal (mirip GitHub contribution graph) — kelihatan konsistensi journaling
- Saran bisa di-tick/checklist agar terasa actionable

### 3. Weekly Review yang nyata
- `WeeklyReview.vue` sudah ada tapi belum diisi penuh
- Seharusnya jadi ritual: summary otomatis dari semua modul minggu ini, tanya refleksi, simpan sebagai snapshot
- Integrasi dengan journal, tasks, habits, learning dalam satu halaman
- Killer feature kalau dieksekusi — membuat app terasa sebagai "personal OS" yang utuh

### 4. Quick Capture lebih pintar
- Sekarang: manual pilih type sebelum capture
- Ide: input teks bebas → AI detect apakah ini task, progress, atau learning (Groq, 1 request ringan)
- Alternatif lebih simpel: capture suara via browser Speech API (tidak perlu backend)

### 5. Task — view dan fitur lebih baik
- Kanban board ada, tapi drag-drop antar kolom belum ada
- Due date visualisasi — task mendekati deadline harus lebih mencolok (warna/badge)
- Subtask / checklist di dalam task (belum ada)
- Recurring task yang lebih terlihat di list

---

## 🟡 Medium — Melengkapi yang Sudah Ada

### 6. Projects lebih dalam
- Progress bar berdasarkan task selesai/total
- Estimated completion date otomatis dari deadline task
- Project status (active/paused/completed) bisa diubah dari UI dengan konfirmasi
- Project health indicator (on track / at risk / delayed)

### 7. Habits — lebih motivating
- Best streak history per habit
- Completion rate % dalam 30/90 hari
- Habit grouping atau tags
- Grafik heatmap per habit (mirip kalender kontribusi)

### 8. Learning — koneksi ke Goals & lebih berguna
- Learning entry bisa di-link ke Goal/Key Result
- Stats: total jam belajar per topik, grafik progress over time
- "Reading list" / backlog dari referensi yang belum dipelajari
- Export learning log ke markdown/PDF

### 9. Milestones — lebih actionable
- Timeline visual: milestones di sepanjang garis waktu project
- Alert/notifikasi kalau milestone mendekati deadline
- Progress detail: berapa yang sudah vs target, dari sumber mana

### 10. Money — lebih lengkap
- Budget per kategori + alert kalau mendekati limit
- Grafik pengeluaran per kategori (pie chart atau donut)
- Recurring transactions (otomatis generate tiap bulan)
- Perbandingan bulan ini vs bulan lalu per kategori

### 11. Bills — reminder lebih aktif
- Notifikasi H-3 dan H-7 sebelum jatuh tempo
- Summary "bulan ini total tagihan: Rp X, sudah bayar: Rp Y" langsung di dashboard
- Annual view yang lebih baik (heatmap pembayaran)

---

## 🟢 Polish & UX

### 12. Global search lebih powerful
- Recent items di search tanpa perlu ketik query
- Filter search by date range atau module
- Cmd+K palette yang lebih kaya: quick actions, navigasi, bukan hanya search
- Search di dalam project (scoped search)

### 13. Empty states lebih baik
- Semua halaman kosong masih kaku
- Empty state yang inviting: contoh data, panduan mulai dari mana, atau CTA yang jelas
- Onboarding minimal: setelah login pertama, arahkan ke setup penting (habits, goals, dll)

### 14. Mobile experience
- Beberapa halaman mungkin masih kurang optimal di layar kecil
- Journal shortcut di bottom nav mobile (sering diakses)
- Swipe gesture untuk navigasi antar records
- Optimasi form input di mobile (date picker, textarea resize)

### 15. Notifikasi lebih smart
- Push notification via browser (Web Push API + service worker, gratis)
- Digest mingguan email yang benar-benar berguna: summary angka, bukan hanya list overdue
- Notifikasi habit reminder yang bisa di-snooze

### 16. Export data
- Export jurnal ke plain text atau PDF
- Backup manual semua data ke ZIP JSON (selain Google Sheets)
- Laporan bulanan PDF yang rapi dan bisa dibagikan

---

## 🔵 Bigger Features

### 17. AI Chat redesign (sudah dihapus dari nav karena styling jelek)
- Bukan chat generik — fokus ke "tanya tentang datamu"
  - "Minggu lalu saya ngerjain apa?"
  - "Habit mana yang paling sering skip bulan ini?"
  - "Ringkasan jurnal bulan ini"
  - "Task apa yang sudah overdue lebih dari seminggu?"
- AI perlu akses data real-time dari DB, bukan hanya history chat
- Ini killer feature kalau dieksekusi dengan benar — bedakan dari ChatGPT biasa
- Styling: terintegrasi dengan design app (bukan dark sidebar terpisah)

### 18. Standup otomatis yang benar-benar berguna
- `/standup` ada tapi belum tahu seberapa berguna
- Generate standup dari daily progress + tasks kemarin → siap paste ke Slack
- Schedule notifikasi tiap pagi "standup kamu siap, klik untuk lihat"
- Format bisa dikustom (Slack format, plain text, dll)

### 19. Streak & Gamification kohesif
- Ada game 2048/minesweeper/dll tapi terpisah dari workflow utama
- XP dari task selesai, habit streak, learning session
- Level / achievement badge
- Leaderboard diri sendiri: "minggu terbaikmu", personal records
- Motivator kuat kalau terintegrasi ke habit/task/learning

### 20. Telegram bot integration
- `llms.txt` sudah ada untuk AI context
- Bot: capture task via chat, query data, kirim reminder
- Tidak perlu interface baru, pakai API yang sudah ada
- Gratis, tidak perlu paid API tambahan

---

## ⚡ Quick Wins (masing-masing < 1 jam)

| Item | Keterangan |
|------|------------|
| Dark mode toggle lebih mudah | Sekarang ada di mana? Harusnya 1 klik dari topbar |
| Keyboard shortcut navigasi | `g d` → dashboard, `g t` → tasks, `g j` → journal |
| Copy to clipboard di detail | Untuk paste ke chat/email |
| Sorting konsisten di semua list | Beberapa halaman mungkin belum ada sort |
| Pagination di jurnal | Kalau sudah banyak entri list jadi panjang |
| Back button konsisten | Di semua halaman detail, tidak harus pakai browser back |
| Loading skeleton di semua halaman | Beberapa halaman masih blank sebentar saat load |
| Error boundary per widget | Kalau satu widget error, halaman tidak crash semua |
| Konfirmasi navigate away | Kalau ada unsaved changes di form, minta konfirmasi |
| Timestamp relatif | "2 jam lalu", "kemarin" lebih readable dari ISO date |

---

## Rekomendasi Prioritas Eksekusi

Urutan berdasarkan **impact × feasibility dalam 1 sesi**:

1. **Journal mood heatmap + trend** — visual langsung kelihatan nilai journaling-nya
2. **Dashboard nudges & contextual summary** — app terasa hidup dan responsif
3. **Weekly Review yang penuh** — ritual mingguan yang menutup semua modul
4. **AI Chat redesign** — fokus "tanya datamu", bukan chat generik, styling terintegrasi
5. **Quick wins** — tumpuk beberapa sekaligus karena masing-masing kecil
