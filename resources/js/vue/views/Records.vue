<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { api, unwrap } from '../api';
import DatePicker from '../components/DatePicker.vue';
import { confirmAction, toast } from '../feedback';
import { formatDate, minutes } from '../format';
import { configs } from '../records';

const learningStats = ref<any>(null);
const learningHeatmap = ref<{ date: string; minutes: number }[]>([]);
const showStats = ref(false);
const updatingStatusId = ref<number | null>(null);

const props = defineProps<{ type: string }>();
const route = useRoute();
const router = useRouter();
const rows = ref<any[]>([]);
const meta = ref<any>(null);
const loading = ref(true);
const savedViews = ref<any[]>([]);
const viewName = ref('');
const savingView = ref(false);
const compact = ref(false);
const filters = ref({ search: '', status: '', category: '', priority: '', project_name: '', project_id: '', from: '', to: '', sort: 'date', direction: 'desc', page: 1 });
const endpoint = computed(() => `/api/v1/${props.type}`);
const config = computed(() => configs[props.type]);
const title = computed(() => ({
  'daily-progress': 'Daily Progress',
  'work-logs': 'Work Logs',
  tasks: 'Tasks',
  learning: 'Learning',
  milestones: 'Milestones',
} as any)[props.type] || props.type);
const statusOptions = computed(() => props.type === 'milestones' ? ['active', 'paused', 'completed', 'cancelled'] : ['todo', 'in_progress', 'done', 'blocked']);
const categoryOptions = computed(() => ({
  'work-logs': ['bug', 'feature', 'research', 'testing', 'setup', 'meeting', 'documentation', 'refactor', 'other'],
  learning: ['programming', 'english', 'japanese', 'german', 'books', 'career', 'other'],
} as any)[props.type] || []);
const sortOptions = computed(() => props.type === 'tasks'
  ? [{ value: 'created_at', label: 'Created' }, { value: 'due_date', label: 'Due date' }, { value: 'priority', label: 'Priority' }, { value: 'status', label: 'Status' }]
  : props.type === 'milestones'
    ? [{ value: 'created_at', label: 'Created' }, { value: 'end_date', label: 'End date' }, { value: 'status', label: 'Status' }, { value: 'category', label: 'Category' }]
    : props.type === 'daily-progress'
      ? [{ value: 'date', label: 'Date' }, { value: 'created_at', label: 'Created' }, { value: 'title', label: 'Title' }]
      : [{ value: 'date', label: 'Date' }, { value: 'created_at', label: 'Created' }, { value: props.type === 'learning' ? 'topic' : 'title', label: 'Title' }, { value: 'category', label: 'Category' }]
);
const emptyState = computed(() => ({
  'daily-progress': ['Start today’s log', 'Capture what moved, what is blocked, and what should happen next.'],
  'work-logs': ['Capture your first work item', 'Track project work, tickets, outcomes, and time spent.'],
  tasks: ['Create a task to focus your day', 'Turn loose work into visible next actions.'],
  learning: ['Log a study session', 'Track minutes, takeaways, and the next thing to practice.'],
  milestones: ['Set your first goal', 'Create measurable outcomes and monitor progress over time.'],
} as any)[props.type] || ['No records yet', 'Create a record to begin.']);
const activeFilters = computed(() => Object.entries(filters.value)
  .filter(([key, value]) => !['page', 'sort', 'direction'].includes(key) && value !== '' && value !== null)
  .map(([key, value]) => ({ key, label: key.replaceAll('_', ' '), value: String(value).replaceAll('_', ' ') })));

async function load() {
  loading.value = true;
  const params = Object.fromEntries(Object.entries(filters.value).filter(([, value]) => value !== '' && value !== null));
  const data = await api.get(endpoint.value, { params }).then(unwrap);
  const page = data[config.value.listKey] || {};
  rows.value = page.data || [];
  meta.value = page;
  loading.value = false;
}

async function loadSavedViews() {
  const data = await api.get('/api/v1/saved-views', { params: { module: props.type } }).then(unwrap);
  savedViews.value = data.saved_views || [];
}

