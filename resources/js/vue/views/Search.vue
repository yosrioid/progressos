<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { api, unwrap } from '../api';
import { formatDate, minutes } from '../format';

const RECENT_KEY = 'search_recent';

const route = useRoute();
const router = useRouter();
const q = ref(String(route.query.q || ''));
const loading = ref(false);
const results = ref<Record<string, any[]>>({});
const activeType = ref('all');
const recentSearches = ref<string[]>([]);

const groups = [
  { key: 'projects', label: 'Projects', path: '/projects' },
  { key: 'work_logs', label: 'Work Logs', path: '/work-logs' },
  { key: 'tasks', label: 'Tasks', path: '/tasks' },
  { key: 'learning', label: 'Learning', path: '/learning' },
  { key: 'milestones', label: 'Milestones', path: '/milestones' },
  { key: 'daily_progress', label: 'Daily Progress', path: '/daily-progress' },
  { key: 'goals', label: 'Goals', path: '/goals' },
  { key: 'habits', label: 'Habits', path: '/habits' },
  { key: 'docs', label: 'Docs', path: '/docs' },
] as const;

const total = computed(() => Object.values(results.value).reduce((sum, rows) => sum + rows.length, 0));

const visibleGroups = computed(() =>
  activeType.value === 'all'
    ? groups.filter(g => (results.value[g.key]?.length ?? 0) > 0)
    : groups.filter(g => g.key === activeType.value),
);

const typeTabCounts = computed(() =>
  groups.map(g => ({ ...g, count: results.value[g.key]?.length ?? 0 })),
);

function itemTitle(item: any) {
  return item.name || item.title || item.topic || 'Untitled';
}

function itemMeta(item: any) {
  return [
    item.date || item.due_date || item.end_date || item.updated_at ? formatDate(item.date || item.due_date || item.end_date || item.updated_at) : null,
    item.status,
    item.category,
    item.frequency,
    item.period_label ?? null,
    item.actual_duration ? minutes(item.actual_duration) : null,
    item.project_name ?? null,
  ].filter(Boolean).join(' · ');
}

function href(key: string, item: any) {
  if (key === 'projects') return `/projects/${item.id}`;
  if (key === 'daily_progress') return `/daily-progress/${item.id}`;
  if (key === 'work_logs') return `/work-logs/${item.id}`;
  if (key === 'goals') return '/goals';
  if (key === 'habits') return '/habits';
  if (key === 'docs') return `/docs/${item.id}`;
  return `/${key}/${item.id}`;
}

