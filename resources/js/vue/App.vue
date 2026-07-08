<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router';
import { api, unwrap } from './api';
import ChatBubble from './components/ChatBubble.vue';
import DailyQuote from './components/DailyQuote.vue';
import DatePicker from './components/DatePicker.vue';
import WorkTimer from './components/WorkTimer.vue';
import QuotaExceededBanner from './components/QuotaExceededBanner.vue';
import AiQuotaStatus from './components/AiQuotaStatus.vue';
import { dismissToast, feedback, resolveConfirm, toast } from './feedback';
import { pasteLinkOverSelection } from './linkPaste';
import { useAuthStore } from './stores/auth';
import { useConfigurationStore } from './stores/configuration';
import { useInboxStore } from './stores/inbox';
import { usePrivacyStore } from './stores/privacy';
import { useClickOutside } from './composables/useClickOutside';

const auth = useAuthStore();
const configuration = useConfigurationStore();
const inboxStore = useInboxStore();
const privacy = usePrivacyStore();
const router = useRouter();
const route = useRoute();
const quick = ref(false);
const mobileMenu = ref(false);
const overdueCount = ref(0);
const projectNames = ref<string[]>([]);
const shortcutsOpen = ref(false);
const notifOpen = ref(false);
const notifUnread = ref(0);
const notifications = ref<any[]>([]);
const profileMenu = ref(false);
const notifPopoverRef = ref<HTMLDivElement | null>(null);
const profilePopoverRef = ref<HTMLDivElement | null>(null);
useClickOutside(notifPopoverRef, () => { if (notifOpen.value) notifOpen.value = false; });
useClickOutside(profilePopoverRef, () => { if (profileMenu.value) profileMenu.value = false; });
const commandOpen = ref(false);
const commandQuery = ref('');
const searchInput = ref<HTMLInputElement | null>(null);
const query = ref('');
const quickForm = ref({ type: 'task', title: '', project_name: '', duration_minutes: 30, notes: '', date: new Date().toISOString().slice(0, 10) });
const themeReady = ref(false);
const prefersDarkMedia = window.matchMedia('(prefers-color-scheme: dark)');
const storageKey = 'progressos-theme';
const habitList = ref<{ id: number; name: string; icon: string }[]>([]);
const selectedHabitId = ref<number | null>(null);
const isGuest = computed(() => !auth.user);
const isAdmin = computed(() => auth.isAdmin);
const navGroups = [
  {
    label: 'Overview',
    items: [
      { label: 'Dashboard', href: '/dashboard', icon: 'dashboard', hint: 'Ringkasan semua aktivitas hari ini — tasks, habit, progress, deadline, dan standup' },
      { label: 'Activity', href: '/activity', icon: 'chart', hint: 'Timeline dan heatmap aktivitas harian — lihat berapa banyak yang sudah dikerjakan tiap hari' },
    ],
  },
  {
    label: 'Work',
    items: [
      { label: 'Daily Progress', href: '/daily-progress', icon: 'calendar', hint: 'Jurnal harian — catat apa yang sudah dikerjakan, sedang berjalan, dan hambatan hari ini' },
      { label: 'Tasks', href: '/tasks', icon: 'check', hint: 'Manajemen task dengan status, prioritas, deadline, dan recurrence — ada view Kanban board juga' },
      { label: 'Projects', href: '/projects', icon: 'folder', hint: 'Kelola proyek aktif dan lihat breakdown tasks serta progres per proyek' },
    ],
  },
  {
    label: 'Journal & AI',
    items: [
      { label: 'Journal', href: '/journal', icon: 'docs', hint: 'Tulis jurnal harian — AI analisa mood, tema, insight, dan saran dari tulisanmu' },
    ],
  },
  {
    label: 'Learning & Goals',
    items: [
      { label: 'Learning', href: '/learning', icon: 'book', hint: 'Catat sesi belajar — topik, sumber, durasi, takeaway, dan next action' },
      { label: 'Goals & OKR', href: '/goals', icon: 'target', hint: 'Set goal jangka panjang dengan key results yang terukur, plus milestones sebagai checkpoint' },
      { label: 'Habits', href: '/habits', icon: 'check', hint: 'Track kebiasaan harian — streak, heatmap 90 hari, dan catatan per hari saat tandai selesai' },
    ],
  },
  {
    label: 'Notes',
    items: [
      { label: 'Docs', href: '/docs', icon: 'docs', hint: 'Catatan, dokumentasi, dan knowledge base — bisa di-share via link publik' },
      { label: 'Lists', href: '/lists', icon: 'check', hint: 'Checklist dan to-do list sederhana — bagus untuk belanja, packing, atau prosedur berulang' },
    ],
  },
  {
    label: 'Finance',
    items: [
      { label: 'Tagihan', href: '/bills', icon: 'billing', hint: 'Reminder tagihan dan langganan bulanan agar tidak ada yang terlewat bayar' },
      { label: 'Transaksi', href: '/money', icon: 'money', hint: 'Catat pemasukan dan pengeluaran harian — lihat ringkasan bulanan per kategori' },
    ],
  },
  {
    label: 'Review',
    items: [
      { label: 'Reports', href: '/reports/weekly', icon: 'chart', hint: 'Review mingguan dan bulanan — ringkasan work, learning, habits, dan produktivitas keseluruhan' },
    ],
  },
  {
    label: 'Games',
    items: [
      { label: 'Games', href: '/games', icon: 'games', hint: 'Mini-games untuk istirahat otak — Sudoku, Minesweeper, 2048, Memory Match, Pitch Trainer' },
    ],
  },
];
function loadCollapsed(): Record<string, boolean> {
  try {
    const saved = localStorage.getItem('nav-collapsed');
    return saved ? JSON.parse(saved) : {};
  } catch {
    return {};
  }
}
const collapsedGroups = ref<Record<string, boolean>>(loadCollapsed());
function toggleNavGroup(label: string) {
  collapsedGroups.value[label] = !collapsedGroups.value[label];
  localStorage.setItem('nav-collapsed', JSON.stringify(collapsedGroups.value));
}

