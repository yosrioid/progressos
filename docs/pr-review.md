# Pull Request Review Guide

Panduan untuk reviewer ProgressOS: cara melakukan code review, fokus area, dan format output komentar. Tujuan utama: review yang konsisten, actionable, dan ramah untukkolaborator.

---

## Mindset

Reviewer bukan gatekeeper — reviewer adalah kolaborator yang bantu author menghasilkan kode lebih baik. Prinsip utama:

- **Tujuan utama**: tangkap bug, percepat future debugging, sebarkan pengetahuan.
- **Tujuan sekunder**: konsistensi style, arsitektur, dan testing.
- **Bukan tujuan**: menunjukkan siapa yang lebih tahu, atau memaksakan preferensi pribadi.

Review yang baik itu spesifik, bisa di-aksi, dan menjelaskan **kenapa** — bukan cuma **apa**.

---

## Sebelum Mulai Review

1. **Baca deskripsi PR** dan link ke issue. Pahami konteks dan trade-off yang author pilih.
2. **Cek PR template** — author harus mengisi checklist (build, test, breaking changes, follow-up).
3. **Cek CI** — hijau atau tidak. Kalau merah, minta author fix dulu sebelum review substantive.
4. **Cek size PR** — ideal di bawah 400 baris diff. Kalau lebih besar:
   - Tanya apakah bisa di-split.
   - Review per-file fokus tanpa harus baca semuanya.
   - Set ekspektasi: review kali ini mungkin kurang mendalam.

---

## Area Fokus Review

### 1. Correctness (Priority Tinggi)

- **Bug logika** — conditional salah, edge case tidak di-handle, off-by-one.
- **Race condition** — async flow yang bisa saling tumpang tindih.
- **Resource leak** — file/connection tidak di-close, transaction tidak di-rollback.
- **Error handling** — silent failure, generic catch, swallowed exception.
- **Boundary check** — apa yang terjadi saat input kosong, null, atau sangat besar.

### 2. Security (Priority Tinggi)

- **Auth check** — endpoint baru wajib ada middleware `auth:sanctum` + ownership check. Lihat `docs/code-standards.md` untuk pola `ownedBy()`.
- **Authorization** — user biasa tidak bisa akses resource admin (perhatikan bug theme toggle yang sebelumnya: `PUT /api/admin/configuration/settings` dipanggil dari user flow).
- **Input validation** — semua input dari `Request` wajib lewat Form Request atau `validate()`.
- **Mass assignment** — pastikan `$fillable` di model tidak terlalu longgar, atau pakai `validated()` di controller.
- **SQL injection** — query string interpolation tanpa binding. Lihat pola di `App\Support\ApiQuery`.
- **XSS** — di Vue, jangan pakai `v-html` untuk data user tanpa sanitasi.
- **Secret** — API key, token, password di-hardcode atau ter-log.
- **File upload** — validasi MIME type, ukuran, dan simpan di luar `public/` jika rahasia.

### 3. Performance

- **N+1 query** — loop dengan akses relasi tanpa eager load.
- **Missing index** — query yang filter/sort pada kolom tanpa index. Untuk personal app, biasanya tidak masalah, tapi flag kalau terlihat berat.
- **Large response** — endpoint yang return seluruh table tanpa pagination.
- **Synchronous heavy work** — export, email, atau AI call di request cycle (harusnya queue).

### 4. Maintainability

- **Naming** — variabel/fungsi menjelaskan intent, bukan implementasi.
- **Function size** — kalau satu method > 50 baris, biasanya bisa dipecah.
- **Duplikasi** — pola yang sama muncul 3+ kali = seharusnya abstraksi.
- **Magic number/string** — hardcoded value yang seharusnya constant atau config.
- **Comment** — ada untuk menjelaskan **kenapa**, bukan **apa**. Kalau comment menjelaskan apa, biasanya bisa hilang.

### 5. Konsistensi dengan `docs/code-standards.md`

- **Backend**: `ApiResponse`, Form Request, API Resource, ownership scope, service untuk aggregation.
- **Frontend**: `api.ts` + `unwrap()`, toast via `feedback.ts`, Tailwind classes reused, no design system baru.
- **Naming payload**: `entry`/`log`/`task`/`entry`/`milestone` per module.
- **Bahasa**: UI, toast, error message, label, placeholder dalam **Inggris**. Komentar dan komunikasi developer boleh Indonesia.

### 6. Test Coverage

- **Backend feature test** untuk endpoint baru — happy path + 1-2 negative case.
- **Auth-sensitive code** wajib punya test unauthorized.
- **E2E test** untuk user-visible flow baru.
- **Test yang ada di-update** kalau ada perubahan API contract.

### 7. Migration & Rollback

- **Migration** reversible. Hindari `down()` kosong tanpa alasan.
- **Data migration** hati-hati — backup atau feature-flag.
- **Breaking change** harus eksplisit di judul dan body PR.

