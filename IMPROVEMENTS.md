# Improvements Tracker — ProgressOS

Trafik daftar semua improvement / feature yang ditargetkan, status tiap item, dan link PR/commit jika sudah ada. **Diperbarui tiap kali ada item diselesaikan.**

---

## 🚨 Critical (Security & Reliability)

| # | Area | Issue | Status | PR/Commit | Notes |
|---|------|-------|--------|-----------|-------|
| 1 | **Authorization** | `OwnedModelPolicy.viewAny = true` returns true always; many controllers manual check vs policies → data leak risk | ✅ FIXED | #89 merged (`67e3638`) | [Policy cleanup in progress](https://github.com/yosrioid/progressos/issues/) — review all controllers for `user_id` manual checks and replace with proper policy/enforcement |
| 2 | **Milestone Recalculation** | `MilestoneRecalculationService` exists but no scheduled job runs — milestones never auto-update | ⏳ IN PROGRESS | - | Add hourly schedule entry in `App/Providers/ScheduleServiceProvider.php`. Need to ensure job is queued properly |
| 3 | **Queue Workers** | Heavy ops (PDF export, AI calls, Excel parsing, backup) run in request thread | 🔍 TODO | - | Set up Redis backend + Horizon. Identify slow methods and convert to queued jobs |

---

## 🧪 Quality & Testing

| # | Area | Issue | Status | PR/Commit | Notes |
|---|------|-------|--------|-----------|-------|
| 4 | **Core CRUD Tests** | No tests for DailyProgress, Task, Learning, Milestone, Bill, Money, Chat, Inbox CRUD | ⏳ IN PROGRESS | - | Start with feature test suite using DatabaseSeeder helpers |
| 5 | **Unit Tests for Services** | Only `GoogleSheetsBackupTest` missing tests for `MilestoneRecalculationService`, `AiQuotaService`, `NotificationService`, `ReportBuilder` | 🔍 TODO | - | Write PHPUnit unit tests with mocked external deps |
| 6 | **Auth/Authorization Tests** | No tests for login flow, policy enforcement, role-based access control | 🔍 TODO | - | Test scenario: user A cannot view user B's bills, admin can |

---

## 💅 Frontend UX & Polish

| # | Area | Issue | Status | PR/Commit | Notes |
|---|------|-------|--------|-----------|-------|
| 7 | **Loading States** | Quick Capture submit has no spinner during async call; auth errors silently logged | ⏳ IN PROGRESS | `feature/quick-capture-loading-spinner` (branch in progress) | Added loading spinner to Quick Capture modal during async submission, disabled button while loading |
| 8 | **Centralized API Interceptor** | Try/catch scattered across stores/views without unified handling | 🔍 TODO | - Create `/src/services/api.ts` wrapper that handles 401 redirect, 422 field errors, 5xx generic toast |
| 9 | **Keyboard Shortcuts** | No help overlay listing shortcuts; no Esc support for closing modals | 🔍 TODO | - Add Cmd+K → help overlay; add `@keydown.escape` handler with focus trap |

---

## 🏗️ Architecture & Maintainability

| # | Area | Issue | Status | PR/Commit | Notes |
|---|------|-------|--------|-----------|-------|
| 10 | **Route Files Monolith** | `routes/api/v1.php` is 500+ lines mixed middleware groups | 🔍 TODO | - Split into feature files: `tasks.php`, `daily-progress.php`, `bills.php`, etc. |
| 11 | **GameController Bloat** | 933 lines mixing Sudoku, Minesweeper, 2048, Memory Match, Melody, Pitch | 🔍 TODO | - Refactor into separate controllers or sub-classes per game type |
| 12 | **Auth Store Type Safety** | `auth.ts` uses `as any` everywhere for user shape | 🔍 TODO | - Define TypeScript interfaces, replace `any` throughout |

---

## ⚙️ Infrastructure

| # | Area | Issue | Status | PR/Commit | Notes |
|---|------|-------|--------|-----------|-------|
| 13 | **Scheduler Missing Cron Jobs** | AI quota reset, milestone recalculation, stale game session cleanup, backup exports likely not running | 🔍 TODO | - Verify every time-sensitive job has an entry in ScheduleServiceProvider |
| 14 | **Error Tracking** | No Sentry/Laravel Forge configured in production | 🔍 TODO | - Add `Sentry\Laravel\InitiationServiceProvider`, configure `.env` |
| 15 | **Health Check Endpoint** | No `/health` endpoint returning DB status, disk space, queue workers | 🔍 TODO | - Simple middleware route checking DB connection, writable storage, queue |

---

## ✅ Items Completed (merged PRs)

| PR # | Title | Merged To | Date | Commit Hash | Description |
|------|-------|-----------|------|-------------|-------------|
| #89 | `test(e2e): stabilize Playwright suite + fix FAB over form action bars` | main | 2026-07-29 | `67e3638` | Stabilized Playwright e2e suite, fixed ChatBubble FAB on form routes, added work_log quick-add, updated Cancel button label, tightened delete confirmation test, aligned mobile nav assertion |

---

## 📌 Roadmap (Next Milestone)

| Phase | Goal | Timeline |
|-------|------|----------|
| **Phase 1 (Week 1-2)** | Fix authorization policies (#1), add milestone scheduler (#2), start unit tests (#5) | Immediate |
| **Phase 2 (Week 3-4)** | Extract API interceptor (#8), load spinners (#7), keyboard shortcuts (#9) | Next sprint |
| **Phase 3 (Month 2)** | Split route files (#10), GameController refactor (#11), auth store types (#12) | Q3 |
| **Phase 4 (Month 3+)** | Queue worker setup (#3), health check (#15), error tracking (#14) | Later |

---

### How to update this tracker

When an improvement is completed:

1. Find the row for that issue
2. Change **Status** from `⏳ IN PROGRESS` / `🔍 TODO` to `✅ DONE` (green check)
3. Fill in **PR/Commit** column with the PR number and commit hash if available
4. Add a brief description of what was done in the **Notes** column
5. If the item spans multiple commits/PRs, list them as bullet points in Notes

This file lives at `IMPROVEMENTS.md` in the project root.
