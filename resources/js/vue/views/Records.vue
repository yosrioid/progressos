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
    <div><h1 class="text-2xl font-semibold">{{ title }}</h1><p class="mt-1 text-sm text-slate-500">Create, review, and maintain your records from one clean workspace.</p></div>
    <RouterLink class="btn btn-primary" :to="`/${type}/create`">New {{ config.singular }}</RouterLink>
  </div>
  <form class="card mb-4 p-4" @submit.prevent="applyFilters()">
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
      <p class="text-sm text-slate-500">{{ meta?.total ?? 0 }} records</p>
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
  </form>
  <div v-if="loading" class="card p-8 text-center text-sm text-slate-500">Loading...</div>
  <div v-else-if="rows.length === 0" class="card p-8 text-center text-sm text-slate-500">
    <p>No records yet.</p>
    <RouterLink class="btn btn-primary mt-4" :to="`/${type}/create`">Create first record</RouterLink>
  </div>
  <div v-else class="grid gap-3">
    <RouterLink v-for="row in rows" :key="row.id" :to="`/${type}/${row.id}`" class="card block transition hover:-translate-y-0.5 hover:border-teal-200 hover:shadow-md">
      <article class="p-4">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
          <div><h2 class="font-semibold">{{ row.title || row.topic }}</h2><p class="text-sm text-slate-500">{{ formatDate(row.date || row.due_date || row.end_date) }}</p></div>
          <span class="rounded-lg bg-slate-100 px-2 py-1 text-xs font-bold text-slate-600">{{ row.status || row.category || minutes(row.duration_minutes || row.actual_duration) }}</span>
        </div>
        <p v-if="row.project_name || row.project?.name" class="mt-2 text-sm text-slate-500">{{ row.project_name || row.project?.name }}</p>
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