function syncFromRoute() {
  filters.value = {
    search: String(route.query.search || ''),
    status: String(route.query.status || ''),
    category: String(route.query.category || ''),
    priority: String(route.query.priority || ''),
    project_name: String(route.query.project_name || ''),
    project_id: String(route.query.project_id || ''),
    from: String(route.query.from || ''),
    to: String(route.query.to || ''),
    sort: String(route.query.sort || (props.type === 'tasks' || props.type === 'milestones' ? 'created_at' : 'date')),
    direction: String(route.query.direction || 'desc'),
    page: Number(route.query.page || 1),
  };
}

async function applyFilters(page = 1) {
  filters.value.page = page;
  await router.push({ path: `/${props.type}`, query: Object.fromEntries(Object.entries(filters.value).filter(([, value]) => value !== '' && value !== null && value !== 1)) });
}

function clearFilters() {
  filters.value = { search: '', status: '', category: '', priority: '', project_name: '', project_id: '', from: '', to: '', sort: props.type === 'tasks' || props.type === 'milestones' ? 'created_at' : 'date', direction: 'desc', page: 1 };
  applyFilters();
}

function clearFilter(key: string) {
  (filters.value as any)[key] = '';
  applyFilters();
}

function tone(value?: string) {
  if (['done', 'completed', 'active'].includes(value || '')) return 'pill-green';
  if (['in_progress', 'medium', 'feature', 'programming'].includes(value || '')) return 'pill-blue';
  if (['blocked', 'urgent', 'high', 'cancelled'].includes(value || '')) return 'pill-red';
  return 'pill-slate';
}

async function loadLearningStats() {
  const [s, h] = await Promise.all([
    api.get('/api/v1/learning/stats').then(unwrap),
    api.get('/api/v1/learning/heatmap').then(unwrap),
  ]);
  learningStats.value = s;
  learningHeatmap.value = h.heatmap || [];
  showStats.value = true;
}

function heatmapColor(mins: number) {
  if (mins === 0) return 'bg-slate-100 dark:bg-zinc-800';
  if (mins < 30) return 'bg-teal-200 dark:bg-teal-800';
  if (mins < 60) return 'bg-teal-400 dark:bg-teal-600';
  return 'bg-teal-700';
}

function isOverdue(row: any) {
  if (props.type !== 'tasks' || row.status === 'done') return false;
  if (!row.due_date) return false;
  return new Date(row.due_date + 'T00:00:00') < new Date(new Date().toDateString());
}

async function saveCurrentView() {
  if (!viewName.value.trim()) return;
  savingView.value = true;
  await api.post('/api/v1/saved-views', { module: props.type, name: viewName.value.trim(), filters: filters.value, pinned: true });
  toast({ tone: 'success', title: 'View saved', message: viewName.value.trim() });
  viewName.value = '';
  savingView.value = false;
  await loadSavedViews();
}

async function removeSavedView(view: any) {
  const confirmed = await confirmAction({
    title: 'Delete saved view?',
    message: `Remove "${view.name}" from your saved filters.`,
    confirmLabel: 'Delete view',
    danger: true,
  });
  if (!confirmed) return;
  await api.delete(`/api/v1/saved-views/${view.id}`);
  toast({ tone: 'success', title: 'Saved view deleted', message: view.name });
  await loadSavedViews();
}

async function applySavedView(view: any) {
  filters.value = { ...filters.value, ...(view.filters || {}), page: 1 };
  await applyFilters();
}

const statusCycle: Record<string, string> = { todo: 'in_progress', in_progress: 'done', done: 'todo', blocked: 'todo' };

async function cycleTaskStatus(e: Event, row: any) {
  if (props.type !== 'tasks') return;
  e.preventDefault();
  e.stopPropagation();
  if (updatingStatusId.value) return;
  const next = statusCycle[row.status];
  if (!next) return;
  updatingStatusId.value = row.id;
  try {
    await api.patch(`/api/v1/tasks/${row.id}/status`, { status: next });
    row.status = next;
  } finally {
    updatingStatusId.value = null;
  }
}

watch(() => [props.type, route.fullPath], () => { syncFromRoute(); load(); loadSavedViews(); });
onMounted(() => { syncFromRoute(); load(); loadSavedViews(); });
</script>

