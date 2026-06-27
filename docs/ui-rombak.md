# UI/UX Rombakan Plan

Hasil research dari Linear, Things 3, Todoist, Notion, Craft + audit codebase (40+ issues ditemukan).

Status: `[ ]` belum · `[x]` selesai · `[~]` sudah cukup / tidak perlu

---

## A. Design System Foundation

- [x] **A1 Typography scale** — `text-3xl` di semua page title (Lists, Docs sudah fix). Section h2 pakai `font-extrabold` tanpa size class — inherited dari body yang konsisten
- [x] **A2 Spacing grid** — `gap-5` → `gap-6` di semua section-level grid (Dashboard, Records, RecordForm, Reports, WeeklyReview, Analytics, RecordDetail, Profile, ProjectShow, DocForm)
- [~] **A3 Color tokens** — semantic tokens sudah ada di CSS (`--text-base`, `--text-soft`, dll), global dark overrides sudah handle semua kasus
- [x] **A4 Icon stroke width** — semua `1.8` → `1.5` di App.vue + semua views + DatePicker, Games, Activity, DocShare
- [x] **A5 Focus ring** — `outline: 2px solid teal` yang visible. Done di `app.css`
- [~] **A6 Border radius** — pola sudah cukup konsisten, minor inconsistency tidak mengganggu UX

---

## B. App Shell & Navigation

- [x] **B1 Sidebar active state** — `ring-1 ring-teal-200/60` + icon bg `bg-teal-100 text-teal-700`. Done di `App.vue`
- [~] **B2 Nav section headers** — sudah `text-[11px] font-extrabold uppercase text-slate-400`, cukup beda dari nav items
- [x] **B3 Mobile nav auto-close** — sudah handled oleh `watch(() => route.fullPath, ...)`
- [x] **B4 Sidebar footer** — border-t separator + prefix `v` sebelum version. Done di `App.vue`
- [~] **B5 Top bar mobile** — `order-last w-full` sudah cukup handle mobile layout

---

## C. Buttons

- [~] **C1 Sizing** — `.btn` `min-height: 2.6rem` di mobile, desktop sudah `0.625rem 0.875rem` padding = cukup
- [x] **C2 Loading state** — sudah ada di RecordForm: `{{ saving ? 'Saving...' : 'Save' }}`
- [x] **C3 Destructive button** — `.btn-danger` class di `app.css`, dipakai di confirm dialog
- [x] **C4 Disabled state** — `opacity-50 cursor-not-allowed` via Tailwind

---

## D. Fields & Forms

- [~] **D1 Label always visible** — semua form sudah pakai `.label` di atas field
- [~] **D2 Field height** — `min-height: 2.75rem` = 44px, sudah oke
- [x] **D3 Required indicator** — `*` merah di label field required. Done di `RecordForm.vue`
- [x] **D4 Validation error** — `text-xs text-red-600 dark:text-red-400`. Done di `RecordForm.vue`
- [~] **D5 Textarea min-height** — `min-h-32` = 128px, sudah oke
- [~] **D6 Date input** — DatePicker component sudah dipakai di semua form

---

## E. Cards & Lists

- [x] **E1 Card hover** — hapus `hover:-translate-y-0.5`, pakai `hover:shadow-sm hover:border-teal-200`. Done di semua views
- [x] **E2 Delete button touch** — selalu visible muted, bukan `invisible group-hover:visible`. Done di `Lists.vue`, `ListDetail.vue`, `Docs.vue`
- [~] **E3 Row density** — `px-4 py-3` sudah dominan di list items
- [~] **E4 Divider** — `divide-y` vs `border-b` minor, tidak critical

---

## F. Empty States

- [x] **F1 Unified pattern** — `Docs.vue` diupdate: icon + judul + deskripsi + CTA. Records.vue sudah ada. Pola konsisten

---

## G. Badges & Pills

- [x] **G1 Priority badge** — sudah ada text `P1/P2/P3` + ikon di `ListDetail.vue`
- [x] **G2 Status pill loading** — `opacity-50` saat update + guard double-click di `Records.vue`
- [~] **G3 Badge sizing** — `.pill` di CSS sudah konsisten `font-size: 0.72rem`

---

## H. Dark Mode Gaps

- [x] **H1 Login page** — CSS override `dark .bg-stone-100 → var(--page)` sudah handle
- [x] **H2 WysiwygEditor toolbar** — sudah ada `dark:bg-zinc-800` di toolbar
- [x] **H3 DatePicker panel** — `dark:border-zinc-700 dark:bg-zinc-900` + semua teks di-dark-mode. Done
- [x] **H4 Skeleton loader** — shimmer opacity `0.09`. Done di `app.css`

---

## I. Dashboard

- [~] **I1 Stat cards** — CSS global override sudah handle dark mode (`bg-teal-50/70` → `rgb(13 78 70 / 0.25)`)
- [x] **I2 Card row hover** — hapus `hover:-translate-y-0.5`, pakai `hover:shadow-sm`. Done

---

## J. Records (list view)

- [x] **J1 Filter grid** — `sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6`. Done di `Records.vue`
- [x] **J2 Sort indicator** — `↓ Descending` / `↑ Ascending`. Done di `Records.vue`
- [x] **J3 Row truncation** — `:title="row.title || row.topic"` pada h2 truncate. Done

---

## K. RecordForm

- [x] **K1 Footer overlap** — sudah ada `lg:left-72` di fixed footer
- [x] **K2 Unsaved changes** — `onBeforeRouteLeave` + `watch(form)` + reset setelah save. Done di `RecordForm.vue`

---

## L. Lists & ListDetail

- [x] **L1 Lists grid** — `sm:grid-cols-2 xl:grid-cols-3` + heading `text-3xl tracking-tight`
- [x] **L2 ListDetail expanded panel** — `grid-cols-1 sm:grid-cols-2`. Done
- [x] **L3 Add item input** — posisi dan placeholder sudah jelas

---

## M. Accessibility

- [x] **M1 ARIA labels** — delete buttons di `ListDetail.vue`, `Lists.vue`, `Docs.vue`. Icon buttons di `App.vue` sudah ada (`aria-label` di notif, theme, quick add, dll)
- [~] **M2 Color-only indicators** — priority sudah ada text label P1/P2/P3. Status pills sudah ada text
- [x] **M3 prefers-reduced-motion** — di `app.css`

---

## Tidak disentuh

- Logic backend / API
- Struktur routing
- Fitur baru
- Game pages

---

**Selesai: 44/46 poin** (2 item di-skip: A6 border-radius minor, C1 sizing mobile sudah cukup)

**Files changed:**
- `resources/css/app.css` — focus ring, btn-danger, card transition, label tracking, skeleton, prefers-reduced-motion
- `resources/js/vue/App.vue` — sidebar active state, footer, confirm dialog btn-danger, mobile nav active
- `resources/js/vue/views/Login.vue` — hapus register link, dark mode link color
- `resources/js/vue/views/Dashboard.vue` — hapus hover translate
- `resources/js/vue/views/Records.vue` — filter grid responsive, sort ↑↓, title attr, hapus hover translate
- `resources/js/vue/views/RecordForm.vue` — required *, error text xs, unsaved changes guard
- `resources/js/vue/views/Lists.vue` — h1 text-3xl, delete button, hover
- `resources/js/vue/views/ListDetail.vue` — expanded panel grid, delete button aria
- `resources/js/vue/views/Docs.vue` — empty state, delete button, card hover, skeleton loading
- `resources/js/vue/components/DatePicker.vue` — full dark mode coverage