const icons: Record<string, string[]> = {
  dashboard: ['M4 13h6V4H4v9Zm10 7h6V4h-6v16ZM4 20h6v-5H4v5Zm10 0h6v-5h-6v5Z'],
  search: ['m21 21-4.35-4.35M10.8 18a7.2 7.2 0 1 1 0-14.4 7.2 7.2 0 0 1 0 14.4Z'],
  folder: ['M3 7.5A2.5 2.5 0 0 1 5.5 5H10l2 2h6.5A2.5 2.5 0 0 1 21 9.5v7A2.5 2.5 0 0 1 18.5 19h-13A2.5 2.5 0 0 1 3 16.5v-9Z'],
  calendar: ['M7 3v3M17 3v3M4 9h16M6 5h12a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z'],
  briefcase: ['M9 7V5.8A1.8 1.8 0 0 1 10.8 4h2.4A1.8 1.8 0 0 1 15 5.8V7M4 9.5A2.5 2.5 0 0 1 6.5 7h11A2.5 2.5 0 0 1 20 9.5v7A2.5 2.5 0 0 1 17.5 19h-11A2.5 2.5 0 0 1 4 16.5v-7Zm0 3.5h16'],
  check: ['M20 6 9 17l-5-5'],
  book: ['M5 4.5A2.5 2.5 0 0 1 7.5 2H20v17H7.5A2.5 2.5 0 0 0 5 21.5v-17Zm0 0v17M9 6h7M9 10h7'],
  target: ['M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm0-4.5a4.5 4.5 0 1 0 0-9 4.5 4.5 0 0 0 0 9Zm0-3a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z'],
  chart: ['M4 19V5M4 19h16M8 16v-5M12 16V8M16 16v-8'],
  plus: ['M12 5v14M5 12h14'],
  menu: ['M4 7h16M4 12h16M4 17h16'],
  close: ['M6 6l12 12M18 6 6 18'],
  user: ['M20 21a8 8 0 0 0-16 0M12 13a5 5 0 1 0 0-10 5 5 0 0 0 0 10Z'],
  logout: ['M10 17l5-5-5-5M15 12H3M21 4v16'],
  spark: ['M12 3l1.7 5.1L19 10l-5.3 1.9L12 17l-1.7-5.1L5 10l5.3-1.9L12 3Z'],
  alert: ['M12 3 2 21h20L12 3ZM12 9v4M12 17h.01'],
  settings: ['M12 15.5A3.5 3.5 0 1 0 12 8a3.5 3.5 0 0 0 0 7.5ZM19.4 15a1.7 1.7 0 0 0 .34 1.87l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.7 1.7 0 0 0-1.87-.34 1.7 1.7 0 0 0-1.04 1.56V21a2 2 0 0 1-4 0v-.08A1.7 1.7 0 0 0 8.96 19.36a1.7 1.7 0 0 0-1.87.34l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-1.56-1.04H3a2 2 0 0 1 0-4h.04A1.7 1.7 0 0 0 4.6 8.96a1.7 1.7 0 0 0-.34-1.87L4.2 7.03A2 2 0 0 1 7.03 4.2l.06.06a1.7 1.7 0 0 0 1.87.34H9a1.7 1.7 0 0 0 1-1.56V3a2 2 0 0 1 4 0v.04a1.7 1.7 0 0 0 1.04 1.56 1.7 1.7 0 0 0 1.87-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.7 1.7 0 0 0-.34 1.87V9a1.7 1.7 0 0 0 1.56 1H21a2 2 0 0 1 0 4h-.04A1.7 1.7 0 0 0 19.4 15Z'],
  games: ['M5 3h4v4H5V3zm5.5 0h4v4h-4V3zM16 3h4v4h-4V3zM5 8.5h4v4H5v-4zm5.5 0h4v4h-4v-4zm5.5 0h4v4h-4v-4zM5 14h4v4H5v-4zm5.5 0h4v4h-4v-4zm5.5 0h4v4h-4v-4z'],
  chevron: ['m6 9 6 6 6-6'],
  docs: ['M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z', 'M14 2v6h6M16 13H8M16 17H8M10 9H8'],
  billing: ['M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2M9 12h6M9 16h4'],
  money: ['M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2', 'M12 6v12M9 9a3 3 0 0 1 6 0c0 1.5-1.5 2-3 3-1.5 1-3 1.5-3 3a3 3 0 0 0 6 0'],
};
const initials = computed(() => (auth.user?.name || 'U').split(' ').map((part) => part[0]).join('').slice(0, 2).toUpperCase());
// Local override so the icon and classList reflect the user's click
// immediately, even before the server round-trip lands. This is the
// single source of truth for the toggle UI.
const themeOverride = ref<string | null>(null);
const activeTheme = computed(() => {
  if (themeOverride.value) return themeOverride.value;
  if (!themeReady.value) return readStoredTheme() || 'system';
  return readStoredTheme()
    || configuration.appearance.theme
    || auth.user?.theme
    || 'system';
});

function readStoredTheme(): string | null {
  try {
    const value = localStorage.getItem(storageKey);
    if (value === 'light' || value === 'dark' || value === 'system') return value;
  } catch {
    // localStorage may be unavailable in private mode.
  }
  return null;
}

function writeStoredTheme(value: string) {
  try { localStorage.setItem(storageKey, value); } catch {
    // localStorage may be unavailable in private mode; safe to ignore.
  }
}
const commandItems = computed(() => [
  ...navGroups.flatMap((group) => group.items.map((item) => ({ ...item, group: group.label, action: 'navigate' }))),
  { label: 'New Daily Progress', href: '/daily-progress/create', icon: 'calendar', group: 'Create', action: 'navigate' },
  { label: 'New Task', href: '/tasks/create', icon: 'check', group: 'Create', action: 'navigate' },
  { label: 'New Learning Entry', href: '/learning/create', icon: 'book', group: 'Create', action: 'navigate' },
  { label: 'New Milestone', href: '/milestones/create', icon: 'target', group: 'Create', action: 'navigate' },
  { label: 'Open Quick Add', href: '#quick-add', icon: 'plus', group: 'Action', action: 'quick' },
  { label: 'Weekly Review', href: '/weekly-review', icon: 'chart', group: 'Review', action: 'navigate' },
  { label: 'Profile Settings', href: '/profile', icon: 'user', group: 'Account', action: 'navigate' },
]);
const filteredCommands = computed(() => {
  const term = commandQuery.value.trim().toLowerCase();
  return commandItems.value.filter((item) => !term || `${item.label} ${item.group}`.toLowerCase().includes(term)).slice(0, 9);
});

async function submitQuick() {
  if (quickForm.value.type === 'habit_log') {
    if (!selectedHabitId.value) return;
    try {
      await api.post(`/api/v1/habits/${selectedHabitId.value}/log`, { date: quickForm.value.date });
      const name = habitList.value.find((h) => h.id === selectedHabitId.value)?.name ?? '';
      quick.value = false;
      selectedHabitId.value = null;
      quickForm.value = { type: 'task', title: '', project_name: '', duration_minutes: 30, notes: '', date: new Date().toISOString().slice(0, 10) };
      toast({ tone: 'success', title: 'Habit logged', message: name });
    } catch {
      toast({ tone: 'error', title: 'Error', message: 'Could not log habit. Please try again.' });
    }
    return;
  }
  try {
    const res = await api.post('/api/v1/quick-capture', quickForm.value).then(unwrap);
    const path: string | null = res.record_path ?? null;
    quick.value = false;
    toast({
      tone: 'success',
      title: 'Captured',
      message: path ? `Saved. <a href="${path}" class="font-bold underline">View record →</a>` : 'Saved.',
    });
    quickForm.value = { type: 'task', title: '', project_name: '', duration_minutes: 30, notes: '', date: new Date().toISOString().slice(0, 10) };
    if (path) await router.push(path);
    else await router.push('/dashboard');
  } catch (e: any) {
    toast({ tone: 'error', title: 'Gagal menyimpan', message: e?.response?.data?.message ?? 'Terjadi kesalahan.' });
  }
}

