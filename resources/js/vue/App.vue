<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router';
import { api } from './api';
import DatePicker from './components/DatePicker.vue';
import { dismissToast, feedback, resolveConfirm, toast } from './feedback';
import { pasteLinkOverSelection } from './linkPaste';
import { useAuthStore } from './stores/auth';

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();
const quick = ref(false);
const mobileMenu = ref(false);
const profileMenu = ref(false);
const commandOpen = ref(false);
const commandQuery = ref('');
const searchInput = ref<HTMLInputElement | null>(null);
const query = ref('');
const quickForm = ref({ type: 'work_log', title: '', project_name: '', duration_minutes: 30, notes: '', date: new Date().toISOString().slice(0, 10) });
const isGuest = computed(() => !auth.user);
const navGroups = [
  {
    label: 'Overview',
    items: [
      { label: 'Dashboard', href: '/dashboard', icon: 'dashboard' },
      { label: 'Search', href: '/search', icon: 'search' },
      { label: 'Projects', href: '/projects', icon: 'folder' },
    ],
  },
  {
    label: 'Work',
    items: [
      { label: 'Daily Progress', href: '/daily-progress', icon: 'calendar' },
      { label: 'Work Logs', href: '/work-logs', icon: 'briefcase' },
      { label: 'Tasks', href: '/tasks', icon: 'check' },
    ],
  },
  {
    label: 'Learning & Goals',
    items: [
      { label: 'Learning', href: '/learning', icon: 'book' },
      { label: 'Milestones', href: '/milestones', icon: 'target' },
    ],
  },
  {
    label: 'Review',
    items: [
      { label: 'Weekly Report', href: '/reports/weekly', icon: 'chart' },
    ],
  },
  {
    label: 'System',
    items: [
      { label: 'Configuration', href: '/configuration', icon: 'settings' },
    ],
  },
];
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
};
const initials = computed(() => (auth.user?.name || 'U').split(' ').map((part) => part[0]).join('').slice(0, 2).toUpperCase());
const commandItems = computed(() => [
  ...navGroups.flatMap((group) => group.items.map((item) => ({ ...item, group: group.label, action: 'navigate' }))),
  { label: 'New Daily Progress', href: '/daily-progress/create', icon: 'calendar', group: 'Create', action: 'navigate' },
  { label: 'New Work Log', href: '/work-logs/create', icon: 'briefcase', group: 'Create', action: 'navigate' },
  { label: 'New Task', href: '/tasks/create', icon: 'check', group: 'Create', action: 'navigate' },
  { label: 'New Learning Entry', href: '/learning/create', icon: 'book', group: 'Create', action: 'navigate' },
  { label: 'New Milestone', href: '/milestones/create', icon: 'target', group: 'Create', action: 'navigate' },
  { label: 'Open Quick Add', href: '#quick-add', icon: 'plus', group: 'Action', action: 'quick' },
  { label: 'Configuration', href: '/configuration', icon: 'settings', group: 'System', action: 'navigate' },
  { label: 'Profile Settings', href: '/profile', icon: 'user', group: 'Account', action: 'navigate' },
]);
const filteredCommands = computed(() => {
  const term = commandQuery.value.trim().toLowerCase();
  return commandItems.value.filter((item) => !term || `${item.label} ${item.group}`.toLowerCase().includes(term)).slice(0, 9);
});

async function submitQuick() {
  await api.post('/api/v1/quick-capture', quickForm.value);
  quick.value = false;
  toast({ tone: 'success', title: 'Captured', message: 'Your quick entry has been saved.' });
  quickForm.value.title = '';
  quickForm.value.notes = '';
  await router.push('/dashboard');
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
  const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
  document.documentElement.classList.toggle('dark', theme === 'dark' || (theme === 'system' && prefersDark));
}

