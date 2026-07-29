# Frontend Audit — 2026-07-09

Audit menyeluruh terhadap Vue 3 + TypeScript + Pinia + TailwindCSS codebase: arsitektur, performa, UX, code quality, dan developer experience.

---

## 1. ROUTING & LAZY LOADING

### 1.1 Semua Routes Eager-Loaded — 🔴 Kritis
`router.ts` meng-import semua views secara statis di baris 3-36. Tidak ada single route yang lazy-loaded.

```ts
// router.ts:3-36 — 34 eager imports
import Login from './views/Login.vue';
import Dashboard from './views/Dashboard.vue';
// ... 32 views lainnya
```

**Impact:** Semua 34 view components di-load di initial bundle, meskipun user hanya mengakses 1-2 halaman. Initial load time akan berat, terutama untuk game views (Sudoku 1044 lines, Minesweeper 810 lines).

**Fix:** Gunakan dynamic imports:
```ts
{ path: '/login', component: () => import('./views/Login.vue'), meta: { guest: true } },
{ path: '/games/sudoku', component: () => import('./views/Sudoku.vue') },
```

### 1.2 Tidak Ada 404 Handler
Tidak ada catch-all route (`/`) di `router.ts`. User yang membuka `/non-existent-route` akan melihat halaman kosong tanpa feedback.

**Fix:** Tambahkan:
```ts
{ path: '/:pathMatch(.*)*', component: () => import('./views/Error404.vue') },
```

### 1.3 Tidak Ada scrollBehavior
Vue Router tidak memiliki `scrollBehavior` configured. Saat navigasi back/forward, scroll position tidak di-reset dan tidak ada smooth scrolling.

**Fix:** Tambahkan:
```ts
scrollBehavior(to, from, savedPosition) {
  if (savedPosition) return savedPosition;
  if (to.hash) return { el: to.hash, behavior: 'smooth' };
  return { top: 0, behavior: 'smooth' };
}
```

### 1.4 Route Redirection Chain
Beberapa redirect chain: `/analytics` → `/activity`, `/weekly-review` → `/reports/weekly`. Chain redirect menambah latency dan bisa membingungkan user.

**STATUS:** Minor — acceptable untuk SEO purposes.

---

## 2. COMPONENT ARCHITECTURE

### 2.1 Configuration.vue — 1021 Lines 🔴 Overwhelm
View terbesar di codebase dengan 1021 baris, menggabungkan:
- AI Configuration
- Google Sheets Sync
- Auth Configuration
- Mail Configuration
- Daily Quote Configuration
- Feature Flags

**Impact:** Susit di-maintain, slow HMR, sulit di-test.

**Fix:** Split menjadi sub-components:
- `<AiConfigSection>`
- `<GoogleSheetsSection>`
- `<AuthConfigSection>`
- `<MailConfigSection>`
- `<QuoteConfigSection>`
- `<FeatureFlagsSection>`

### 2.2 Views Terlalu Besar
| View | Lines | Issue |
|------|-------|-------|
| `Sudoku.vue` | 1044 | Game logic + UI campur |
| `Minesweeper.vue` | 810 | Game logic + UI campur |
| `Game2048.vue` | 685 | Game logic + UI campur |
| `Bills.vue` | 622 | Recurring bill logic terlalu kompleks |
| `Chat.vue` | 603 | Message rendering + emoji picker |
| `RecordDetail.vue` | 555 | Detail view terlalu banyak logic |

**Pattern umum:** Views > 500 lines harus dipecah. Game views (Sudoku, Minesweeper, 2048) sebaiknya extract game engine ke composable.

### 2.3 Navigation Duplication
Struktur navigasi (`navGroups`) didefinisikan di 3 tempat berbeda di `App.vue`:
- Sidebar navigation
- Header navigation  
- Mobile drawer navigation

Perubahan navigasi harus dilakukan di 3 tempat — mudah lupa dan menyebabkan inconsistency.

**Fix:** Extract ke `composables/useNavigation()` atau Pinia store.