async function loadHabits() {
  try {
    const res = await api.get('/api/v1/habits').then(unwrap);
    habitList.value = (res.habits || []).filter((h: any) => h.active).map((h: any) => ({ id: h.id, name: h.name, icon: h.icon }));
  } catch {
    // non-critical
  }
}

async function loadProjectNames() {
  if (projectNames.value.length) return;
  try {
    const res = await api.get('/api/v1/projects?per_page=200&sort=name&direction=asc').then(unwrap);
    projectNames.value = (res.data ?? []).map((p: any) => p.name).filter(Boolean);
  } catch {
    // non-critical
  }
}

async function loadOverdueCount() {
  try {
    const res = await api.get('/api/v1/tasks/overdue-count').then(unwrap);
    overdueCount.value = res.count ?? 0;
  } catch {
    overdueCount.value = 0;
  }
}

async function loadNotifications() {
  try {
    const res = await api.get('/api/v1/notifications').then(unwrap);
    notifications.value = res.notifications ?? [];
    notifUnread.value = res.unread_count ?? 0;
  } catch {
    // non-critical
  }
}

async function toggleNotif() {
  notifOpen.value = !notifOpen.value;
  if (notifOpen.value) await loadNotifications();
}

async function markNotifRead(n: any) {
  if (n.read_at) return;
  try {
    await api.patch(`/api/v1/notifications/${n.id}/read`);
    n.read_at = new Date().toISOString();
    notifUnread.value = Math.max(0, notifUnread.value - 1);
    if (n.action_url) { notifOpen.value = false; router.push(n.action_url); }
  } catch {
    // non-critical, notification badge may be slightly off
  }
}

async function markAllNotifRead() {
  try {
    await api.post('/api/v1/notifications/mark-all-read');
    notifications.value.forEach((n) => { n.read_at = new Date().toISOString(); });
    notifUnread.value = 0;
  } catch {
    // non-critical
  }
}

async function clearAllNotif() {
  try {
    await api.delete('/api/v1/notifications');
    notifications.value = [];
    notifUnread.value = 0;
  } catch {
    // non-critical
  }
}

async function logout() {
  await auth.logout();
  profileMenu.value = false;
  mobileMenu.value = false;
  await router.push('/login');
}

function search() {
  if (query.value.trim()) router.push(`/search?q=${encodeURIComponent(query.value.trim())}`);
}

function onTimerLog(payload: { title: string; minutes: number }) {
  quickForm.value.type = 'daily_progress';
  quickForm.value.title = payload.title;
  quickForm.value.date = new Date().toISOString().slice(0, 10);
  quick.value = true;
}

async function runCommand(item: any) {
  commandOpen.value = false;
  commandQuery.value = '';
  if (item.action === 'quick') {
    quick.value = true;
    return;
  }
  await router.push(item.href);
}

function handleQuickNotesPaste(event: ClipboardEvent) {
  pasteLinkOverSelection(event, quickForm, 'notes');
}

function applyTheme(theme?: string) {
  const prefersDark = prefersDarkMedia.matches;
  const isDark = theme === 'dark' || (theme === 'system' && prefersDark);
  document.documentElement.classList.toggle('dark', isDark);
}

async function cycleTheme() {
  const next: Record<string, string> = { system: 'light', light: 'dark', dark: 'system' };
  const newTheme = next[activeTheme.value] ?? 'system';
  // Update local override first so the icon swaps instantly.
  themeOverride.value = newTheme;
  writeStoredTheme(newTheme);
  applyTheme(newTheme);
  configuration.applyGroups({ appearance: { ...configuration.appearance, theme: newTheme } });
  // Persist to the user profile so the value survives across browsers and devices.
  // Admins use the global appearance.theme; non-admins use their personal user theme.
  if (isAdmin.value) {
    api.put('/api/admin/configuration/settings', { appearance: { theme: newTheme } }).catch(() => {});
  } else if (auth.user) {
    auth.updateProfile({ name: auth.user.name, email: auth.user.email, timezone: auth.user.timezone || 'UTC', theme: newTheme }).catch(() => {});
  }
}

function applyFavicon(url?: string) {
  if (!url) return;
  let link = document.querySelector<HTMLLinkElement>('link[rel="icon"]');
  if (!link) {
    link = document.createElement('link');
    link.rel = 'icon';
    document.head.appendChild(link);
  }
  link.href = url;
}

const confirmCancelRef = ref<HTMLButtonElement | null>(null);
watch(() => feedback.confirm.open, async (open) => {
  if (open) {
    await nextTick();
    confirmCancelRef.value?.focus();
  }
});

function shortcuts(event: KeyboardEvent) {
  const target = event.target as HTMLElement | null;
  const typing = ['INPUT', 'TEXTAREA', 'SELECT'].includes(target?.tagName || '');
  if (event.key === 'Escape') {
    if (feedback.confirm.open) { resolveConfirm(false); return; }
    quick.value = false;
    mobileMenu.value = false;
    profileMenu.value = false;
    commandOpen.value = false;
    shortcutsOpen.value = false;
    notifOpen.value = false;
  }
  if (typing) return;
  if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
    event.preventDefault();
    commandOpen.value = true;
    return;
  }
  if (event.key === '/') {
    event.preventDefault();
    searchInput.value?.focus();
  }
  if (event.key.toLowerCase() === 'n') {
    event.preventDefault();
    quick.value = true;
  }
  if (event.key === '?') {
    event.preventDefault();
    shortcutsOpen.value = true;
  }
}

function isActive(href: string) {
  if (route.path === href) return true;
  if (href === '/dashboard') return false;
  if (!route.path.startsWith(href + '/')) return false;
  // Don't highlight a shorter prefix if a more specific nav item matches
  const allHrefs = navGroups.flatMap((g) => g.items.map((i) => i.href));
  return !allHrefs.some((h) => h !== href && h.length > href.length && route.path.startsWith(h));
}

watch(() => auth.user, async (user) => {
  if (!user) return;
  privacy.init(String(user.id));
  if (isAdmin.value) return;
  try {
    await configuration.load();
  } catch {
    // Configuration is non-critical for shell boot.
  }
  // If the user already picked a theme in this browser, mirror it into
  // the configuration so other consumers (e.g. the Profile screen) see
  // the same value. Otherwise, persist whatever the server returned so
  // subsequent refreshes stay in sync.
  const stored = readStoredTheme();
  if (stored) {
    configuration.applyGroups({ appearance: { ...configuration.appearance, theme: stored } });
  } else {
    const serverTheme = configuration.appearance.theme || auth.user?.theme;
    if (serverTheme) writeStoredTheme(serverTheme);
  }
  themeReady.value = true;
  // Drop any stale override so the computed reflects the resolved value.
  themeOverride.value = null;
  loadOverdueCount();
  loadNotifications();
  loadProjectNames();
}, { immediate: true });
watch(() => route.fullPath, () => { if (auth.user && !isAdmin.value) { loadOverdueCount(); loadNotifications(); } });
watch(activeTheme, (theme) => {
  applyTheme(theme || 'system');
  // Persist changes that did not originate from a click (e.g. when
  // configuration loads from the server and resolves a stored value).
  if (themeOverride.value !== theme) {
    writeStoredTheme(theme || 'system');
  }
});