function shortcuts(event: KeyboardEvent) {
  const target = event.target as HTMLElement | null;
  const typing = ['INPUT', 'TEXTAREA', 'SELECT'].includes(target?.tagName || '');
  if (event.key === 'Escape') {
    quick.value = false;
    mobileMenu.value = false;
    profileMenu.value = false;
    commandOpen.value = false;
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
}

function isActive(href: string) {
  return route.path === href || (href !== '/dashboard' && route.path.startsWith(`${href}/`));
}

watch(() => auth.user?.theme, (theme) => applyTheme(theme || 'system'), { immediate: true });
watch(() => route.fullPath, () => {
  mobileMenu.value = false;
  profileMenu.value = false;
  commandOpen.value = false;
});
onMounted(() => window.addEventListener('keydown', shortcuts));
onUnmounted(() => window.removeEventListener('keydown', shortcuts));
</script>

<template>
  <RouterView v-if="isGuest" />
  <div v-else class="min-h-screen bg-[#f7f8fa] text-slate-950 lg:flex">
    <aside class="hidden border-r border-slate-200 bg-white/90 px-4 py-5 shadow-[12px_0_40px_rgb(15_23_42/0.035)] backdrop-blur lg:fixed lg:inset-y-0 lg:block lg:w-72">
      <RouterLink to="/dashboard" class="mb-7 flex items-center gap-3">
        <span class="grid h-10 w-10 place-items-center rounded-2xl bg-teal-700 text-white shadow-lg shadow-teal-900/10">
          <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" viewBox="0 0 24 24"><path v-for="path in icons.spark" :key="path" :d="path" /></svg>
        </span>
        <span>
          <span class="block text-base font-extrabold tracking-tight">ProgressOS</span>
          <span class="text-xs font-semibold text-slate-500">Personal operating system</span>
        </span>
      </RouterLink>
      <nav class="space-y-6">
        <section v-for="group in navGroups" :key="group.label">
          <p class="mb-2 px-3 text-[11px] font-extrabold uppercase text-slate-400">{{ group.label }}</p>
          <div class="space-y-1">
            <RouterLink
              v-for="item in group.items"
              :key="item.href"
              :to="item.href"
              class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-bold text-slate-600 transition hover:bg-slate-100 hover:text-slate-950"
              :class="isActive(item.href) ? 'bg-teal-50 text-teal-800 shadow-[inset_0_0_0_1px_rgb(20_184_166/0.18)]' : ''"
            >
              <span class="grid h-8 w-8 place-items-center rounded-lg bg-slate-100 text-slate-500 transition group-hover:bg-white group-hover:text-teal-700" :class="isActive(item.href) ? 'bg-white text-teal-700' : ''">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" viewBox="0 0 24 24"><path v-for="path in icons[item.icon]" :key="path" :d="path" /></svg>
              </span>
              {{ item.label }}
            </RouterLink>
          </div>
        </section>
      </nav>
    </aside>
    <main class="min-w-0 flex-1 lg:pl-72">
      <header class="sticky top-0 z-30 border-b border-slate-200 bg-[#f7f8fa]/90 px-3 py-3 backdrop-blur-xl sm:px-5">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-2 sm:flex-nowrap">
          <button class="btn btn-muted px-3 lg:hidden" aria-label="Open menu" @click="mobileMenu = true">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" viewBox="0 0 24 24"><path v-for="path in icons.menu" :key="path" :d="path" /></svg>
            <span class="hidden sm:inline">Menu</span>
          </button>
          <RouterLink to="/dashboard" class="mr-auto flex items-center gap-2 text-base font-extrabold lg:hidden">
            <span class="grid h-9 w-9 place-items-center rounded-xl bg-teal-700 text-white">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" viewBox="0 0 24 24"><path v-for="path in icons.spark" :key="path" :d="path" /></svg>
            </span>
            <span class="hidden sm:inline">ProgressOS</span>
          </RouterLink>
          <form class="relative order-last mt-2 w-full min-w-0 sm:order-none sm:mt-0 sm:block sm:flex-1" @submit.prevent="search">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" viewBox="0 0 24 24"><path v-for="path in icons.search" :key="path" :d="path" /></svg>
            <input ref="searchInput" v-model="query" class="field h-11 pl-9" placeholder="Search everything" aria-label="Search everything" />
            <button type="button" class="absolute right-2 top-1/2 hidden -translate-y-1/2 rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs font-bold text-slate-400 md:block" @click="commandOpen = true">Cmd K</button>
          </form>
          <button class="btn btn-primary px-3 sm:px-4" title="Quick add" aria-label="Quick add" @click="quick = true">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path v-for="path in icons.plus" :key="path" :d="path" /></svg>
            <span class="hidden sm:inline">Quick Add</span>
          </button>
          <div class="relative">
            <button class="flex h-11 items-center gap-2 rounded-xl border border-slate-200 bg-white px-2.5 text-sm font-bold text-slate-700 shadow-sm transition hover:border-teal-200 hover:text-teal-800" aria-label="Open profile menu" aria-haspopup="menu" :aria-expanded="profileMenu" @click="profileMenu = !profileMenu">
              <img v-if="auth.user?.avatar_url" :src="auth.user.avatar_url" class="h-8 w-8 rounded-lg object-cover" alt="" />
              <span v-else class="grid h-8 w-8 place-items-center rounded-lg bg-slate-900 text-xs font-extrabold text-white">{{ initials }}</span>
              <span class="hidden max-w-32 truncate md:block">{{ auth.user?.name || 'Profile' }}</span>
            </button>
            <button v-if="profileMenu" class="fixed inset-0 z-10 cursor-default" tabindex="-1" aria-label="Close profile menu" @click="profileMenu = false"></button>
            <div v-if="profileMenu" class="absolute right-0 z-20 mt-2 w-64 overflow-hidden rounded-2xl border border-slate-200 bg-white p-2 shadow-2xl shadow-slate-900/10" role="menu">
              <div class="border-b border-slate-100 px-3 py-3">
                <div class="flex items-center gap-3">
                  <img v-if="auth.user?.avatar_url" :src="auth.user.avatar_url" class="h-10 w-10 rounded-xl object-cover" alt="" />
                  <span v-else class="grid h-10 w-10 place-items-center rounded-xl bg-slate-900 text-xs font-extrabold text-white">{{ initials }}</span>
                  <div class="min-w-0">
                    <p class="truncate text-sm font-extrabold text-slate-900">{{ auth.user?.name }}</p>
                    <p class="truncate text-xs font-semibold text-slate-500">{{ auth.user?.email }}</p>
                  </div>
                </div>
              </div>
              <RouterLink to="/profile" class="mt-2 flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-100" role="menuitem">
                <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" viewBox="0 0 24 24"><path v-for="path in icons.user" :key="path" :d="path" /></svg>
                Profile settings
              </RouterLink>
              <button class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-bold text-red-700 hover:bg-red-50" role="menuitem" @click="logout">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" viewBox="0 0 24 24"><path v-for="path in icons.logout" :key="path" :d="path" /></svg>
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
    <div v-if="mobileMenu" class="fixed inset-0 z-40 lg:hidden" role="dialog" aria-modal="true" aria-label="Navigation menu">
      <button class="absolute inset-0 bg-slate-950/35 backdrop-blur-[2px]" aria-label="Close menu" @click="mobileMenu = false"></button>
      <aside class="relative h-full w-[min(22rem,88vw)] overflow-y-auto border-r border-slate-200 bg-white p-4 shadow-2xl">
        <div class="mb-6 flex items-center justify-between">
          <RouterLink to="/dashboard" class="flex items-center gap-3">
            <span class="grid h-10 w-10 place-items-center rounded-2xl bg-teal-700 text-white">
              <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" viewBox="0 0 24 24"><path v-for="path in icons.spark" :key="path" :d="path" /></svg>
            </span>
            <span class="font-extrabold">ProgressOS</span>
          </RouterLink>
          <button class="btn btn-muted px-3" aria-label="Close menu" @click="mobileMenu = false">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" viewBox="0 0 24 24"><path v-for="path in icons.close" :key="path" :d="path" /></svg>
          </button>
        </div>
        <nav class="space-y-6">
          <section v-for="group in navGroups" :key="group.label">
            <p class="mb-2 px-3 text-[11px] font-extrabold uppercase text-slate-400">{{ group.label }}</p>
            <div class="space-y-1">
              <RouterLink
                v-for="item in group.items"
                :key="item.href"
                :to="item.href"
                class="flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-bold text-slate-600 hover:bg-slate-100"
                :class="isActive(item.href) ? 'bg-teal-50 text-teal-800' : ''"
              >
                <span class="grid h-8 w-8 place-items-center rounded-lg bg-slate-100" :class="isActive(item.href) ? 'bg-white text-teal-700' : 'text-slate-500'">
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" viewBox="0 0 24 24"><path v-for="path in icons[item.icon]" :key="path" :d="path" /></svg>
                </span>
                {{ item.label }}
              </RouterLink>
            </div>
          </section>
        </nav>
      </aside>
    </div>
    <div v-if="quick" class="fixed inset-0 z-40 grid place-items-center bg-slate-950/45 p-4 backdrop-blur-[2px]" role="dialog" aria-modal="true" aria-labelledby="quick-add-title">
      <form class="card w-full max-w-xl overflow-hidden p-0 shadow-2xl shadow-slate-950/10" @submit.prevent="submitQuick">
        <div class="border-b border-slate-100 bg-slate-50/70 px-5 py-4">
          <div class="flex items-center justify-between gap-3">
            <div>
              <p class="text-xs font-extrabold uppercase text-teal-700">Capture</p>
              <h2 id="quick-add-title" class="text-lg font-extrabold">Quick Add</h2>
            </div>
            <button type="button" class="btn btn-muted px-3" aria-label="Close quick add" @click="quick = false">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" viewBox="0 0 24 24"><path v-for="path in icons.close" :key="path" :d="path" /></svg>
            </button>
          </div>
        </div>
        <div class="grid gap-3 p-5 sm:grid-cols-2">
          <select v-model="quickForm.type" class="field"><option value="task">Task</option><option value="blocker">Blocker</option><option value="work_log">Work log</option><option value="daily_progress">Daily progress</option><option value="learning">Learning</option></select>
          <DatePicker v-model="quickForm.date" label="Quick add date" />
          <input v-model="quickForm.title" class="field sm:col-span-2" placeholder="Title" required />
          <input v-model="quickForm.project_name" class="field" placeholder="Project" />
          <input v-model="quickForm.duration_minutes" class="field" type="number" min="1" />
          <textarea v-model="quickForm.notes" class="field min-h-28 sm:col-span-2" placeholder="Notes" @paste="handleQuickNotesPaste" />
        </div>
        <div class="flex justify-end border-t border-slate-100 bg-slate-50/70 px-5 py-4"><button class="btn btn-primary">Capture</button></div>
      </form>
    </div>
    <button class="fixed bottom-4 right-4 z-30 grid h-14 w-14 place-items-center rounded-2xl bg-teal-700 text-white shadow-2xl shadow-teal-900/25 sm:hidden" aria-label="Open floating action" @click="quick = true">
      <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path v-for="path in icons.plus" :key="path" :d="path" /></svg>
    </button>
    <div v-if="commandOpen" class="fixed inset-0 z-50 grid place-items-start bg-slate-950/40 p-3 pt-20 backdrop-blur-[2px] sm:p-6 sm:pt-24" role="dialog" aria-modal="true" aria-labelledby="command-title">
      <button class="absolute inset-0 cursor-default" aria-label="Close command palette" @click="commandOpen = false"></button>
      <section class="relative mx-auto w-full max-w-2xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-950/15">
        <div class="border-b border-slate-100 p-3">
          <h2 id="command-title" class="sr-only">Command palette</h2>
          <div class="relative">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" viewBox="0 0 24 24"><path v-for="path in icons.search" :key="path" :d="path" /></svg>
            <input v-model="commandQuery" class="field h-12 border-0 bg-slate-50 pl-10 text-base" placeholder="Jump to a page or create something" autofocus @keydown.enter.prevent="filteredCommands[0] && runCommand(filteredCommands[0])" />
          </div>
        </div>
        <div class="max-h-[24rem] overflow-y-auto p-2">
          <button v-for="item in filteredCommands" :key="`${item.group}-${item.label}`" class="flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left hover:bg-slate-100" @click="runCommand(item)">
            <span class="grid h-9 w-9 place-items-center rounded-xl bg-slate-100 text-slate-500">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" viewBox="0 0 24 24"><path v-for="path in icons[item.icon]" :key="path" :d="path" /></svg>
            </span>
            <span class="min-w-0 flex-1">
              <span class="block truncate text-sm font-extrabold text-slate-900">{{ item.label }}</span>
              <span class="text-xs font-bold uppercase text-slate-400">{{ item.group }}</span>
            </span>
          </button>
          <div v-if="filteredCommands.length === 0" class="p-8 text-center text-sm font-semibold text-slate-500">No matching command.</div>
        </div>
      </section>
    </div>
    <div class="fixed right-3 top-3 z-50 grid w-[min(24rem,calc(100vw-1.5rem))] gap-2 sm:right-5 sm:top-5">
      <div v-for="item in feedback.toasts" :key="item.id" class="rounded-2xl border bg-white p-4 shadow-2xl shadow-slate-950/10" :class="item.tone === 'success' ? 'border-teal-200' : item.tone === 'error' ? 'border-red-200' : 'border-slate-200'">
        <div class="flex items-start justify-between gap-3">
          <div class="flex gap-3">
            <span class="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-xl" :class="item.tone === 'success' ? 'bg-teal-50 text-teal-700' : item.tone === 'error' ? 'bg-red-50 text-red-700' : 'bg-slate-100 text-slate-600'">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path v-for="path in icons[item.tone === 'error' ? 'alert' : 'check']" :key="path" :d="path" /></svg>
            </span>
            <div>
            <p class="font-semibold" :class="item.tone === 'success' ? 'text-teal-800' : item.tone === 'error' ? 'text-red-700' : 'text-slate-800'">{{ item.title }}</p>
            <p v-if="item.message" class="mt-1 text-sm text-slate-500">{{ item.message }}</p>
            </div>
          </div>
          <button class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="Dismiss notification" @click="dismissToast(item.id)">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" viewBox="0 0 24 24"><path v-for="path in icons.close" :key="path" :d="path" /></svg>
          </button>
        </div>
      </div>
    </div>
    <div v-if="feedback.confirm.open" class="fixed inset-0 z-50 grid place-items-center bg-slate-950/45 p-4 backdrop-blur-[2px]" role="dialog" aria-modal="true" aria-labelledby="confirm-title">
      <section class="card w-full max-w-md overflow-hidden p-0 shadow-2xl shadow-slate-950/15">
        <div class="border-b border-slate-100 bg-slate-50/70 px-5 py-4">
          <div class="flex items-start gap-3">
            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-2xl" :class="feedback.confirm.danger ? 'bg-red-50 text-red-700' : 'bg-teal-50 text-teal-700'">
              <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" viewBox="0 0 24 24"><path v-for="path in icons[feedback.confirm.danger ? 'alert' : 'check']" :key="path" :d="path" /></svg>
            </span>
            <div>
              <p class="text-xs font-extrabold uppercase" :class="feedback.confirm.danger ? 'text-red-700' : 'text-teal-700'">{{ feedback.confirm.danger ? 'Needs confirmation' : 'Confirm action' }}</p>
              <h2 id="confirm-title" class="mt-1 text-xl font-extrabold">{{ feedback.confirm.title }}</h2>
            </div>
          </div>
        </div>
        <div class="px-5 py-4">
          <p class="mt-2 text-sm leading-6 text-slate-600">{{ feedback.confirm.message }}</p>
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50/70 px-5 py-4">
          <button class="btn btn-muted" @click="resolveConfirm(false)">Cancel</button>
          <button class="btn" :class="feedback.confirm.danger ? 'border border-red-200 bg-red-600 text-white hover:bg-red-700' : 'btn-primary'" @click="resolveConfirm(true)">{{ feedback.confirm.confirmLabel }}</button>
        </div>
      </section>
    </div>
  </div>
</template>
