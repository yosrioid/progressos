<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { RouterLink, RouterView, useRouter } from 'vue-router';
import { api } from './api';
import { pasteLinkOverSelection } from './linkPaste';
import { useAuthStore } from './stores/auth';

const auth = useAuthStore();
const router = useRouter();
const quick = ref(false);
const searchInput = ref<HTMLInputElement | null>(null);
const query = ref('');
const quickForm = ref({ type: 'work_log', title: '', project_name: '', duration_minutes: 30, notes: '', date: new Date().toISOString().slice(0, 10) });
const isGuest = computed(() => !auth.user);
const nav = [
  ['Dashboard', '/dashboard'],
  ['Projects', '/projects'],
  ['Daily Progress', '/daily-progress'],
  ['Work Logs', '/work-logs'],
  ['Tasks', '/tasks'],
  ['Learning', '/learning'],
  ['Milestones', '/milestones'],
  ['Weekly Report', '/reports/weekly'],
  ['Profile', '/profile'],
];

async function submitQuick() {
  await api.post('/api/v1/quick-capture', quickForm.value);
  quick.value = false;
  quickForm.value.title = '';
  quickForm.value.notes = '';
  await router.push('/dashboard');
}

async function logout() {
  await auth.logout();
  await router.push('/login');
}

function search() {
  if (query.value.trim()) router.push(`/search?q=${encodeURIComponent(query.value.trim())}`);
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
  if (event.key === 'Escape') quick.value = false;
  if (typing) return;
  if (event.key === '/') {
    event.preventDefault();
    searchInput.value?.focus();
  }
  if (event.key.toLowerCase() === 'n') {
    event.preventDefault();
    quick.value = true;
  }
}

watch(() => auth.user?.theme, (theme) => applyTheme(theme || 'system'), { immediate: true });
onMounted(() => window.addEventListener('keydown', shortcuts));
onUnmounted(() => window.removeEventListener('keydown', shortcuts));
</script>

<template>
  <RouterView v-if="isGuest" />
  <div v-else class="min-h-screen bg-slate-50 text-slate-950 lg:flex">
    <aside class="hidden border-r border-slate-200 bg-white px-4 py-4 lg:fixed lg:inset-y-0 lg:block lg:w-64">
      <RouterLink to="/dashboard" class="mb-6 block text-lg font-semibold">ProgressOS</RouterLink>
      <nav class="space-y-1">
        <RouterLink v-for="[label, href] in nav" :key="href" :to="href" class="flex items-center rounded-lg px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100" active-class="bg-teal-50 text-teal-800">{{ label }}</RouterLink>
      </nav>
    </aside>
    <main class="min-w-0 flex-1 lg:pl-64">
      <header class="sticky top-0 z-20 border-b border-slate-200 bg-slate-50/95 px-3 py-3 backdrop-blur sm:px-4">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-2 sm:flex-nowrap">
          <RouterLink to="/dashboard" class="mr-auto text-base font-semibold lg:hidden">ProgressOS</RouterLink>
          <form class="relative min-w-0 flex-1" @submit.prevent="search"><input ref="searchInput" v-model="query" class="field" placeholder="Search everything" aria-label="Search everything" /></form>
          <button class="btn btn-primary" title="Quick add" aria-label="Quick add" @click="quick = true">Quick Add</button>
          <RouterLink class="btn btn-muted hidden sm:inline-flex" to="/profile">{{ auth.user?.name || 'Profile' }}</RouterLink>
          <button class="btn btn-muted" @click="logout">Logout</button>
        </div>
      </header>
      <section class="bottom-nav-safe mx-auto max-w-7xl px-3 py-4 sm:px-4 sm:py-6">
        <RouterView />
      </section>
    </main>
    <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200 bg-white/95 px-2 pb-2 pt-2 shadow-[0_-8px_30px_rgb(15_23_42/0.08)] lg:hidden">
      <div class="mx-auto grid max-w-md grid-cols-5 gap-1">
        <RouterLink v-for="[label, href] in nav.slice(0, 5)" :key="href" :to="href" class="rounded-xl px-2 py-2 text-center text-[11px] font-semibold text-slate-500" active-class="bg-teal-50 text-teal-800">{{ label.split(' ')[0] }}</RouterLink>
      </div>
    </nav>
    <div v-if="quick" class="fixed inset-0 z-40 grid place-items-center bg-slate-950/40 p-4" role="dialog" aria-modal="true" aria-labelledby="quick-add-title">
      <form class="card w-full max-w-xl p-5" @submit.prevent="submitQuick">
        <div class="mb-4 flex items-center justify-between"><h2 id="quick-add-title" class="text-lg font-semibold">Quick Add</h2><button type="button" class="btn btn-muted" @click="quick = false">Close</button></div>
        <div class="grid gap-3 sm:grid-cols-2">
          <select v-model="quickForm.type" class="field"><option value="task">Task</option><option value="blocker">Blocker</option><option value="work_log">Work log</option><option value="daily_progress">Daily progress</option><option value="learning">Learning</option></select>
          <input v-model="quickForm.date" class="field" type="date" />
          <input v-model="quickForm.title" class="field sm:col-span-2" placeholder="Title" required />
          <input v-model="quickForm.project_name" class="field" placeholder="Project" />
          <input v-model="quickForm.duration_minutes" class="field" type="number" min="1" />
          <textarea v-model="quickForm.notes" class="field min-h-28 sm:col-span-2" placeholder="Notes" @paste="handleQuickNotesPaste" />
        </div>
        <div class="mt-4 flex justify-end"><button class="btn btn-primary">Capture</button></div>
      </form>
    </div>
  </div>
</template>