onMounted(() => {
  // Apply whatever we already know at mount time so the page does not flash
  // the wrong mode. The activeTheme computed already prefers the localStorage
  // value when themeReady is false, so this picks up the user's previous pick.
  applyTheme(activeTheme.value);

  // React to system preference changes so 'system' mode stays in sync.
  prefersDarkMedia.addEventListener('change', () => {
    if (activeTheme.value === 'system') applyTheme('system');
  });
});
watch(() => configuration.appearance.favicon_url, (url) => applyFavicon(url), { immediate: true });
watch(() => configuration.appName, (name) => {
  document.title = name || 'ProgressOS';
}, { immediate: true });
watch(() => route.fullPath, () => {
  mobileMenu.value = false;
  profileMenu.value = false;
  commandOpen.value = false;
});
let notifInterval: ReturnType<typeof setInterval> | null = null;
let bumpTimeout: ReturnType<typeof setTimeout> | null = null;
function onActivity() {
  if (bumpTimeout) return;
  bumpTimeout = setTimeout(() => {
    privacy.bump();
    bumpTimeout = null;
  }, 10_000);
}

onMounted(() => {
  window.addEventListener('keydown', shortcuts);
  window.addEventListener('mousemove', onActivity, { passive: true });
  window.addEventListener('keydown', onActivity, { passive: true });
  window.addEventListener('click', onActivity, { passive: true });
  notifInterval = setInterval(() => { if (auth.user) loadNotifications(); }, 60_000);
});
onUnmounted(() => {
  window.removeEventListener('keydown', shortcuts);
  window.removeEventListener('mousemove', onActivity);
  window.removeEventListener('keydown', onActivity);
  window.removeEventListener('click', onActivity);
  if (notifInterval) clearInterval(notifInterval);
});
</script>