### 2.4 Components Kecil & Terfokus
Components yang ada relatif kecil dan fokus:
- `AiQuotaStatus.vue` — status display
- `BarChart.vue` — chart component
- `DailyProgressLists.vue` — list dengan drag-and-drop
- `DailyQuote.vue` — quote display
- `DatePicker.vue` — date picker wrapper
- `PinGate.vue` — PIN input gate
- `QuotaExceededBanner.vue` — notification banner
- `WorkTimer.vue` — timer component
- `WysiwygEditor.vue` — rich text editor
- `ChatBubble.vue` — chat message component

**STATUS: ✅ GOOD** — komponen-komponen ini reusable dan terfokus.

---

## 3. STATE MANAGEMENT (PINIA)

### 3.1 Stores Terdaftar
| Store | File | Purpose |
|-------|------|---------|
| Auth | `stores/auth.ts` | User data, boot, token |
| Configuration | `stores/configuration.ts` | App settings, AI config |
| Inbox | `stores/inbox.ts` | Conversations, messages |
| Privacy | `stores/privacy.ts` | PIN gate, timer |

### 3.2 Direct API Calls di Views
Banyak views melakukan `api.get()` langsung tanpa melalui store:
- `Dashboard.vue` — langsung fetch `/api/v1/records`
- `Records.vue` — langsung fetch records
- `Configuration.vue` — langsung fetch AI config
- `Games.vue` — langsung fetch games list
- `Bills.vue` — langsung fetch bills
- `Goals.vue` — langsung fetch goals
- `Habits.vue` — langsung fetch habits

**Impact:** Tidak ada caching, tidak ada deduplication, setiap navigasi membuat request baru. Data tidak ter-share antar views.

**Fix:** Buat stores untuk domain yang sering diakses:
- `useDashboardStore()` — stats, recent records
- `useBillStore()` — bills, upcoming
- `useGoalStore()` — goals, KR progress
- `useHabitStore()` — habits, streaks

### 3.3 Polling Tanpa Exponential Backoff
`stores/inbox.ts` dan `App.vue` menggunakan polling interval:
- `App.vue:483` — notification refresh setiap 60 detik
- `ChatBubble.vue:37` — unread check setiap 15 detik
- `ChatBubble.vue:38` — conversation refresh setiap 30 detik
- `inbox.ts` — message refresh setiap 5 detik

Tidak ada retry logic atau exponential backoff. Jika server down, semua polling terus mencoba.

**Fix:** Tambahkan retry dengan backoff:
```ts
function pollWithBackoff(fn, intervalMs) {
  let retries = 0;
  const timer = setInterval(async () => {
    try {
      await fn();
      retries = 0;
    } catch (e) {
      retries++;
      if (retries > 3) clearInterval(timer);
    }
  }, intervalMs * Math.pow(2, retries));
}
```

### 3.4 `applyGroups()` Accepts `any`
`stores/configuration.ts:106` — `applyGroups(groups: any)` tidak memvalidasi keys. Bisa menerima object sembarangan tanpa error.

**Fix:** Tambahkan TypeScript interface untuk config groups.

---

## 4. API CLIENT

### 4.1 Minimal Interceptor
`api.ts` hanya punya 1 response interceptor:
```ts
// api.ts:8-16
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401 && !startsWithLogin) {
      window.location.href = '/login';
    }
    return Promise.reject(error);
  }
);
```

**Yang tidak ada:**
- ~~CSRF 419 auto-retry~~ — session expired, POST gagal tanpa retry
- ~~Global error handler~~ — setiap view harus handle error sendiri
- ~~Loading state management~~ — tidak ada global loading
- ~~Caching~~ — GET requests tidak di-cache
- ~~Deduplication~~ — 2 view yang fetch data sama = 2 API calls
- ~~Timeout~~ — tidak ada default timeout
- ~~Retry logic~~ — network error langsung reject

### 4.2 Error Handling Tidak Seragam
Setiap view punya pola error handling berbeda:
- `Records.vue` — try/catch dengan `.then(unwrap)`
- `Configuration.vue` — inline catch blocks
- `Bills.vue` — try/catch di banyak fungsi
- `Chat.vue` — `.catch()` di beberapa tempat, tapi tidak semua