<template>
  <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div><h1 class="text-3xl font-extrabold tracking-tight">{{ title }}</h1></div>
    <div class="flex flex-wrap items-center gap-2">
      <!-- Saved view quick-access chips -->
      <template v-if="savedViews.length">
        <button
          v-for="view in savedViews"
          :key="view.id"
          class="inline-flex items-center gap-1 rounded-full border border-teal-200 bg-teal-50 px-3 py-1 text-xs font-extrabold text-teal-700 hover:bg-teal-100 dark:border-teal-800 dark:bg-teal-900/20 dark:text-teal-400 dark:hover:bg-teal-900/40"
          @click="applySavedView(view)"
        >
          <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
          {{ view.name }}
        </button>
      </template>
      <button v-if="type === 'learning'" type="button" class="btn btn-muted" @click="showStats ? showStats = false : loadLearningStats()">
        {{ showStats ? 'Sembunyikan Stats' : 'Lihat Stats' }}
      </button>
      <RouterLink class="btn btn-primary" :to="`/${type}/create`">New {{ config.singular }}</RouterLink>
    </div>
  </div>

  <!-- Learning Stats Panel -->
  <div v-if="type === 'learning' && showStats && learningStats" class="mb-5 grid gap-5">
    <div class="card p-5">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="font-extrabold">Learning Stats</h2>
        <span class="text-sm font-semibold text-slate-500">{{ learningStats.totals.entries }} sesi · {{ minutes(learningStats.totals.minutes) }} total</span>
      </div>
      <!-- Heatmap 90 hari -->
      <div class="mb-5">
        <p class="label mb-2">90 Hari Terakhir</p>
        <div class="flex flex-wrap gap-1">
          <div v-for="day in learningHeatmap" :key="day.date"
            class="h-4 w-4 rounded-sm transition"
            :class="heatmapColor(day.minutes)"
            :title="`${day.date}: ${day.minutes} menit`" />
        </div>
        <div class="mt-1 flex items-center gap-2 text-xs text-slate-400">
          <span>Sedikit</span>
          <div class="h-3 w-3 rounded-sm bg-teal-200" />
          <div class="h-3 w-3 rounded-sm bg-teal-400" />
          <div class="h-3 w-3 rounded-sm bg-teal-700" />
          <span>Banyak</span>
        </div>
      </div>
      <!-- Per kategori -->
      <p class="label mb-3">Per Kategori</p>
      <div class="space-y-2">
        <div v-for="cat in learningStats.categories" :key="cat.category" class="flex items-center gap-3">
          <span class="w-24 shrink-0 text-xs font-extrabold capitalize text-slate-600 dark:text-zinc-400">{{ cat.category }}</span>
          <div class="flex-1 overflow-hidden rounded-full bg-slate-100 dark:bg-zinc-700">
            <div class="h-2.5 rounded-full bg-teal-600 transition-all"
              :style="{ width: `${Math.round((cat.total_minutes / learningStats.totals.minutes) * 100)}%` }" />
          </div>
          <span class="w-16 shrink-0 text-right text-xs font-bold text-slate-600 dark:text-zinc-400">{{ minutes(cat.total_minutes) }}</span>
          <span class="w-16 shrink-0 text-right text-xs text-slate-400 dark:text-zinc-600">{{ cat.entries }} sesi</span>
        </div>
      </div>
      <!-- Trend bulanan -->
      <div v-if="learningStats.monthly.length" class="mt-5">
        <p class="label mb-3">Trend 6 Bulan</p>
        <div class="flex items-end gap-2 rounded-2xl bg-slate-50 p-3 dark:bg-zinc-800">
          <div v-for="m in learningStats.monthly" :key="m.month" class="flex flex-1 flex-col items-center gap-1">
            <div class="w-full rounded-t-lg bg-teal-600 transition-all"
              :style="{ height: `${Math.max(6, Math.round((m.total_minutes / Math.max(...learningStats.monthly.map((x: any) => x.total_minutes))) * 80))}px` }" />
            <span class="text-[10px] font-bold text-slate-500">{{ m.month.slice(5) }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
  <form class="card mb-4 overflow-hidden p-0" @submit.prevent="applyFilters()">
    <div class="flex flex-col gap-3 border-b border-slate-100 bg-slate-50/70 px-4 py-4 lg:flex-row lg:items-center lg:justify-between">
      <div>
        <h2 class="font-extrabold">Filters and views</h2>
        <p class="text-sm font-medium text-slate-500">{{ meta?.total ?? 0 }} records in this workspace</p>
      </div>
      <div class="inline-flex w-fit rounded-xl border border-slate-200 bg-white p-1">
        <button type="button" class="rounded-lg px-3 py-1.5 text-sm font-bold" :class="!compact ? 'bg-slate-900 text-white' : 'text-slate-500'" @click="compact = false">Detailed</button>
        <button type="button" class="rounded-lg px-3 py-1.5 text-sm font-bold" :class="compact ? 'bg-slate-900 text-white' : 'text-slate-500'" @click="compact = true">Compact</button>
      </div>
    </div>
    <div class="p-4">
    <div class="grid gap-3 md:grid-cols-6">
      <input v-model="filters.search" class="field md:col-span-2" placeholder="Search records" />
      <select v-if="type === 'tasks' || type === 'work-logs' || type === 'milestones'" v-model="filters.status" class="field"><option value="">Any status</option><option v-for="option in statusOptions" :key="option" :value="option">{{ option.replaceAll('_', ' ') }}</option></select>
      <select v-if="categoryOptions.length" v-model="filters.category" class="field"><option value="">Any category</option><option v-for="option in categoryOptions" :key="option" :value="option">{{ option }}</option></select>
      <select v-if="type === 'tasks' || type === 'work-logs'" v-model="filters.priority" class="field"><option value="">Any priority</option><option v-for="option in ['low', 'medium', 'high', 'urgent']" :key="option" :value="option">{{ option }}</option></select>
      <DatePicker v-model="filters.from" label="From date" placeholder="From date" />
      <DatePicker v-model="filters.to" label="To date" placeholder="To date" />
      <select v-model="filters.sort" class="field"><option v-for="option in sortOptions" :key="option.value" :value="option.value">Sort: {{ option.label }}</option></select>
      <select v-model="filters.direction" class="field"><option value="desc">Newest first</option><option value="asc">Oldest first</option></select>
    </div>
    <div class="mt-3 flex flex-wrap justify-between gap-2">
      <div class="flex flex-wrap gap-2">
        <span v-for="filter in activeFilters" :key="filter.key" class="pill pill-slate">
          {{ filter.label }}: {{ filter.value }}
          <button type="button" class="ml-1 text-slate-400 hover:text-red-600" :aria-label="`Clear ${filter.label}`" @click="clearFilter(filter.key)">x</button>
        </span>
      </div>
      <div class="flex gap-2"><button type="button" class="btn btn-muted" @click="clearFilters">Reset</button><button class="btn btn-primary">Apply</button></div>
    </div>
    <div class="mt-4 border-t border-slate-100 pt-4">
      <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex flex-wrap gap-2">
          <span v-for="view in savedViews" :key="view.id" class="inline-flex overflow-hidden rounded-lg border border-slate-200 bg-white text-sm font-semibold">
            <button type="button" class="px-3 py-2 text-slate-600 hover:text-teal-700" @click="applySavedView(view)">{{ view.name }}</button>
            <button type="button" class="border-l border-slate-200 px-2 text-slate-400 hover:bg-red-50 hover:text-red-700" :aria-label="`Delete ${view.name}`" @click="removeSavedView(view)">x</button>
          </span>
        </div>
        <div class="flex gap-2">
          <input v-model="viewName" class="field max-w-56" placeholder="Save view name" />
          <button type="button" class="btn btn-muted" :disabled="savingView" @click="saveCurrentView">{{ savingView ? 'Saving...' : 'Save view' }}</button>
        </div>
      </div>
    </div>
    </div>
  </form>
  <div v-if="loading" class="grid gap-3">
    <div v-for="item in 4" :key="item" class="skeleton h-28 rounded-2xl"></div>
  </div>
  <div v-else-if="rows.length === 0" class="card p-10 text-center">
    <template v-if="activeFilters.length">
      <div class="mx-auto mb-4 grid h-14 w-14 place-items-center rounded-2xl bg-slate-100 dark:bg-zinc-800">
        <svg class="h-6 w-6 text-slate-400" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" viewBox="0 0 24 24"><path d="m21 21-4.35-4.35M10.8 18a7.2 7.2 0 1 1 0-14.4 7.2 7.2 0 0 1 0 14.4Z"/></svg>
      </div>
      <h2 class="text-xl font-extrabold text-slate-900 dark:text-zinc-100">Tidak ada hasil</h2>
      <p class="mx-auto mt-2 max-w-md text-sm font-medium text-slate-500 dark:text-zinc-500">Filter aktif tidak menemukan record yang cocok. Coba ubah atau reset filter.</p>
      <button class="btn btn-muted mt-4" @click="clearFilters">Reset semua filter</button>
    </template>
    <template v-else>
      <div class="mx-auto mb-4 grid h-14 w-14 place-items-center rounded-2xl bg-teal-50 dark:bg-teal-900/20">
        <svg class="h-7 w-7 text-teal-700 dark:text-teal-400" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
      </div>
      <h2 class="text-xl font-extrabold text-slate-900 dark:text-zinc-100">{{ emptyState[0] }}</h2>
      <p class="mx-auto mt-2 max-w-md text-sm font-medium text-slate-500 dark:text-zinc-500">{{ emptyState[1] }}</p>
      <RouterLink class="btn btn-primary mt-4" :to="`/${type}/create`">Buat {{ config.singular }} pertama</RouterLink>
    </template>
  </div>
  <div v-else class="grid gap-3">
    <RouterLink v-for="row in rows" :key="row.id" :to="`/${type}/${row.id}`" class="card block transition hover:-translate-y-0.5 hover:border-teal-200 hover:shadow-md">
      <article :class="compact ? 'p-3' : 'p-4'">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
          <div>
            <h2 class="font-extrabold text-slate-900 dark:text-zinc-100">{{ row.title || row.topic }}</h2>
            <p class="text-sm font-medium" :class="isOverdue(row) ? 'text-red-600 dark:text-red-400' : 'text-slate-500 dark:text-zinc-500'">
              {{ formatDate(row.date || row.due_date || row.end_date) }}
              <span v-if="isOverdue(row)" class="ml-1 rounded bg-red-100 px-1.5 py-0.5 text-[10px] font-extrabold uppercase tracking-wide text-red-700 dark:bg-red-900/40 dark:text-red-400">Overdue</span>
            </p>
          </div>
          <button
            v-if="type === 'tasks'"
            class="pill transition hover:ring-2 hover:ring-teal-300 dark:hover:ring-teal-700"
            :class="[tone(row.status), updatingStatusId === row.id ? 'opacity-50' : '']"
            :title="`Click → ${statusCycle[row.status] ?? ''}`.replaceAll('_', ' ')"
            @click.prevent="cycleTaskStatus($event, row)"
          >{{ row.status?.replaceAll('_', ' ') }}</button>
          <span v-else class="pill" :class="tone(row.status || row.priority || row.category)">{{ row.status || row.category || minutes(row.duration_minutes || row.actual_duration) }}</span>
        </div>
        <p v-if="!compact && (row.project_name || row.project?.name)" class="mt-2 text-sm font-medium text-slate-500">{{ row.project_name || row.project?.name }}</p>
      </article>
    </RouterLink>
  </div>
  <div v-if="meta && meta.last_page > 1" class="mt-4 flex flex-wrap items-center justify-between gap-3">
    <p class="text-sm text-slate-500">Page {{ meta.current_page }} of {{ meta.last_page }}</p>
    <div class="flex gap-2">
      <button class="btn btn-muted" :disabled="meta.current_page <= 1" @click="applyFilters(meta.current_page - 1)">Previous</button>
      <button class="btn btn-muted" :disabled="meta.current_page >= meta.last_page" @click="applyFilters(meta.current_page + 1)">Next</button>
    </div>
  </div>
</template>