<template>
  <RouterView v-if="isGuest || route.meta.public" />
  <div v-else class="min-h-screen text-slate-950 dark:text-zinc-100 lg:flex">
    <!-- Desktop sidebar -->
    <aside class="hidden flex-col border-r border-slate-200 bg-white/95 shadow-[12px_0_40px_rgb(15_23_42/0.035)] backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/95 dark:shadow-[12px_0_40px_rgb(0_0_0/0.3)] lg:fixed lg:inset-y-0 lg:flex lg:w-72">
      <RouterLink :to="isAdmin ? '/admin/users' : '/dashboard'" class="shrink-0 px-4 pb-4 pt-5 flex items-center gap-3">
        <span class="grid h-10 w-10 place-items-center rounded-2xl bg-teal-700 text-white shadow-lg shadow-teal-900/10">
          <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" viewBox="0 0 24 24"><path v-for="path in icons.spark" :key="path" :d="path" /></svg>
        </span>
        <span>
          <span class="block text-base font-extrabold tracking-tight">{{ configuration.projectName }}</span>
          <span class="text-xs font-semibold text-slate-500 dark:text-zinc-500">{{ configuration.tagline }}</span>
        </span>
      </RouterLink>
      <nav class="flex-1 space-y-1 overflow-y-auto px-4 pb-2">
        <template v-if="isAdmin">
          <RouterLink
            to="/admin/users"
            class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-bold text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900 dark:text-zinc-400 dark:hover:bg-zinc-800/60 dark:hover:text-zinc-100"
            :class="isActive('/admin/users') ? 'bg-teal-50 text-teal-800 ring-1 ring-teal-200/60 dark:bg-teal-500/12 dark:text-teal-300 dark:ring-teal-500/20' : ''"
          >
            <span class="grid h-8 w-8 place-items-center rounded-lg" :class="isActive('/admin/users') ? 'bg-teal-100 text-teal-700 dark:bg-teal-500/25 dark:text-teal-300' : 'bg-slate-100 text-slate-500 dark:bg-zinc-800 dark:text-zinc-500'">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </span>
            <span class="flex-1">User Management</span>
          </RouterLink>
          <RouterLink
            to="/configuration"
            class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-bold text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900 dark:text-zinc-400 dark:hover:bg-zinc-800/60 dark:hover:text-zinc-100"
            :class="isActive('/configuration') ? 'bg-teal-50 text-teal-800 ring-1 ring-teal-200/60 dark:bg-teal-500/12 dark:text-teal-300 dark:ring-teal-500/20' : ''"
          >
            <span class="grid h-8 w-8 place-items-center rounded-lg" :class="isActive('/configuration') ? 'bg-teal-100 text-teal-700 dark:bg-teal-500/25 dark:text-teal-300' : 'bg-slate-100 text-slate-500 dark:bg-zinc-800 dark:text-zinc-500'">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" viewBox="0 0 24 24"><path v-for="path in icons.settings" :key="path" :d="path" /></svg>
            </span>
            <span class="flex-1">Configuration</span>
          </RouterLink>
        </template>
        <section v-else v-for="group in navGroups" :key="group.label" class="mb-1">
          <button
            class="mb-0.5 flex w-full items-center justify-between rounded-xl px-3 py-2.5 text-[11px] font-extrabold uppercase text-slate-400 transition hover:bg-slate-50 hover:text-slate-600 dark:hover:bg-zinc-800/40 dark:text-zinc-600 dark:hover:text-zinc-400"
            @click="toggleNavGroup(group.label)"
          >
            {{ group.label }}
            <svg class="h-3 w-3 transition-transform duration-150" :class="collapsedGroups[group.label] ? '-rotate-90' : ''" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" viewBox="0 0 24 24"><path v-for="path in icons.chevron" :key="path" :d="path" /></svg>
          </button>
          <div v-show="!collapsedGroups[group.label]" class="space-y-1">
            <RouterLink
              v-for="item in group.items"
              :key="item.href"
              :to="item.href"
              :title="item.hint"
              class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-bold text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900 dark:text-zinc-400 dark:hover:bg-zinc-800/60 dark:hover:text-zinc-100"
              :class="isActive(item.href) ? 'bg-teal-50 text-teal-800 ring-1 ring-teal-200/60 dark:bg-teal-500/12 dark:text-teal-300 dark:ring-teal-500/20' : ''"
            >
              <span
                class="grid h-8 w-8 place-items-center rounded-lg transition-colors"
                :class="isActive(item.href) ? 'bg-teal-100 text-teal-700 dark:bg-teal-500/25 dark:text-teal-300' : 'bg-slate-100 text-slate-500 group-hover:bg-white group-hover:text-teal-700 dark:bg-zinc-800 dark:text-zinc-500 dark:group-hover:bg-zinc-700 dark:group-hover:text-teal-400'"
              >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" viewBox="0 0 24 24"><path v-for="path in icons[item.icon]" :key="path" :d="path" /></svg>
              </span>
              <span class="flex-1">{{ item.label }}</span>
              <span v-if="item.href === '/tasks' && overdueCount > 0" class="ml-auto rounded-full bg-red-500 px-1.5 py-0.5 text-[10px] font-extrabold text-white">{{ overdueCount > 9 ? '9+' : overdueCount }}</span>
            </RouterLink>
          </div>
        </section>
      </nav>
      <DailyQuote />
      <div class="shrink-0 border-t border-slate-100 px-4 py-3 dark:border-zinc-800/80">
        <p class="text-[10px] font-semibold uppercase tracking-widest text-slate-300 dark:text-zinc-700">v{{ configuration.appVersion }}</p>
      </div>
    </aside>

    <main class="min-w-0 flex-1 lg:pl-72">
      <!-- Topbar -->
      <header class="sticky top-0 z-30 border-b border-slate-200 bg-[#f7f8fa]/90 px-3 py-3 backdrop-blur-xl dark:border-zinc-800 dark:bg-zinc-950/90 sm:px-5">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-2 sm:flex-nowrap">
          <button class="btn btn-muted px-3 lg:hidden" aria-label="Open menu" @click="mobileMenu = true">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" viewBox="0 0 24 24"><path v-for="path in icons.menu" :key="path" :d="path" /></svg>
            <span class="hidden sm:inline">Menu</span>
          </button>
          <RouterLink to="/dashboard" class="mr-auto flex items-center gap-2 text-base font-extrabold lg:hidden">
            <span class="grid h-9 w-9 place-items-center rounded-xl bg-teal-700 text-white">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" viewBox="0 0 24 24"><path v-for="path in icons.spark" :key="path" :d="path" /></svg>
            </span>
            <span class="hidden sm:inline">{{ configuration.projectName }}</span>
          </RouterLink>
          <!-- Search -->
          <form class="relative order-last mt-2 w-full min-w-0 sm:order-none sm:mt-0 sm:block sm:flex-1" @submit.prevent="search">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400 dark:text-zinc-500" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" viewBox="0 0 24 24"><path v-for="path in icons.search" :key="path" :d="path" /></svg>
            <input ref="searchInput" v-model="query" class="field h-11 pl-9" placeholder="Search everything" aria-label="Search everything" />
            <button type="button" class="absolute right-2 top-1/2 hidden -translate-y-1/2 rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs font-bold text-slate-400 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-500 xl:block" @click="commandOpen = true">Cmd K</button>
          </form>
          <!-- Keyboard shortcut hints -->
          <div class="hidden items-center gap-1.5 2xl:flex">
            <kbd class="rounded border border-slate-200 bg-white px-1.5 py-0.5 text-[10px] font-bold text-slate-400 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-500" title="Quick add">N</kbd>
            <kbd class="rounded border border-slate-200 bg-white px-1.5 py-0.5 text-[10px] font-bold text-slate-400 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-500" title="Focus search">/</kbd>
            <kbd class="rounded border border-slate-200 bg-white px-1.5 py-0.5 text-[10px] font-bold text-slate-400 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-500" title="Command palette">⌘K</kbd>
          </div>
          <!-- Work timer -->
          <div v-if="!isAdmin" class="hidden xl:block">
            <WorkTimer @log="onTimerLog" />
          </div>
          <!-- Notification bell -->
          <div v-if="!isAdmin" class="relative" ref="notifPopoverRef">
            <button
              @click="toggleNotif"
              class="relative grid h-11 w-11 shrink-0 place-items-center rounded-xl border border-slate-200 bg-white text-slate-500 transition hover:border-amber-200 hover:text-amber-600 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400 dark:hover:border-amber-600 dark:hover:text-amber-400"
              aria-label="Notifications"
            >
              <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 0 1-3.46 0"/></svg>
              <span v-if="notifUnread > 0" class="absolute -right-1 -top-1 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[9px] font-extrabold text-white">{{ notifUnread > 9 ? '9+' : notifUnread }}</span>
            </button>
            <div v-if="notifOpen" class="fixed inset-x-3 top-[4.5rem] z-20 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-900/10 dark:border-zinc-800 dark:bg-zinc-900 dark:shadow-black/30 sm:absolute sm:inset-auto sm:right-0 sm:top-full sm:mt-2 sm:w-80">
              <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3 dark:border-zinc-800">
                <span class="text-sm font-extrabold text-slate-900 dark:text-zinc-100">Notifications</span>
                <div class="flex gap-2">
                  <button v-if="notifUnread > 0" @click="markAllNotifRead" class="text-xs text-teal-600 hover:underline dark:text-teal-400">Mark all read</button>
                  <button v-if="notifications.length" @click="clearAllNotif" class="text-xs text-red-500 hover:underline">Clear all</button>
                </div>
              </div>
              <div v-if="!notifications.length" class="py-8 text-center text-sm text-slate-400 dark:text-zinc-500">No notifications</div>
              <div v-else class="max-h-96 overflow-y-auto divide-y divide-slate-100 dark:divide-zinc-800">
                <button
                  v-for="n in notifications"
                  :key="n.id"
                  @click="markNotifRead(n)"
                  :class="['w-full text-left px-4 py-3 transition hover:bg-slate-50 dark:hover:bg-zinc-800/60', !n.read_at ? 'bg-amber-50 dark:bg-amber-900/10' : '']"
                >
                  <div class="flex items-start gap-2">
                    <span class="mt-0.5 text-base leading-none">{{ { task_overdue: '⚠️', task_due_soon: '📅', milestone_completed: '🎉', habit_streak: '🔥' }[n.type] || '🔔' }}</span>
                    <div class="flex-1 min-w-0">
                      <p class="text-xs font-bold text-slate-800 dark:text-zinc-200">{{ n.title }}</p>
                      <p v-if="n.body" class="text-xs text-slate-500 dark:text-zinc-400 mt-0.5 truncate">{{ n.body }}</p>
                    </div>
                    <span v-if="!n.read_at" class="mt-1 h-2 w-2 rounded-full bg-amber-500 flex-shrink-0"></span>
                  </div>
                </button>
              </div>
            </div>
          </div>
          <!-- Privacy hide toggle -->
          <button
            class="grid h-11 w-11 shrink-0 place-items-center rounded-xl border transition"
            :class="privacy.hideSensitive ? 'border-amber-300 bg-amber-50 text-amber-600 dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-400' : 'border-slate-200 bg-white text-slate-500 hover:border-teal-200 hover:text-teal-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400 dark:hover:border-teal-700 dark:hover:text-teal-400'"
            :title="privacy.hideSensitive ? 'Data keuangan disembunyikan — klik untuk tampilkan' : 'Sembunyikan data sensitif'"
            aria-label="Toggle privacy mode"
            @click="privacy.toggleHide()"
          >
            <!-- Eye open -->
            <svg v-if="!privacy.hideSensitive" class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" viewBox="0 0 24 24"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
            <!-- Eye off (hidden) -->
            <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" viewBox="0 0 24 24"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24M1 1l22 22"/></svg>
          </button>
          <!-- Theme toggle -->
          <button
            class="grid h-11 w-11 shrink-0 place-items-center rounded-xl border border-slate-200 bg-white text-slate-500 transition hover:border-teal-200 hover:text-teal-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400 dark:hover:border-teal-700 dark:hover:text-teal-400"
            :title="activeTheme === 'light' ? 'Light mode' : activeTheme === 'dark' ? 'Dark mode' : 'System theme'"
            aria-label="Toggle theme"
            @click="cycleTheme"
          >
            <!-- Sun: light -->
            <svg v-if="activeTheme === 'light'" class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>
            <!-- Moon: dark -->
            <svg v-else-if="activeTheme === 'dark'" class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            <!-- Monitor: system -->
            <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
          </button>
          <!-- Quick add -->
          <button v-if="!isAdmin" class="btn btn-primary px-3 sm:px-4" title="Quick add" aria-label="Quick add" @click="quick = true">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path v-for="path in icons.plus" :key="path" :d="path" /></svg>
            <span class="hidden sm:inline">Quick Add</span>
          </button>
          <!-- AI Quota Status -->
          <AiQuotaStatus v-if="!isAdmin" />
          <!-- Profile -->
          <div ref="profilePopoverRef">
            <button class="flex h-11 items-center gap-2 rounded-xl border border-slate-200 bg-white px-2.5 text-sm font-bold text-slate-700 shadow-sm transition hover:border-teal-200 hover:text-teal-800 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:border-teal-700 dark:hover:text-teal-300" aria-label="Open profile menu" aria-haspopup="menu" :aria-expanded="profileMenu" @click="profileMenu = !profileMenu">
              <img v-if="auth.user?.avatar_url" :src="auth.user.avatar_url" class="h-8 w-8 rounded-lg object-cover" alt="" />
              <span v-else class="grid h-8 w-8 place-items-center rounded-lg bg-slate-900 text-xs font-extrabold text-white dark:bg-zinc-700">{{ initials }}</span>
              <span class="hidden max-w-32 truncate md:block">{{ auth.user?.name || 'Profile' }}</span>
            </button>
            <div v-if="profileMenu" class="absolute right-0 z-20 mt-2 w-64 overflow-hidden rounded-2xl border border-slate-200 bg-white p-2 shadow-2xl shadow-slate-900/10 dark:border-zinc-800 dark:bg-zinc-900 dark:shadow-black/30" role="menu">
              <div class="border-b border-slate-100 px-3 py-3 dark:border-zinc-800">
                <div class="flex items-center gap-3">
                  <img v-if="auth.user?.avatar_url" :src="auth.user.avatar_url" class="h-10 w-10 rounded-xl object-cover" alt="" />
                  <span v-else class="grid h-10 w-10 place-items-center rounded-xl bg-slate-900 text-xs font-extrabold text-white dark:bg-zinc-700">{{ initials }}</span>
                  <div class="min-w-0">
                    <p class="truncate text-sm font-extrabold text-slate-900 dark:text-zinc-100">{{ auth.user?.name }}</p>
                    <p class="truncate text-xs font-semibold text-slate-500 dark:text-zinc-500">{{ auth.user?.email }}</p>
                  </div>
                </div>
              </div>
              <RouterLink to="/profile" class="mt-2 flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-100 dark:text-zinc-300 dark:hover:bg-zinc-800" role="menuitem">
                <svg class="h-4 w-4 text-slate-400 dark:text-zinc-600" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" viewBox="0 0 24 24"><path v-for="path in icons.user" :key="path" :d="path" /></svg>
                Profile settings
              </RouterLink>
              <RouterLink v-if="!isAdmin" to="/inbox" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-100 dark:text-zinc-300 dark:hover:bg-zinc-800" role="menuitem" @click="profileMenu = false">
                <svg class="h-4 w-4 text-slate-400 dark:text-zinc-600" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                Inbox
                <span v-if="inboxStore.unreadTotal > 0" class="ml-auto flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-[10px] font-extrabold text-white">{{ inboxStore.unreadTotal > 99 ? '99+' : inboxStore.unreadTotal }}</span>
              </RouterLink>
              <button class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-bold text-red-700 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20" role="menuitem" @click="logout">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" viewBox="0 0 24 24"><path v-for="path in icons.logout" :key="path" :d="path" /></svg>
                Logout
              </button>
            </div>
          </div>
        </div>
      </header>
      <section class="mx-auto max-w-7xl px-3 py-4 sm:px-5 sm:py-6">
        <RouterView />
      </section>
    </main>

    <!-- Mobile menu drawer -->
    <div v-if="mobileMenu" class="fixed inset-0 z-40 lg:hidden" role="dialog" aria-modal="true" aria-label="Navigation menu">
      <button class="absolute inset-0 bg-slate-950/40 backdrop-blur-[2px]" aria-label="Close menu" @click="mobileMenu = false"></button>
      <aside class="relative h-full w-[min(22rem,88vw)] overflow-y-auto border-r border-slate-200 bg-white p-4 shadow-2xl dark:border-zinc-800 dark:bg-zinc-900">
        <div class="mb-6 flex items-center justify-between">
          <RouterLink :to="isAdmin ? '/admin/users' : '/dashboard'" class="flex items-center gap-3">
            <span class="grid h-10 w-10 place-items-center rounded-2xl bg-teal-700 text-white">
              <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" viewBox="0 0 24 24"><path v-for="path in icons.spark" :key="path" :d="path" /></svg>
            </span>
            <span class="font-extrabold dark:text-zinc-100">{{ configuration.projectName }}</span>
          </RouterLink>
          <button class="btn btn-muted px-3" aria-label="Close menu" @click="mobileMenu = false">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" viewBox="0 0 24 24"><path v-for="path in icons.close" :key="path" :d="path" /></svg>
          </button>
        </div>
        <nav class="space-y-1">
          <template v-if="isAdmin">
            <RouterLink
              to="/admin/users"
              class="flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-bold text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900 dark:text-zinc-400 dark:hover:bg-zinc-800"
              :class="isActive('/admin/users') ? 'bg-teal-50 text-teal-800 ring-1 ring-teal-200/60 dark:bg-teal-500/12 dark:text-teal-300 dark:ring-teal-500/20' : ''"
              @click="mobileMenu = false"
            >
              <span class="grid h-8 w-8 place-items-center rounded-lg" :class="isActive('/admin/users') ? 'bg-teal-100 text-teal-700 dark:bg-teal-500/25 dark:text-teal-300' : 'bg-slate-100 text-slate-500 dark:bg-zinc-800 dark:text-zinc-500'">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
              </span>
              User Management
            </RouterLink>
            <RouterLink
              to="/configuration"
              class="flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-bold text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900 dark:text-zinc-400 dark:hover:bg-zinc-800"
              :class="isActive('/configuration') ? 'bg-teal-50 text-teal-800 ring-1 ring-teal-200/60 dark:bg-teal-500/12 dark:text-teal-300 dark:ring-teal-500/20' : ''"
              @click="mobileMenu = false"
            >
              <span class="grid h-8 w-8 place-items-center rounded-lg" :class="isActive('/configuration') ? 'bg-teal-100 text-teal-700 dark:bg-teal-500/25 dark:text-teal-300' : 'bg-slate-100 text-slate-500 dark:bg-zinc-800 dark:text-zinc-500'">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" viewBox="0 0 24 24"><path v-for="path in icons.settings" :key="path" :d="path" /></svg>
              </span>
              Configuration
            </RouterLink>
          </template>
          <section v-else v-for="group in navGroups" :key="group.label" class="mb-1">
            <button
              class="mb-0.5 flex w-full items-center justify-between rounded-xl px-3 py-2.5 text-[11px] font-extrabold uppercase text-slate-400 transition hover:bg-slate-50 hover:text-slate-600 dark:hover:bg-zinc-800/40 dark:text-zinc-600 dark:hover:text-zinc-400"
              @click="toggleNavGroup(group.label)"
            >
              {{ group.label }}
              <svg class="h-3 w-3 transition-transform duration-150" :class="collapsedGroups[group.label] ? '-rotate-90' : ''" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" viewBox="0 0 24 24"><path v-for="path in icons.chevron" :key="path" :d="path" /></svg>
            </button>
            <div v-show="!collapsedGroups[group.label]" class="space-y-1">
              <RouterLink
                v-for="item in group.items"
                :key="item.href"
                :to="item.href"
                :title="item.hint"
                class="flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-bold text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900 dark:text-zinc-400 dark:hover:bg-zinc-800"
                :class="isActive(item.href) ? 'bg-teal-50 text-teal-800 ring-1 ring-teal-200/60 dark:bg-teal-500/12 dark:text-teal-300 dark:ring-teal-500/20' : ''"
              >
                <span class="grid h-8 w-8 place-items-center rounded-lg" :class="isActive(item.href) ? 'bg-teal-100 text-teal-700 dark:bg-teal-500/25 dark:text-teal-300' : 'bg-slate-100 text-slate-500 dark:bg-zinc-800 dark:text-zinc-500'">
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" viewBox="0 0 24 24"><path v-for="path in icons[item.icon]" :key="path" :d="path" /></svg>
                </span>
                {{ item.label }}
              </RouterLink>
            </div>
          </section>
        </nav>
      </aside>
    </div>

    <!-- Quick add modal -->
    <Transition name="quick-modal">
      <div v-if="quick" class="fixed inset-0 z-40 flex items-end justify-center bg-slate-950/50 backdrop-blur-[2px] sm:items-center sm:p-4" role="dialog" aria-modal="true" aria-labelledby="quick-add-title" @click.self="quick = false">
        <form class="quick-panel w-full overflow-hidden rounded-t-3xl border border-slate-200 bg-white shadow-2xl dark:border-zinc-800 dark:bg-zinc-900 sm:max-w-xl sm:rounded-2xl" @submit.prevent="submitQuick">
          <div class="border-b border-slate-100 bg-slate-50/70 px-5 py-4 dark:border-zinc-800 dark:bg-zinc-900/60">
            <div class="flex items-center justify-between gap-3">
              <div>
                <p class="text-xs font-extrabold uppercase text-teal-700 dark:text-teal-500">Capture</p>
                <h2 id="quick-add-title" class="text-lg font-extrabold">Quick Add</h2>
              </div>
              <button type="button" class="btn btn-muted px-3" aria-label="Close quick add" @click="quick = false">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" viewBox="0 0 24 24"><path v-for="path in icons.close" :key="path" :d="path" /></svg>
              </button>
            </div>
          </div>
          <div class="max-h-[calc(85vh-8rem)] overflow-y-auto">
            <div class="grid gap-3 p-5 sm:grid-cols-2">
              <select v-model="quickForm.type" class="field" @change="quickForm.type === 'habit_log' && loadHabits()">
                <option value="task">Task</option>
                <option value="blocker">Blocker</option>
                <option value="daily_progress">Daily progress</option>
                <option value="learning">Learning</option>
                <option value="habit_log">Habit log</option>
              </select>
              <DatePicker v-model="quickForm.date" label="Quick add date" />
              <!-- Habit log: just pick a habit -->
              <template v-if="quickForm.type === 'habit_log'">
                <select v-model="selectedHabitId" class="field sm:col-span-2" required>
                  <option :value="null">— Select habit —</option>
                  <option v-for="h in habitList" :key="h.id" :value="h.id">{{ h.icon ? h.icon + ' ' : '' }}{{ h.name }}</option>
                </select>
              </template>
              <!-- Standard capture fields -->
              <template v-else>
                <input v-model="quickForm.title" class="field sm:col-span-2" placeholder="Title" required />
                <input v-model="quickForm.project_name" list="quick-projects" class="field" placeholder="Project" autocomplete="off" />
                <datalist id="quick-projects">
                  <option v-for="name in projectNames" :key="name" :value="name" />
                </datalist>
                <input v-model="quickForm.duration_minutes" class="field" type="number" min="1" />
                <textarea v-model="quickForm.notes" class="field min-h-20 sm:col-span-2 sm:min-h-28" placeholder="Notes" @paste="handleQuickNotesPaste" />
              </template>
            </div>
          </div>
          <div class="flex justify-end border-t border-slate-100 bg-slate-50/70 px-5 py-4 pb-[calc(1rem+env(safe-area-inset-bottom))] dark:border-zinc-800 dark:bg-zinc-900/60 sm:pb-4">
            <button class="btn btn-primary">Capture</button>
          </div>
        </form>
      </div>
    </Transition>

    <!-- Chat bubble (mobile: navigates to /inbox; desktop: floating panel) -->
    <ChatBubble v-if="auth.user && !isAdmin" />

    <!-- Command palette -->
    <div v-if="commandOpen && !isAdmin" class="fixed inset-0 z-50 grid place-items-start bg-slate-950/50 p-3 pt-20 backdrop-blur-[2px] sm:p-6 sm:pt-24" role="dialog" aria-modal="true" aria-labelledby="command-title">
      <button class="absolute inset-0 cursor-default" aria-label="Close command palette" @click="commandOpen = false"></button>
      <section class="relative mx-auto w-full max-w-2xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-950/15 dark:border-zinc-800 dark:bg-zinc-900 dark:shadow-black/40">
        <div class="border-b border-slate-100 p-3 dark:border-zinc-800">
          <h2 id="command-title" class="sr-only">Command palette</h2>
          <div class="relative">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400 dark:text-zinc-500" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" viewBox="0 0 24 24"><path v-for="path in icons.search" :key="path" :d="path" /></svg>
            <input v-model="commandQuery" class="field h-12 border-0 bg-slate-50 pl-10 text-base dark:bg-zinc-800" placeholder="Jump to a page or create something" autofocus @keydown.enter.prevent="filteredCommands[0] && runCommand(filteredCommands[0])" />
          </div>
        </div>
        <div class="max-h-[24rem] overflow-y-auto p-2">
          <button v-for="item in filteredCommands" :key="`${item.group}-${item.label}`" class="flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left hover:bg-slate-100 dark:hover:bg-zinc-800" @click="runCommand(item)">
            <span class="grid h-9 w-9 place-items-center rounded-xl bg-slate-100 text-slate-500 dark:bg-zinc-800 dark:text-zinc-400">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" viewBox="0 0 24 24"><path v-for="path in icons[item.icon]" :key="path" :d="path" /></svg>
            </span>
            <span class="min-w-0 flex-1">
              <span class="block truncate text-sm font-extrabold text-slate-900 dark:text-zinc-100">{{ item.label }}</span>
              <span class="text-xs font-bold uppercase text-slate-400 dark:text-zinc-600">{{ item.group }}</span>
            </span>
          </button>
          <div v-if="filteredCommands.length === 0" class="p-8 text-center text-sm font-semibold text-slate-500 dark:text-zinc-500">No matching command.</div>
        </div>
      </section>
    </div>

    <!-- Toasts -->
    <div class="fixed right-3 top-3 z-50 grid w-[min(24rem,calc(100vw-1.5rem))] gap-2 sm:right-5 sm:top-5">
      <div
        v-for="item in feedback.toasts"
        :key="item.id"
        class="rounded-2xl border bg-white p-4 shadow-2xl shadow-slate-950/10 dark:bg-zinc-900 dark:shadow-black/30"
        :class="item.tone === 'success' ? 'border-teal-200 dark:border-teal-800' : item.tone === 'error' ? 'border-red-200 dark:border-red-900' : 'border-slate-200 dark:border-zinc-800'"
      >
        <div class="flex items-start justify-between gap-3">
          <div class="flex gap-3">
            <span class="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-xl" :class="item.tone === 'success' ? 'bg-teal-50 text-teal-700 dark:bg-teal-900/40 dark:text-teal-400' : item.tone === 'error' ? 'bg-red-50 text-red-700 dark:bg-red-900/40 dark:text-red-400' : 'bg-slate-100 text-slate-600 dark:bg-zinc-800 dark:text-zinc-400'">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path v-for="path in icons[item.tone === 'error' ? 'alert' : 'check']" :key="path" :d="path" /></svg>
            </span>
            <div>
              <p class="font-semibold" :class="item.tone === 'success' ? 'text-teal-800 dark:text-teal-300' : item.tone === 'error' ? 'text-red-700 dark:text-red-400' : 'text-slate-800 dark:text-zinc-200'">{{ item.title }}</p>
              <p v-if="item.message" class="mt-1 text-sm text-slate-500 dark:text-zinc-500">{{ item.message }}</p>
            </div>
          </div>
          <button class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:text-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-300" aria-label="Dismiss notification" @click="dismissToast(item.id)">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" viewBox="0 0 24 24"><path v-for="path in icons.close" :key="path" :d="path" /></svg>
          </button>
        </div>
      </div>
    </div>
    <!-- Keyboard shortcuts modal -->
    <div v-if="shortcutsOpen" class="fixed inset-0 z-50 grid place-items-center bg-slate-950/50 p-4 backdrop-blur-[2px]" @click.self="shortcutsOpen = false">
      <section class="w-full max-w-md overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-zinc-800 dark:bg-zinc-900">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-zinc-800">
          <h2 class="font-extrabold">Keyboard Shortcuts</h2>
          <button class="text-slate-400 hover:text-slate-700" @click="shortcutsOpen = false">✕</button>
        </div>
        <div class="divide-y divide-slate-100 p-2 dark:divide-zinc-800">
          <div v-for="[keys, desc] in [['N', 'Quick add'], ['/', 'Focus search'], ['⌘K', 'Command palette'], ['?', 'This shortcuts modal'], ['Esc', 'Close modal / overlay']]" :key="String(keys)" class="flex items-center justify-between px-3 py-2.5">
            <span class="text-sm font-semibold text-slate-700 dark:text-zinc-300">{{ desc }}</span>
            <kbd class="rounded border border-slate-200 bg-slate-100 px-2 py-1 text-xs font-bold text-slate-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400">{{ keys }}</kbd>
          </div>
        </div>
      </section>
    </div>
    <!-- Quota exceeded banner -->
    <QuotaExceededBanner />
    <!-- Confirm dialog -->
    <Transition name="confirm-dialog">
      <div
        v-if="feedback.confirm.open"
        class="fixed inset-0 z-50 flex items-end justify-center bg-slate-950/50 backdrop-blur-[2px] sm:items-center sm:p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="confirm-title"
        @click.self="resolveConfirm(false)"
      >
        <section class="confirm-panel w-full overflow-hidden rounded-t-3xl border border-slate-200 bg-white shadow-2xl dark:border-zinc-800 dark:bg-zinc-900 sm:max-w-md sm:rounded-2xl">
          <div class="border-b border-slate-100 bg-slate-50/70 px-5 py-4 dark:border-zinc-800 dark:bg-zinc-900/60">
            <div class="flex items-start gap-3">
              <span class="grid h-10 w-10 shrink-0 place-items-center rounded-2xl" :class="feedback.confirm.danger ? 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-400' : 'bg-teal-50 text-teal-700 dark:bg-teal-900/30 dark:text-teal-400'">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" viewBox="0 0 24 24"><path v-for="path in icons[feedback.confirm.danger ? 'alert' : 'check']" :key="path" :d="path" /></svg>
              </span>
              <div class="min-w-0 flex-1">
                <p class="text-xs font-extrabold uppercase" :class="feedback.confirm.danger ? 'text-red-700 dark:text-red-400' : 'text-teal-700 dark:text-teal-500'">{{ feedback.confirm.danger ? 'Butuh konfirmasi' : 'Konfirmasi aksi' }}</p>
                <h2 id="confirm-title" class="mt-1 text-xl font-extrabold">{{ feedback.confirm.title }}</h2>
              </div>
            </div>
          </div>
          <div class="px-5 py-4">
            <p class="text-sm leading-6 text-slate-600 dark:text-zinc-400">{{ feedback.confirm.message }}</p>
          </div>
          <div class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50/70 px-5 py-4 pb-[calc(1rem+env(safe-area-inset-bottom))] dark:border-zinc-800 dark:bg-zinc-900/60 sm:pb-4">
            <button ref="confirmCancelRef" class="btn btn-muted" @click="resolveConfirm(false)">Batal</button>
            <button class="btn" :class="feedback.confirm.danger ? 'btn-danger' : 'btn-primary'" @click="resolveConfirm(true)">{{ feedback.confirm.confirmLabel }}</button>
          </div>
        </section>
      </div>
    </Transition>
  </div>
</template>

<style>
.quick-modal-enter-active { transition: opacity 0.15s ease; }
.quick-modal-leave-active { transition: opacity 0.1s ease; }
.quick-modal-enter-from,
.quick-modal-leave-to { opacity: 0; }
.quick-modal-enter-active .quick-panel {
  transition: transform 0.18s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.quick-modal-enter-from .quick-panel { transform: translateY(100%); }
@media (min-width: 640px) {
  .quick-modal-enter-from .quick-panel { transform: scale(0.95) translateY(8px); }
}

.confirm-dialog-enter-active { transition: opacity 0.15s ease; }
.confirm-dialog-leave-active { transition: opacity 0.1s ease; }
.confirm-dialog-enter-from,
.confirm-dialog-leave-to { opacity: 0; }

.confirm-dialog-enter-active .confirm-panel {
  transition: transform 0.18s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.confirm-dialog-enter-from .confirm-panel {
  transform: translateY(100%);
}
@media (min-width: 640px) {
  .confirm-dialog-enter-from .confirm-panel {
    transform: scale(0.95) translateY(8px);
  }
}
</style>