**Risk:** View yang lupa `.catch()` bisa crash atau stuck loading.

### 4.3 Magic Strings di API Paths
API paths tersebar sebagai string literal:
- `/api/v1/records`
- `/api/v1/reports/weekly`
- `/api/admin/configuration/ai`
- `/api/inbox/conversations`
- `/api/games/leaderboard`

**Fix:** Buat constants file:
```ts
// api-paths.ts
export const API = {
  RECORDS: '/api/v1/records',
  REPORTS_WEEKLY: '/api/v1/reports/weekly',
  ADMIN_CONFIG_AI: '/api/admin/configuration/ai',
  INBOX_CONVERSATIONS: '/api/inbox/conversations',
};
```

---

## 5. TYPESCRIPT QUALITY

### 5.1 `any` Usage — 33 Instances
```
stores/configuration.ts:106  applyGroups(groups: any)
views/Records.vue:10         const learningStats = ref<any>(null)
views/Records.vue:19         const meta = ref<any>(null)
views/Records.vue:74         async function toggleDefaultView(view: any)
views/Records.vue:134        function isOverdue(row: any)
views/Records.vue:150        async function removeSavedView(view: any)
App.vue:216                  } catch (e: any)
App.vue:224                  .map((h: any) => ...)
...
```

**Impact:** Type safety hilang di 33+ tempat. Refactoring jadi risky.

**Fix:** Buat proper interfaces untuk semua data shapes.

### 5.2 Missing Type Definitions
Tidak ada shared type definitions di codebase. Setiap view mendefinisikan tipe datanya sendiri-sendiri.

**Fix:** Buat `resources/js/vue/types/` folder:
```ts
// types/record.ts
export interface Record {
  id: number;
  type: string;
  title: string;
  content: string;
  tags: string[];
  created_at: string;
}

// types/game.ts
export interface GameLeaderboardEntry {
  id: number;
  user_id: number;
  score: number;
  created_at: string;
}
```

---

## 6. PERFORMANCE

### 6.1 Bundle Size — Belum Optimal
`vite.config.js` sudah ada manual chunking untuk:
- `vendor-vue` (vue, pinia, @vue)
- `vendor-dompurify`
- `vendor-tiptap`

**Yang belum di-chunk:**
- Semua views di main bundle (tidak lazy-loaded)
- Game engines (Sudoku solver, Minesweeper grid) di main bundle
- Chart libraries (jika ada) di main bundle

### 6.2 Large Lists Without Virtualization
Views seperti `Records.vue` menampilkan daftar records yang bisa sangat panjang. Tidak ada virtualization (windowing).

**Fix:** Pertimbangkan `vue-virtual-scroller` untuk lists > 100 items.

### 6.3 Computed Properties di Templates
`Chat.vue` dan `Bills.vue` memiliki computed properties yang di-re-evaluate setiap message/data change. Dengan 8 `v-for` loops di Chat.vue, bisa ada re-render overhead.

### 6.4 Interval Leak Risk
`WorkTimer.vue` punya 7 `clearInterval` calls — ada risk interval tidak ter-clear jika component unmount sebelum interval selesai.

**STATUS:** `WorkTimer.vue:148` sudah punya `onBeforeUnmount` cleanup — OK.

---

## 7. CODE DUPLICATION

### 7.1 Reports.vue ↔ WeeklyReview.vue
Kedua views punya ~120 lines kode identik:
- `REFLECTION_KEY` constant
- `weekOffset` computed
- `loadSnapshots()` / `saveSnapshot()` localStorage pattern
- Fetch `/api/v1/reports/weekly`
- Week navigation UI

**Fix:** Extract ke `useWeeklyReport()` composable + `<WeeklyReportContent>` component.

### 7.2 DailyProgressLists.vue ↔ TaskBoard.vue
Keduanya implement drag-and-drop dengan `draggable="true"` dan `@click` handlers. Pattern hampir identik.

**Fix:** Buat `useSortableList()` composable.

### 7.3 Error Handling Pattern Duplication
Try/catch blocks dengan pola yang sama muncul di 20+ views:
```ts
try {
  const res = await api.post('/api/...');
  // success
} catch (e) {
  // error handling
}
```

