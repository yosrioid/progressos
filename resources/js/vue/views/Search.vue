<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { api, unwrap } from '../api';
import { formatDate, minutes } from '../format';

const route = useRoute();
const router = useRouter();
const q = ref(String(route.query.q || ''));
const loading = ref(false);
const results = ref<Record<string, any[]>>({});
const groups = computed(() => [
  ['projects', 'Projects', '/projects'],
  ['daily_progress', 'Daily Progress', '/daily-progress'],
  ['work_logs', 'Work Logs', '/work-logs'],
  ['tasks', 'Tasks', '/tasks'],
  ['learning', 'Learning', '/learning'],
  ['milestones', 'Milestones', '/milestones'],
] as const);
const total = computed(() => Object.values(results.value).reduce((sum, rows) => sum + rows.length, 0));

function itemTitle(item: any) {
  return item.name || item.title || item.topic || 'Untitled';
}

function itemMeta(item: any) {
  return [item.date || item.due_date || item.end_date ? formatDate(item.date || item.due_date || item.end_date) : null, item.status, item.category, item.actual_duration ? minutes(item.actual_duration) : null].filter(Boolean).join(' / ');
}

function href(group: string, item: any) {
  if (group === 'projects') return `/projects/${item.id}`;
  if (group === 'daily_progress') return `/daily-progress/${item.id}`;
  if (group === 'work_logs') return `/work-logs/${item.id}`;
  return `/${group}/${item.id}`;
}

async function load() {
  q.value = String(route.query.q || '');
  if (!q.value.trim()) {
    results.value = {};
    return;
  }
  loading.value = true;
  const data = await api.get('/api/v1/search', { params: { q: q.value } }).then(unwrap);
  results.value = data.results || {};
  loading.value = false;
}

async function submit() {
  await router.push({ path: '/search', query: { q: q.value.trim() } });
}

watch(() => route.fullPath, load);
onMounted(load);
</script>

<template>
  <div class="mb-5">
    <p class="text-sm font-semibold text-teal-700">Global search</p>
    <h1 class="mt-1 text-2xl font-semibold">Search ProgressOS</h1>
  </div>
  <form class="card mb-5 p-4" @submit.prevent="submit">
    <div class="flex flex-col gap-3 sm:flex-row">
      <input v-model="q" class="field" placeholder="Search projects, logs, tasks, learning, milestones..." />
      <button class="btn btn-primary">Search</button>
    </div>
  </form>
  <div v-if="loading" class="card p-8 text-center text-sm text-slate-500">Searching...</div>
  <div v-else-if="q && total === 0" class="card p-8 text-center text-sm text-slate-500">No results for "{{ q }}".</div>
  <div v-else class="grid gap-4">
    <section v-for="[key, label, listHref] in groups" :key="key" class="card p-5">
      <div class="mb-3 flex items-center justify-between gap-3">
        <div><h2 class="font-semibold">{{ label }}</h2><p class="text-sm text-slate-500">{{ results[key]?.length || 0 }} matches</p></div>
        <RouterLink class="text-sm font-semibold text-teal-700" :to="`${listHref}?search=${encodeURIComponent(q)}`">Open list</RouterLink>
      </div>
      <div v-if="results[key]?.length" class="grid gap-2">
        <RouterLink v-for="item in results[key]" :key="`${key}-${item.id}`" :to="href(key, item)" class="rounded-xl border border-slate-200 bg-white p-3 hover:border-teal-200 hover:bg-teal-50/30">
          <b class="text-sm">{{ itemTitle(item) }}</b>
          <p class="mt-1 text-xs text-slate-500">{{ itemMeta(item) }}</p>
        </RouterLink>
      </div>
      <p v-else class="rounded-xl border border-dashed border-slate-200 p-4 text-sm text-slate-500">No matches in this area.</p>
    </section>
  </div>
</template>