### 8. Docs Update

- `docs/openapi.yaml` untuk endpoint baru atau perubahan API.
- `docs/code-standards.md` untuk pola baru.
- `docs/git-workflow.md` atau `docs/pr-template.md` untuk perubahan workflow.
- Setelah update `openapi.yaml`, jalankan `php artisan docs:sync`.

---

## Cara Memberi Komentar

### Tone

- **Langsung dan spesifik**, bukan模糊.
- **Ajukan pertanyaan** kalau tidak yakin, bukan asumsi.
- **Puji** kalau ada hal bagus — code review bukan cuma soal mencari masalah.
- **Hindari** kata-kata judgemental: "ini salah", "kok gini", "tidak masuk akal".

### Contoh Kalimat yang Baik vs Buruk

| Buruk | Baik |
| --- | --- |
| "Kode ini salah." | "Di line 142, kalau `user.theme` null, `activeTheme` jadi `null`, bukan `'system'`. Mungkin perlu fallback." |
| "Harusnya pakai Form Request." | "Bisa extract validasi ini ke `UpdateProfileRequest`? Lebih mudah di-test dan konsisten dengan pola di `AuthController`." |
| "Tidak optimal." | "Query ini kena N+1 kalau user punya 10 tasks. Coba eager load dengan `with('tasks')` di line 88." |
| "Saya tidak setuju." | "Saya khawatir pendekatan ini akan menyulitkan saat theme disimpan per-device. Apa dipertimbangkan pakai `user.theme` di `auth.ts` sebagai gantinya?" |

### Jenis Komentar

Gunakan prefix untuk klarifikasi jenis:

- **`nit:`** — minor style/preferensi, optional. Author boleh skip.
- **`suggestion:`** — saran perbaikan, bukan blocker.
- **`question:`** — butuh klarifikasi dari author.
- **`issue:`** — masalah yang harus di-fix sebelum merge (bug, security, breaking).
- **`praise:`** — hal bagus yang layak di-highlight.

Contoh:

```text
issue: endpoint ini tidak ada check ownership. User A bisa akses data User B
dengan PATCH /api/v1/records/{id} tanpa middleware `ownedBy()`.

suggestion: extract `cycleTheme` ke composable `useTheme()`. Logic-nya bisa
di-reuse di profile page atau settings lain.

nit: typo di comment line 42 — "temprorary" → "temporary".

question: kenapa pakai `localStorage` bukan `user.theme` di database?
Apakah dipertimbangkan untuk sync antar device?

praise: nice catch dengan `themeOverride` ref untuk optimistic update.
```

---

## Format Review Output

Saat selesai review, post satu ringkasan umum di PR conversation (tidak sebagai inline comment). Gunakan template ini:

```markdown
## Review Summary

**Status**: ✅ Approve / 💬 Comment / ❌ Request Changes

**Verdict**: Ringkasan 1-2 kalimat tentang overall assessment.

### Hal yang bagus
- ...
- ...

### Harus di-fix (blocker)
- [ ] ...
- [ ] ...

### Disarankan (non-blocker)
- [ ] ...
- [ ] ...

### Pertanyaan / diskusi
- ...

### Test yang dijalankan
- [ ] composer lint
- [ ] composer analyse
- [ ] php artisan test
- [ ] npm run build
- [ ] npm run test:e2e (kalau ada perubahan UI)
```

---

## Kapan Set Status

- **Approve** — tidak ada blocker, minor issue sudah resolved atau di-defer ke follow-up.
- **Comment** — ada saran/pertanyaan tapi tidak memblok merge. Author boleh merge setelah respond.
- **Request Changes** — ada `issue:` yang harus di-fix. Reviewer wajib re-review setelah author push.

---

## Saat Approve

1. Set "Approve" review.
2. Pilih merge strategy yang direkomendasikan (lihat `docs/git-workflow.md` bagian "Setelah Review Approve").
3. Kalau ada follow-up yang sudah di-identify tapi tidak blocking, post sebagai separate issue atau note di PR body.

---

## Anti-patterns (Hindari)

- **Nitpicking berlebihan** — kalau 90% PR sudah baik, jangan blok merge untuk hal sepele.
- **Review terlalu dalam untuk PR kecil** — bug fix 10 baris tidak perlu architectural review.
- **Diam setelah approve** — selalu tinggalkan ringkasan, bahkan kalau cuma "LGTM, nice work".
- **Debat panjang di inline comment** — pindah ke discussion kalau sudah超过 3 round-trip.
- **Self-approve** — hindari review PR yang Anda buat sendiri, minta orang lain.

---

## Referensi

- `docs/code-standards.md` — standar kode backend dan frontend.
- `docs/git-workflow.md` — commit dan push rules.
- `docs/pr-template.md` — template body PR author.
- `docs/pr-resolve.md` — cara author merespons review.
- `docs/openapi.yaml` — API contract.