**Fix:** Gunakan `useAsyncData()` composable yang sudah ada, atau buat wrapper function.

---

## 8. ACCESSIBILITY (A11Y)

### 8.1 Missing ARIA Labels
Dari 62 `aria-*` attributes yang ada, masih banyak yang kurang:
- `DailyProgressLists.vue` — draggable items tanpa `role="listitem"`
- `TaskBoard.vue` — kanban columns tanpa `aria-label`
- `Configuration.vue` — help toggles tanpa `aria-expanded`
- `PinGate.vue` — PIN input tanpa `aria-describedby`

### 8.2 Keyboard Navigation
- Emoji picker di `Chat.vue` tidak ada keyboard navigation
- Modal dialogs tidak ada focus trap
- Tab order di `Configuration.vue` tidak konsisten

### 8.3 Screen Reader Support
- `v-html` di `Search.vue`, `DocDetail.vue`, `DocShare.vue` — DOMPurified tapi tidak ada `aria-live` untuk updates
- Loading states tidak di-announce ke screen readers

---

## 9. FORM HANDLING

### 9.1 Generic Record System — Bagus
`records.ts` menyediakan `normalizeForForm()` dan `serializeRecord()` yang dipakai oleh `RecordForm.vue` dan `RecordDetail.vue`. Pola ini konsisten dan reusable.

**STATUS: ✅ GOOD**

### 9.2 Non-Generic Forms
Forms yang tidak pakai RecordForm:
- `Configuration.vue` — 6 form sections dengan manual field binding
- `Chat.vue` — message input
- `JournalShow.vue` — journal entry form
- `Goals.vue` — goal/KR forms
- `Bills.vue` — bill creation form

**Risk:** Tidak ada unified validation, error display, atau unsaved changes warning.

### 9.3 Missing Features
- ~~Unsaved changes warning~~ — tidak ada di forms yang ada
- ~~Field-level error display~~ — hanya toast/global error
- ~~Submit button disabled state~~ — tidak seragam
- ~~Required field indicators~~ — tidak konsisten

---

## 10. DARK MODE

### 10.1 Tailwind Dark Mode
Menggunakan `darkMode: 'class'` — manual toggle via `html.dark` class.

### 10.2 Coverage
Dark mode implemented via Tailwind `dark:` variants. Namun:
- `Configuration.vue` — beberapa hardcoded colors (`bg-white`, `text-slate-800`) tidak punya dark variant
- `Games.vue` — game boards mungkin tidak adaptif ke dark mode
- `Bills.vue` — financial charts mungkin perlu dark palette

**Fix:** Run `npx tailwindcss --dark-mode-class` check atau audit manual.

---

## 11. MOBILE RESPONSIVENESS

### 11.1 Breakpoints
Menggunakan Tailwind default breakpoints: sm (640px), md (768px), lg (1024px), xl (1280px).

### 11.2 Mobile Navigation
Mobile drawer navigation ada di `App.vue` — hamburger menu pattern.

### 11.3 Touch Targets
Beberapa buttons di `Configuration.vue` dan `Records.vue` mungkin terlalu kecil untuk touch targets (< 44px).

### 11.4 Non-Responsive Tables
`Records.vue` menampilkan data dalam tabel — tabel tidak responsive di mobile.

**Fix:** Buat card view alternatif untuk mobile.

---

## 12. E2E TESTING

### 12.1 Test Coverage
File: `tests/e2e/progressos.spec.ts` — hanya ~10 tests.

**Yang di-test:**
- Basic CRUD operations
- Avatar upload
- Paste behavior

**Yang TIDAK di-test:**
- Login/Register flow
- Forgot password flow
- AI chat interaction
- Quota exceeded scenarios
- Admin user management
- Error states (network failure, permission denied)
- Mobile responsive layouts
- Dark mode functionality
- Accessibility

### 12.2 Test Reliability
Tidak ada test data cleanup strategy. Tests bisa saling interfere.

---

## 13. DEPENDENCIES

