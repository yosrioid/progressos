<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { api, unwrap } from '../api';
import { confirmAction, toast } from '../feedback';
import { formatDate, minutes } from '../format';
import { configs } from '../records';

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

watch(() => [props.type, route.fullPath], () => { syncFromRoute(); load(); loadSavedViews(); });
onMounted(() => { syncFromRoute(); load(); loadSavedViews(); });
</script>

<template>
  <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
    <div><h1 class="text-3xl font-extrabold tracking-tight">{{ title }}</h1><p class="mt-1 text-sm font-medium text-slate-500">Create, review, and maintain your records from one clean workspace.</p></div>
    <RouterLink class="btn btn-primary" :to="`/${type}/create`">New {{ config.singular }}</RouterLink>
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
      <input v-model="filters.from" class="field" type="date" />
      <input v-model="filters.to" class="field" type="date" />
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
    <div class="mx-auto mb-4 grid h-14 w-14 place-items-center rounded-2xl bg-teal-50 text-2xl font-extrabold text-teal-700">+</div>
    <h2 class="text-xl font-extrabold text-slate-900">{{ emptyState[0] }}</h2>
    <p class="mx-auto mt-2 max-w-md text-sm font-medium text-slate-500">{{ emptyState[1] }}</p>
    <RouterLink class="btn btn-primary mt-4" :to="`/${type}/create`">Create first record</RouterLink>
  </div>
  <div v-else class="grid gap-3">
    <RouterLink v-for="row in rows" :key="row.id" :to="`/${type}/${row.id}`" class="card block transition hover:-translate-y-0.5 hover:border-teal-200 hover:shadow-md">
      <article :class="compact ? 'p-3' : 'p-4'">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
          <div><h2 class="font-extrabold text-slate-900">{{ row.title || row.topic }}</h2><p class="text-sm font-medium text-slate-500">{{ formatDate(row.date || row.due_date || row.end_date) }}</p></div>
          <span class="pill" :class="tone(row.status || row.priority || row.category)">{{ row.status || row.category || minutes(row.duration_minutes || row.actual_duration) }}</span>
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