function escapeHtml(str: string) {
  return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

function highlight(text: string, term: string) {
  const safe = escapeHtml(text);
  if (!term.trim()) return safe;
  const escaped = term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  return safe.replace(new RegExp(`(${escapeHtml(escaped)})`, 'gi'), '<mark class="bg-yellow-100 dark:bg-yellow-800/50 rounded px-0.5">$1</mark>');
}

function loadRecent() {
  try {
    recentSearches.value = JSON.parse(localStorage.getItem(RECENT_KEY) || '[]');
  } catch {
    recentSearches.value = [];
  }
}

function saveRecent(term: string) {
  const list = [term, ...recentSearches.value.filter(r => r !== term)].slice(0, 6);
  recentSearches.value = list;
  localStorage.setItem(RECENT_KEY, JSON.stringify(list));
}

function removeRecent(term: string) {
  recentSearches.value = recentSearches.value.filter(r => r !== term);
  localStorage.setItem(RECENT_KEY, JSON.stringify(recentSearches.value));
}

async function load() {
  q.value = String(route.query.q || '');
  if (!q.value.trim()) { results.value = {}; return; }
  loading.value = true;
  saveRecent(q.value.trim());
  const data = await api.get('/api/v1/search', { params: { q: q.value } }).then(unwrap);
  results.value = data.results || {};
  loading.value = false;
}

async function submit() {
  if (!q.value.trim()) return;
  await router.push({ path: '/search', query: { q: q.value.trim() } });
}

async function useRecent(term: string) {
  q.value = term;
  await router.push({ path: '/search', query: { q: term } });
}

watch(() => route.fullPath, load);
onMounted(() => { loadRecent(); load(); });
</script>

<template>
  <div class="mb-5">
    <p class="text-xs font-extrabold uppercase text-teal-700 dark:text-teal-500">Global search</p>
    <h1 class="mt-1 text-2xl font-extrabold">Search ProgressOS</h1>
  </div>

  <form class="card mb-4 p-4" @submit.prevent="submit">
    <div class="flex flex-col gap-3 sm:flex-row">
      <input v-model="q" class="field text-base" placeholder="Search projects, work logs, tasks, learning, milestones..." autofocus />
      <button class="btn btn-primary px-6">Search</button>
    </div>
    <!-- Recent searches -->
    <div v-if="!q && recentSearches.length" class="mt-3 flex flex-wrap items-center gap-2">
      <span class="text-xs font-extrabold uppercase text-slate-400">Recent:</span>
      <button
        v-for="term in recentSearches"
        :key="term"
        type="button"
        class="group flex items-center gap-1 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-600 hover:border-teal-200 hover:text-teal-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400"
        @click="useRecent(term)"
      >
        {{ term }}
        <span class="invisible text-slate-300 group-hover:visible hover:text-red-500" @click.stop="removeRecent(term)">×</span>
      </button>
    </div>
  </form>

  <div v-if="loading" class="card p-10 text-center text-sm text-slate-400">Searching...</div>

  <template v-else-if="q && total > 0">
    <!-- Type filter tabs -->
    <div class="mb-4 flex flex-wrap gap-2">
      <button
        class="rounded-full px-3 py-1.5 text-xs font-extrabold transition"
        :class="activeType === 'all' ? 'bg-teal-700 text-white' : 'border border-slate-200 bg-white text-slate-500 hover:border-teal-200 hover:text-teal-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400'"
        @click="activeType = 'all'"
      >All ({{ total }})</button>
      <button
        v-for="g in typeTabCounts.filter(g => g.count > 0)"
        :key="g.key"
        class="rounded-full px-3 py-1.5 text-xs font-extrabold transition"
        :class="activeType === g.key ? 'bg-teal-700 text-white' : 'border border-slate-200 bg-white text-slate-500 hover:border-teal-200 hover:text-teal-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400'"
        @click="activeType = g.key"
      >{{ g.label }} ({{ g.count }})</button>
    </div>

    <div class="grid gap-4">
      <section v-for="g in visibleGroups" :key="g.key" class="card overflow-hidden p-0">
        <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/60 px-5 py-3 dark:border-zinc-800 dark:bg-zinc-900/50">
          <h2 class="font-extrabold">{{ g.label }}</h2>
          <RouterLink class="text-xs font-extrabold text-teal-700 hover:underline dark:text-teal-400" :to="`${g.path}?search=${encodeURIComponent(q)}`">Open list →</RouterLink>
        </div>
        <div class="divide-y divide-slate-100 dark:divide-zinc-800">
          <RouterLink
            v-for="item in results[g.key]"
            :key="`${g.key}-${item.id}`"
            :to="href(g.key, item)"
            class="flex items-center gap-3 px-5 py-3 hover:bg-teal-50/40 dark:hover:bg-teal-900/10"
          >
            <div class="min-w-0 flex-1">
              <!-- eslint-disable-next-line vue/no-v-html -->
              <p class="truncate text-sm font-extrabold text-slate-800 dark:text-zinc-200" v-html="highlight(itemTitle(item), q)" />
              <p class="mt-0.5 truncate text-xs text-slate-400 dark:text-zinc-600">{{ itemMeta(item) }}</p>
            </div>
            <svg class="h-4 w-4 shrink-0 text-slate-300 dark:text-zinc-600" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
          </RouterLink>
        </div>
      </section>
    </div>
  </template>

  <div v-else-if="q && total === 0" class="card p-12 text-center">
    <p class="text-lg font-extrabold text-slate-700 dark:text-zinc-300">No results</p>
    <p class="mt-2 text-sm text-slate-400">No matches for "{{ q }}".</p>
  </div>

  <div v-else-if="!q" class="card p-10 text-center text-sm text-slate-400 dark:text-zinc-600">
    Type something to search ProgressOS.
  </div>
</template>