### 13.1 Key Dependencies
Dari `package.json` (runtime):
- `vue` — framework
- `vue-router` — routing
- `pinia` — state management
- `axios` — HTTP client
- `dompurify` — XSS prevention
- `@tiptap/*` — rich text editor
- `@tailwindcss/vite` — CSS framework
- `vite` — build tool

### 13.2 Unused/Redundant
Perlu cek dengan `depcheck` atau `npm-unused` untuk memastikan tidak ada dependencies yang tidak dipakai.

---

## 14. DEVELOPER EXPERIENCE

### 14.1 Vite Config — Cukup
- Hot Module Replacement (HMR) aktif via `laravel-vite-plugin`
- Manual chunking sudah ada untuk vendor libs
- Server watch ignores Blade compiled views — OK

### 14.2 TypeScript Paths
Perlu cek apakah ada `paths` alias di `tsconfig.json` untuk cleaner imports.

### 14.3 Composables
Terdapat 3 composables:
- `useAsyncData.ts` — loading/error state wrapper
- `useClickOutside.ts` — click outside detection
- `useConversationSession.ts` — chat session management

**Gap:** Tidak ada composable untuk:
- Weekly report logic
- Filter/sort logic (Records.vue)
- Polling with backoff
- Drag-and-drop
- Form validation

---

## 15. ACTION ITEMS (Prioritized)

### 🔴 CRITICAL
| # | Issue | Impact | Effort |
|---|-------|--------|--------|
| 1 | Semua routes eager-loaded | Initial bundle besar, slow load | 30 min |
| 2 | Tidak ada 404 handler | Halaman kosong untuk invalid routes | 15 min |
| 3 | Configuration.vue 1021 lines | Sulit maintain, slow HMR | 4 hours |
| 4 | No global error handler | Views bisa crash/stuck loading | 1 hour |

### 🟡 HIGH
| # | Issue | Impact | Effort |
|---|-------|--------|--------|
| 5 | 33+ instances of `any` | No type safety, risky refactoring | 4 hours |
| 6 | Reports ↔ WeeklyReview duplication | ~120 lines duplicate | 2 hours |
| 7 | Direct API calls di views | No caching, no dedup | 6 hours |
| 8 | No scrollBehavior | Jarring navigation | 30 min |
| 9 | Magic strings di API paths | Hard to refactor | 1 hour |
| 10 | Polling tanpa backoff | Wasted requests saat server down | 2 hours |

### 🟢 MEDIUM
| # | Issue | Impact | Effort |
|---|-------|--------|--------|
| 11 | Missing ARIA labels | Poor accessibility | 3 hours |
| 12 | Large lists no virtualization | Laggy dengan 100+ items | 4 hours |
| 13 | Navigation duplication (3x) | Inconsistent nav | 1 hour |
| 14 | Forms tanpa unsaved warning | Data loss risk | 2 hours |
| 15 | Non-responsive tables | Bad mobile UX | 2 hours |

### ℹ️ LOW
| # | Issue | Impact | Effort |
|---|-------|--------|--------|
| 16 | E2E test coverage rendah | Untested user flows | 8 hours |
| 17 | Dark mode inconsistencies | Some components look wrong | 2 hours |
| 18 | Touch targets di config | Hard to tap on mobile | 1 hour |
| 19 | Hardcoded colors di views | Theme inconsistency | 3 hours |

---

## 16. SUMMARY

| Category | Rating | Notes |
|----------|--------|-------|
| Architecture | B- | Views too large, no lazy-loading, direct API calls |
| State Management | C | Pinia underutilized, stores only for auth/inbox/privacy |
| TypeScript | C | 33+ `any` usages, no shared types |
| Performance | C | All views eager-loaded, no virtualization |
| Code Quality | B | Some duplication, but good component patterns |
| Accessibility | C | Missing ARIA labels, poor keyboard nav |
| Mobile UX | B | Responsive layout, but tables not adaptive |
| Testing | D | Only ~10 E2E tests, no unit tests |
| DX | B | Good Vite config, HMR works, could improve composables |
| **Overall** | **C+** | Solid foundation, major improvements needed |
