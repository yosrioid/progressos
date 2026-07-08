# Pull Request Resolve Guide

Panduan untuk author PR ProgressOS: cara merespons review, resolve conversation, dan melanjutkan ke merge. Tujuan utama: iterasi review yang cepat, jelas, dan minim back-and-forth.

---

## Mindset

Author yang baik itu **responsif** dan **transparan**:

- Respond ke setiap komentar,即便 tidak ada perubahan.
- Puji reviewer yang menangkap masalah — itu kolega yang membantu.
- Kalau tidak setuju, jelaskan dengan data, bukan asumsi.
- Setelah fix, tunjukkan **apa** yang berubah dan **dimana**.

---

## Alur Setelah Review Masuk

```
Reviewer post komentar
        ↓
Author baca semua komentar (jangan skip)
        ↓
Kategorikan: blocker / suggestion / question / nit / praise
        ↓
Untuk blocker: fix → commit → reply "Fixed in <commit-sha>"
Untuk suggestion: evaluate → fix atau jelaskan kenapa skip
Untuk question: jawab
Untuk nit: fix atau reply "akan defer, bukan blocker"
Untuk praise: acknowledge
        ↓
Push perubahan
        ↓
Resolve conversation yang sudah selesai
        ↓
Minta re-review kalau ada perubahan
```

---

## Cara Reply Komentar

### Tone

- **Singkron dan berterima kasih**.
- **Tunjukkan bukti** kalau ada fix: link ke commit, diff, atau test.
- **Jangan defensif** — kalau reviewer benar, akui.
- **Jangan silent** — kalau belum sempat fix, acknowledge dengan timeline.

### Template Reply

Untuk komentar yang di-fix:

```text
Fixed in <commit-sha>. <Penjelasan singkat apa yang berubah>.

```diff
- if (theme === 'system') { applyTheme('system'); }
+ applyTheme(activeTheme.value);
```

Tests: added 1 test case for null user.theme fallback.
```

Untuk komentar yang ditolak (setelah diskusi):

```text
Saya diskusi offline dengan <reviewer> dan sepakat untuk tidak mengubah
ini karena <alasan>. Kita bisa revisit kalau ada use case baru.
```

Untuk pertanyaan:

```text
Dipakai `localStorage` karena pilihan theme cepat berubah tiap klik
dan tidak perlu round-trip ke server. Server tetap authoritative
melalui `user.theme` saat first load.
```

---

## Resolve Conversation

GitHub punya fitur "Resolve conversation" di setiap thread inline comment. **Resolve hanya setelah**:

1. Fix sudah di-commit dan di-push.
2. Author sudah reply dengan bukti perubahan.
3. Issue / pertanyaan sudah benar-benar selesai (bukan di-defer).

### Yang **Tidak Boleh** Di-resolve

- Conversation yang belum ada fix-nya.
- Conversation yang ada pertanyaan tapi belum dijawab author.
- Conversation yang masih dalam diskusi (belum ada共识).

### Workflow

1. **Buka PR Files tab** → scroll ke komentar.
2. Untuk tiap thread yang sudah selesai, klik **"Resolve conversation"**.
3. **Jangan resolve dari conversation list** tanpa baca thread — bisa skip reply yang penting.

---

## Update Branch dengan main

Saat review berjalan, `main` mungkin sudah maju. Sebelum merge:

```bash
# Fetch latest
git fetch origin main

# Lihat apa yang akan di-merge
git log --oneline origin/main..HEAD

# Lihat potensi conflict
git diff origin/main --stat
```

### Tanpa Conflict

```bash
git merge origin/main
# atau
git rebase origin/main   # kalau mau history lebih bersih
```

### Dengan Conflict

```bash
git fetch origin main
git rebase origin/main
# Resolve conflict per file
git add <resolved-files>
git rebase --continue
git push --force-with-lease
```

Gunakan `--force-with-lease`, **bukan** `--force`, agar tidak menimpa commit orang lain kalau ada push paralel.

---

## Re-request Review

Setelah push perubahan yang menanggapi review:

1. **Re-request review** dari reviewer yang sama (klik tombol "Re-request review" di sidebar PR).
2. **Komentar singkat** di PR conversation:

```text
Fixed semua blocker di <commit-sha>:
- ownership check di PATCH /api/v1/records/{id} (issue: ...)
- extract validation ke UpdateProfileRequest (suggestion: ...)

Tinggal 1 nit yang saya defer: typo di comment line 42. Bukan blocker,
bisa di-cleanup di PR berikutnya.

Re-request @reviewer untuk final check.
```

---

## Sebelum Merge

Pastikan checklist ini sudah lengkap:

- [ ] Semua `issue:` di-comment sudah di-fix.
- [ ] Semua `question:` sudah dijawab.
- [ ] `nit:` dan `suggestion:` yang di-accept sudah di-handle.
- [ ] CI hijau (cek status check rollup).
- [ ] Branch sudah up-to-date dengan `main` (no conflict).
- [ ] `composer lint`, `composer analyse`, `php artisan test`, `npm run build` semua hijau lokal.
- [ ] `docs/openapi.yaml` dan `php artisan docs:sync` sudah dijalankan (kalau ada endpoint baru).
- [ ] `docs/pr-resolve.md` checklist sudah selesai.

---

## Self-merge vs Tunggu Reviewer

| Situasi | Rekomendasi |
| --- | --- |
| PR kecil, tidak ada review yang request changes | Author boleh merge setelah approve |
| PR dengan perubahan arsitektur | Tunggu explicit approve dari reviewer |
| PR sudah lebih dari 2 hari tanpa aktivitas | Ping reviewer dengan `@reviewer friendly ping` |
| PR sudah lebih dari 5 hari tanpa review | Boleh self-merge setelah CI hijau, tapi tetap tunggu kalau blocker |

**Tidak boleh self-merge** kalau:

- Ada review yang statusnya "Request Changes" dan belum di-resolve.
- PR mengubah API contract (perlu sign-off dari maintainer).
- PR menyentuh auth, payment, atau data destruction.

---

## Cara Squash Commit Sebelum Merge

Kalau history berantakan (banyak small commit, fix-up, revert), squash jadi 1 commit:

```bash
# Interactive rebase untuk squash commit terakhir N
git rebase -i HEAD~5
# Di editor, ganti 'pick' jadi 'squash' atau 'fixup' untuk commit yang mau digabung
```

Atau, **lebih sering lebih baik** squash via UI GitHub saat merge dengan opsi "Squash and merge". Ini menggabungkan semua commit jadi 1 dengan pesan yang bisa di-edit.

---

## Setelah Merge

1. **Hapus branch** di remote (GitHub biasanya auto-prompt).
2. **Hapus branch** lokal: `git branch -d feature/<name>`.
3. **Update local main**: `git checkout main && git pull`.
4. **Close issue** yang terkait jika ada: `Closes #123`.
5. **Pantau CI di main** selama beberapa menit — kalau ada post-merge failure, rollback cepat.

### Rollback Post-merge

```bash
# Revert commit terakhir di main
git checkout main
git pull
git revert -m 1 <merge-commit-sha>   # untuk merge commit
git push
```

Atau pakai GitHub UI: **Revert** button di merge commit.

---

## Anti-patterns (Hindari)

- **Resolve tanpa fix** — reviewer akan protes dan reopen conversation.
- **Mass "fixed everything"** tanpa detail perubahan — reviewer harus chase Anda untuk klarifikasi.
- **Push fix sebagai commit terpisah tanpa reply** — reviewer tidak tahu commit mana yang fix apa.
- **Debat berkepanjangan** di inline comment — pindah ke Slack/chat kalau sudah超过 3 round-trip.
- **Merge dengan CI merah** — kalau CI gagal, fix dulu atau re-run.
- **Lupa update branch dengan main** — menyebabkan conflict saat merge dan delay release.

---

## Referensi

- `docs/code-standards.md` — standar kode yang harus dijaga saat fix.
- `docs/git-workflow.md` — branch, commit, push rules.
- `docs/pr-template.md` — template body PR (author side).
- `docs/pr-review.md` — panduan reviewer (untuk paham ekspektasi mereka).
- `docs/openapi.yaml` — API contract.
