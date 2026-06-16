<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { api, unwrap } from '../api';
import { confirmAction, toast } from '../feedback';
import { formatDate, minutes } from '../format';
import { configs } from '../records';

const props = defineProps<{ type: string; id: string }>();
const router = useRouter();
const config = computed(() => configs[props.type]);
const record = ref<any>(null);
const loading = ref(true);
const deleting = ref(false);
const referenceForm = ref({ label: '', url: '', type: 'link', notes: '' });
const referenceError = ref('');

const refTypeIcons: Record<string, string> = {
  link: 'M15 7h3a5 5 0 0 1 5 5 5 5 0 0 1-5 5h-3m-6 0H6a5 5 0 0 1-5-5 5 5 0 0 1 5-5h3M8 12h8',
  doc: 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zM14 2v6h6M16 13H8M16 17H8M10 9H8',
  ticket: 'M15 5v2M15 11v2M15 17v2M5 5h14a2 2 0 0 1 2 2v3a2 2 0 0 0 0 4v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-3a2 2 0 1 0 0-4V7a2 2 0 0 1 2-2z',
  pr: 'M18 21a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM6 3a3 3 0 1 0 0 6 3 3 0 0 0 0-6zm12 6c0 3-6 4.5-6 9M6 9v12',
  article: 'M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2zm20 0h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z',
  course: 'M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5',
  other: 'M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z',
};

const refTypeBadge: Record<string, string> = {
  link: 'bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-400',
  doc: 'bg-slate-100 text-slate-600 dark:bg-zinc-800 dark:text-zinc-400',
  ticket: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
  pr: 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400',
  article: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
  course: 'bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-400',
  other: 'bg-slate-100 text-slate-500 dark:bg-zinc-800 dark:text-zinc-500',
};

function refDomain(url: string): string | null {
  try {
    return new URL(url).hostname.replace('www.', '');
  } catch {
    return null;
  }
}

function safeUrl(url: string): string | null {
  try {
    const parsed = new URL(url);
    return ['http:', 'https:'].includes(parsed.protocol) ? url : null;
  } catch {
    return null;
  }
}

const refsByType = computed(() => {
  const refs = record.value?.references || [];
  const groups: Record<string, any[]> = {};
  for (const ref of refs) {
    const t = ref.type || 'other';
    if (!groups[t]) groups[t] = [];
    groups[t].push(ref);
  }
  return groups;
});

const milestonePace = computed(() => {
  if (props.type !== 'milestones' || !record.value) return null;
  const m = record.value;
  const now = new Date();
  const start = m.start_date ? new Date(m.start_date + 'T00:00:00') : null;
  const end = m.end_date ? new Date(m.end_date + 'T00:00:00') : null;
  const current = Number(m.current_value || 0);
  const target = Number(m.target_value || 1);
  const remaining = target - current;
  if (remaining <= 0) return { label: 'Done!', tone: 'green' };
  if (!start) return null;
  const elapsedDays = Math.max(1, Math.floor((now.getTime() - start.getTime()) / 86400000));
  const dailyRate = current / elapsedDays;
  if (dailyRate <= 0) return end ? { label: 'No progress yet', tone: 'slate' } : null;
  const daysToFinish = Math.ceil(remaining / dailyRate);
  const eta = new Date(now.getTime() + daysToFinish * 86400000)
    .toLocaleDateString('en-US', { day: 'numeric', month: 'short', year: 'numeric' });
  if (end) {
    const daysLeft = Math.floor((end.getTime() - now.getTime()) / 86400000);
    if (daysToFinish <= daysLeft) return { label: `On track · ETA ${eta}`, tone: 'green' };
    const over = daysToFinish - daysLeft;
    return { label: `Behind · ~${over} days late`, tone: 'amber' };
  }
  return { label: `~${daysToFinish} days left at this pace`, tone: 'slate' };
});

const learningEntries = ref<any[]>([]);
const learningMeta = ref<any>(null);
const learningPage = ref(1);
const loadingLearning = ref(false);
const contributingMilestones = ref<any[]>([]);

const title = computed(() => record.value?.[config.value.titleKey] || config.value.singular);
const progressPercent = computed(() => {
  const row = record.value || {};
  return row.progress_percent ?? Math.min(100, Math.round((Number(row.current_value || 0) / Number(row.target_value || 1)) * 100));
});
const meta = computed(() => {
  const row = record.value || {};
  return [
    row.date || row.due_date || row.start_date ? formatDate(row.date || row.due_date || row.start_date) : null,
    row.project_name || row.project?.name || row.category || null,
    row.status || null,
  ].filter(Boolean).join(' / ');
});
const sideMeta = computed(() => {
  const row = record.value || {};
  return [
    ['Date', row.date || row.due_date || row.start_date ? formatDate(row.date || row.due_date || row.start_date) : null],
    ['Status', row.status],
    ['Category', row.category],
    ['Priority', row.priority],
    ['Project', row.project_name || row.project?.name],
    ['Duration', row.actual_duration || row.duration_minutes ? minutes(row.actual_duration || row.duration_minutes) : null],
  ].filter(([, value]) => value);
});

function visibleEntries(row: any) {
  return Object.entries(row || {}).filter(([key, value]) => {
    if (['id', 'user_id', 'project_id', 'deleted_at', 'created_at', 'updated_at', 'references'].includes(key)) return false;
    if (value === null || value === '' || value === undefined) return false;
    if (Array.isArray(value) && value.length === 0) return false;
    if (typeof value === 'object' && !Array.isArray(value)) return false;
    return true;
  });
}

function referenceType() {
  return ({ 'daily-progress': 'daily_progress', 'work-logs': 'work_log', tasks: 'task', learning: 'learning', milestones: 'milestone' } as any)[props.type];
}

function renderValue(key: string, value: any) {
  if (Array.isArray(value)) return value.map((item) => item.name || item).join(', ');
  if (String(key).includes('duration') || key === 'duration_minutes') return minutes(value);
  if (String(key).includes('date')) return formatDate(value);
  return String(value).replaceAll('_', ' ');
}

function tone(value?: string) {
  if (['done', 'completed', 'active'].includes(value || '')) return 'pill-green';
  if (['in_progress', 'medium', 'feature', 'programming'].includes(value || '')) return 'pill-blue';
  if (['blocked', 'urgent', 'high', 'cancelled'].includes(value || '')) return 'pill-red';
  return 'pill-slate';
}

function linkParts(text: string) {
  const parts: Array<{ text: string; href?: string }> = [];
  const pattern = /\[([^\]]+)\]\((https?:\/\/[^)]+)\)|(https?:\/\/\S+)/g;
  let cursor = 0;
  let match;
  while ((match = pattern.exec(text)) !== null) {
    if (match.index > cursor) parts.push({ text: text.slice(cursor, match.index) });
    parts.push({ text: match[1] || match[3], href: match[2] || match[3] });
    cursor = match.index + match[0].length;
  }
  if (cursor < text.length) parts.push({ text: text.slice(cursor) });
  return parts;
}

async function load() {
  loading.value = true;
  const data = await api.get(`${config.value.endpoint}/${props.id}`).then(unwrap);
  const prev = record.value;
  record.value = data[config.value.payloadKey];
  if (props.type === 'learning') {
    contributingMilestones.value = data.contributing_milestones || [];
  }
  if (props.type === 'milestones' && record.value?.completed_at && !prev?.completed_at) {
    toast({ tone: 'success', title: 'Milestone reached! 🎉', message: `"${record.value.title}" is now 100%.` });
  }
  loading.value = false;
  if (props.type === 'milestones' && record.value?.source_type !== 'manual') {
    loadHistory();
  }
}

async function loadHistory() {
  loadingLearning.value = true;
  const data = await api.get(`/api/v1/milestones/${props.id}/history`, { params: { page: learningPage.value, per_page: 20, sort: historySort.value, direction: 'desc' } }).then(unwrap);
  learningEntries.value = data.items?.data || [];
  learningMeta.value = data.items;
  loadingLearning.value = false;
}

const historySort = computed(() => {
  const src = record.value?.source_type;
  if (src === 'task_done_count') return 'created_at';
  return 'date';
});

function historyLabel(item: any, src: string) {
  if (src === 'learning_minutes') return { main: item.topic, sub: `${item.category} · ${item.source_type?.replaceAll('_', ' ')}`, value: minutes(item.duration_minutes), date: item.date, path: `/learning/${item.id}` };
  if (src === 'work_log_count' || src === 'work_log_minutes') return { main: item.title, sub: `${item.project_name}${item.category ? ' · ' + item.category : ''}`, value: minutes(item.actual_duration), date: item.date, path: `/work-logs/${item.id}` };
  if (src === 'task_done_count') return { main: item.title, sub: `priority: ${item.priority}`, value: 'done', date: item.completed_at || item.due_date || item.created_at, path: `/tasks/${item.id}` };
  if (src === 'daily_progress_streak') return { main: item.title || formatDate(item.date), sub: item.mood ? `mood: ${item.mood}` : '', value: '', date: item.date, path: `/daily-progress/${item.id}` };
  return { main: '', sub: '', value: '', date: '', path: '/' };
}

function historySummary(src: string, currentValue: number) {
  if (src === 'learning_minutes') return `${learningMeta.value?.total ?? 0} entries · ${minutes(currentValue)}`;
  if (src === 'work_log_count') return `${learningMeta.value?.total ?? 0} work logs`;
  if (src === 'work_log_minutes') return `${learningMeta.value?.total ?? 0} logs · ${minutes(currentValue)} total`;
  if (src === 'task_done_count') return `${learningMeta.value?.total ?? 0} tasks done`;
  if (src === 'daily_progress_streak') return `${learningMeta.value?.total ?? 0} daily entries`;
  return '';
}

async function destroy() {
  const confirmed = await confirmAction({
    title: `Delete ${config.value.singular.toLowerCase()}?`,
    message: 'This record will be moved out of your active workspace. This action cannot be undone from this screen.',
    confirmLabel: 'Delete',
    danger: true,
  });
  if (!confirmed) return;
  deleting.value = true;
  await api.delete(`${config.value.endpoint}/${props.id}`);
  toast({ tone: 'success', title: `${config.value.singular} deleted`, message: title.value });
  await router.push(`/${props.type}`);
}

async function addReference() {
  referenceError.value = '';
  try {
    await api.post('/api/v1/references', {
      referenceable_type: referenceType(),
      referenceable_id: props.id,
      ...referenceForm.value,
    });
    toast({ tone: 'success', title: 'Reference added', message: referenceForm.value.label });
    referenceForm.value = { label: '', url: '', type: 'link', notes: '' };
    await load();
  } catch (e: any) {
    referenceError.value = e.response?.data?.message || 'Could not add reference.';
  }
}

async function removeReference(reference: any) {
  const confirmed = await confirmAction({
    title: 'Remove reference?',
    message: `Remove "${reference.label}" from this record.`,
    confirmLabel: 'Remove',
    danger: true,
  });
  if (!confirmed) return;
  await api.delete(`/api/v1/references/${reference.id}`);
  toast({ tone: 'success', title: 'Reference removed', message: reference.label });
  await load();
}

onMounted(load);
</script>

<template>
  <div class="mb-5">
    <RouterLink class="inline-flex text-sm font-extrabold text-teal-700 hover:underline" :to="`/${type}`">Back to {{ config.singular }}</RouterLink>
    <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
      <div>
        <h1 class="text-3xl font-extrabold tracking-tight">{{ title }}</h1>
        <p v-if="meta" class="mt-1 text-sm font-medium text-slate-500">{{ meta }}</p>
      </div>
      <div class="flex gap-2">
        <RouterLink class="btn btn-muted" :to="`/${type}/${id}/edit`">Edit</RouterLink>
        <button class="btn border-red-200 bg-red-50 text-red-700 hover:bg-red-100" :disabled="deleting" @click="destroy">{{ deleting ? 'Deleting...' : 'Delete' }}</button>
      </div>
    </div>
  </div>

  <div v-if="loading" class="grid gap-4 lg:grid-cols-[1fr_20rem]">
    <div class="skeleton h-96 rounded-2xl"></div>
    <div class="skeleton h-72 rounded-2xl"></div>
  </div>
  <div v-else class="grid gap-5 lg:grid-cols-[1fr_20rem]">
  <section class="card overflow-hidden p-0">
    <div class="border-b border-slate-100 bg-gradient-to-r from-teal-50 via-white to-sky-50 px-5 py-4">
      <h2 class="font-extrabold">Record workspace</h2>
      <p class="text-sm font-medium text-slate-500">Readable context, links, and structured metadata for this entry.</p>
    </div>
    <div class="p-5">
    <div v-if="type === 'daily-progress'" class="mb-5 grid gap-3 md:grid-cols-2">
      <div v-for="field in ['completed_items', 'in_progress', 'todo', 'blockers']" :key="field" class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
        <p class="label mb-2">{{ field.replaceAll('_', ' ') }}</p>
        <p class="whitespace-pre-wrap text-sm leading-6 text-slate-700">{{ Array.isArray(record[field]) ? record[field].join('\n') : (record[field] || '-') }}</p>
      </div>
    </div>
    <div v-if="type === 'work-logs' && record?.task" class="mb-3 flex items-center gap-3 rounded-2xl border border-sky-100 bg-sky-50/60 px-4 py-3 dark:border-sky-800/30 dark:bg-sky-900/10">
      <svg class="h-4 w-4 shrink-0 text-sky-600" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="m9 11 3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
      <p class="text-sm font-semibold text-slate-700 dark:text-zinc-300">Linked task:</p>
      <button class="text-sm font-extrabold text-sky-700 hover:underline dark:text-sky-400" @click="router.push(`/tasks/${record.task.id}`)">{{ record.task.title }}</button>
    </div>
    <div v-if="type === 'work-logs'" class="mb-5 grid gap-3 md:grid-cols-4">
      <div class="rounded-2xl border bg-slate-50 p-4"><p class="label">Project</p><p class="mt-1 font-semibold">{{ record.project_name }}</p></div>
      <div class="rounded-2xl border bg-slate-50 p-4"><p class="label">Ticket</p><p class="mt-1 font-semibold">{{ record.ticket_code || '-' }}</p></div>
      <div class="rounded-2xl border bg-slate-50 p-4"><p class="label">Actual</p><p class="mt-1 font-semibold">{{ minutes(record.actual_duration) }}</p></div>
      <div class="rounded-2xl border bg-slate-50 p-4"><p class="label">Priority</p><p class="mt-1 font-semibold">{{ record.priority }}</p></div>
    </div>
    <div v-if="type === 'learning'" class="mb-5 grid gap-3 md:grid-cols-3">
      <div class="rounded-2xl border bg-slate-50 p-4 dark:border-zinc-700 dark:bg-zinc-800"><p class="label">Category</p><p class="mt-1 font-semibold">{{ record.category }}</p></div>
      <div class="rounded-2xl border bg-slate-50 p-4 dark:border-zinc-700 dark:bg-zinc-800"><p class="label">Source</p><p class="mt-1 font-semibold">{{ record.source_type }}</p></div>
      <div class="rounded-2xl border bg-slate-50 p-4 dark:border-zinc-700 dark:bg-zinc-800"><p class="label">Rating</p><p class="mt-1 font-semibold">{{ record.rating || '-' }}</p></div>
    </div>
    <div v-if="type === 'learning'" class="mb-5">
      <p class="label mb-2">Contributing to Milestone</p>
      <div v-if="!contributingMilestones.length" class="rounded-2xl border border-dashed border-slate-200 px-4 py-3 text-sm text-slate-400 dark:border-zinc-700 dark:text-zinc-600">
        No active milestone is tracking learning minutes for this entry.
      </div>
      <div v-else class="space-y-2">
        <button
          v-for="m in contributingMilestones"
          :key="m.id"
          class="block w-full rounded-2xl border border-teal-100 bg-teal-50/60 p-3 text-left hover:bg-teal-50 dark:border-teal-700/40 dark:bg-teal-900/20 dark:hover:bg-teal-900/30"
          @click="router.push(`/milestones/${m.id}`)"
        >
          <div class="mb-1.5 flex items-center justify-between gap-3">
            <span class="text-sm font-extrabold text-slate-800 dark:text-zinc-200">{{ m.title }}</span>
            <span class="shrink-0 text-xs font-bold text-teal-700 dark:text-teal-400">{{ m.progress_percent }}%</span>
          </div>
          <div class="h-1.5 overflow-hidden rounded-full bg-teal-100 dark:bg-teal-900/50">
            <div class="h-full rounded-full bg-teal-600" :style="{ width: `${m.progress_percent}%` }" />
          </div>
          <p class="mt-1 text-xs text-slate-500 dark:text-zinc-500">{{ m.current_value }} / {{ m.target_value }} min</p>
        </button>
      </div>
    </div>
    <div v-if="type === 'milestones'" class="mb-5 rounded-2xl border border-teal-100 bg-teal-50/50 p-4 dark:border-teal-700/40 dark:bg-teal-900/20">
      <div class="mb-2 flex items-center justify-between text-sm font-semibold"><span>Progress</span><span>{{ progressPercent }}%</span></div>
      <div class="h-3 overflow-hidden rounded-full bg-white dark:bg-zinc-700"><div class="h-full rounded-full bg-teal-700 transition-all" :style="{ width: `${progressPercent}%` }" /></div>
      <div class="mt-2 flex items-center justify-between gap-3">
        <p class="text-sm text-slate-600 dark:text-zinc-400">{{ record.current_value }} / {{ record.target_value }} {{ record.target_type }}</p>
        <span v-if="milestonePace" class="text-xs font-bold"
          :class="milestonePace.tone === 'green' ? 'text-teal-700 dark:text-teal-400' : milestonePace.tone === 'amber' ? 'text-amber-600 dark:text-amber-400' : 'text-slate-500 dark:text-zinc-400'">
          {{ milestonePace.label }}
        </span>
      </div>
    </div>
    <div class="grid gap-4 md:grid-cols-2">
      <div v-for="[key, value] in visibleEntries(record)" :key="key" class="rounded-2xl border border-slate-200 bg-white p-4" :class="String(value).length > 120 ? 'md:col-span-2' : ''">
        <p class="label mb-2">{{ key.replaceAll('_', ' ') }}</p>
        <p class="whitespace-pre-wrap text-sm leading-6 text-slate-700">
          <template v-for="(part, index) in linkParts(renderValue(key, value))" :key="index">
            <a v-if="part.href" class="font-semibold text-teal-700 underline" :href="part.href" target="_blank" rel="noreferrer">{{ part.text }}</a>
            <span v-else>{{ part.text }}</span>
          </template>
        </p>
      </div>
    </div>
    </div>
  </section>
  <aside class="space-y-5">
    <section class="card p-5">
      <h2 class="mb-4 font-extrabold">At a glance</h2>
      <div class="space-y-3">
        <div v-for="[label, value] in sideMeta" :key="label" class="flex items-center justify-between gap-3 border-b border-slate-100 pb-3 last:border-0 last:pb-0 dark:border-zinc-700">
          <span class="text-xs font-extrabold uppercase text-slate-400">{{ label }}</span>
          <span v-if="label === 'Status' || label === 'Priority' || label === 'Category'" class="pill" :class="tone(String(value))">{{ value }}</span>
          <span v-else-if="label === 'Date' && type === 'tasks' && record?.status !== 'done' && record?.due_date && new Date(record.due_date + 'T00:00:00') < new Date(new Date().toDateString())" class="flex items-center gap-1.5 text-right text-sm font-bold text-red-600 dark:text-red-400">
            {{ value }}
            <span class="rounded bg-red-100 px-1.5 py-0.5 text-[10px] font-extrabold uppercase tracking-wide dark:bg-red-900/40">Overdue</span>
          </span>
          <span v-else class="text-right text-sm font-bold text-slate-700 dark:text-zinc-300">{{ value }}</span>
        </div>
      </div>
    </section>
    <section class="card p-5">
      <h2 class="mb-4 font-extrabold">Actions</h2>
      <div class="grid gap-2">
        <RouterLink class="btn btn-primary w-full" :to="`/${type}/${id}/edit`">Modify record</RouterLink>
        <button class="btn w-full border-red-200 bg-red-50 text-red-700 hover:bg-red-100" :disabled="deleting" @click="destroy">{{ deleting ? 'Deleting...' : 'Remove record' }}</button>
      </div>
    </section>
  </aside>
    <section v-if="type === 'milestones' && record?.source_type !== 'manual'" class="card p-5 lg:col-span-2">
      <div class="mb-4 flex items-center justify-between">
        <div>
          <h2 class="font-extrabold">History</h2>
          <p v-if="record.source_filter" class="mt-0.5 text-xs font-semibold text-slate-400">Filter: {{ record.source_filter }}</p>
        </div>
        <span class="text-sm font-semibold text-slate-500">{{ historySummary(record.source_type, Number(record.current_value)) }}</span>
      </div>
      <div v-if="loadingLearning" class="py-8 text-center text-sm text-slate-400">Loading…</div>
      <div v-else-if="!learningEntries.length" class="py-8 text-center text-sm text-slate-400">No matching data.</div>
      <div v-else class="divide-y divide-slate-100 dark:divide-zinc-700">
        <div v-for="item in learningEntries" :key="item.id" class="flex items-center justify-between gap-3 py-2.5">
          <div class="min-w-0 flex-1">
            <button class="truncate font-semibold text-teal-700 hover:underline dark:text-teal-400" @click="router.push(historyLabel(item, record.source_type).path)">
              {{ historyLabel(item, record.source_type).main }}
            </button>
            <p v-if="historyLabel(item, record.source_type).sub" class="text-xs text-slate-400 dark:text-zinc-500">{{ historyLabel(item, record.source_type).sub }}</p>
          </div>
          <span v-if="historyLabel(item, record.source_type).value" class="shrink-0 text-sm font-bold text-slate-700 dark:text-zinc-300">{{ historyLabel(item, record.source_type).value }}</span>
          <span class="shrink-0 text-xs text-slate-400 dark:text-zinc-500">{{ formatDate(historyLabel(item, record.source_type).date) }}</span>
        </div>
      </div>
      <div v-if="learningMeta && learningMeta.last_page > 1" class="mt-4 flex justify-center gap-2">
        <button v-for="p in learningMeta.last_page" :key="p" class="btn btn-muted px-3 py-1.5 text-sm" :class="{ 'bg-teal-700 text-white': p === learningPage }" @click="learningPage = p; loadHistory()">{{ p }}</button>
      </div>
    </section>
    <section class="card p-5 lg:col-span-2">
      <div class="mb-3 flex items-center justify-between"><h2 class="font-extrabold">References</h2><span class="text-sm font-semibold text-slate-500">{{ record.references?.length || 0 }} links</span></div>
      <div class="mb-4 space-y-4">
        <template v-for="(refs, refType) in refsByType" :key="String(refType)">
          <div>
            <div class="mb-2 flex items-center gap-1.5">
              <span :class="['inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-extrabold uppercase', refTypeBadge[String(refType)] || refTypeBadge.other]">
                <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" viewBox="0 0 24 24"><path :d="refTypeIcons[String(refType)] || refTypeIcons.other" /></svg>
                {{ String(refType) }}
              </span>
            </div>
            <div class="grid gap-2">
              <div v-for="reference in refs" :key="reference.id" class="flex flex-col gap-2 rounded-2xl border border-slate-200 bg-slate-50 p-3 dark:border-zinc-700 dark:bg-zinc-800/50 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                  <a v-if="safeUrl(reference.url)" class="font-extrabold text-teal-700 hover:underline dark:text-teal-400" :href="safeUrl(reference.url)!" target="_blank" rel="noreferrer noopener">{{ reference.label }}</a>
                  <span v-else class="font-semibold text-slate-400">{{ reference.label }}</span>
                  <div class="mt-1 flex items-center gap-2">
                    <span v-if="refDomain(reference.url)" class="rounded bg-slate-200 px-1.5 py-0.5 text-[10px] font-semibold text-slate-500 dark:bg-zinc-700 dark:text-zinc-400">{{ refDomain(reference.url) }}</span>
                    <p v-if="reference.notes" class="truncate text-xs text-slate-500 dark:text-zinc-500">{{ reference.notes }}</p>
                  </div>
                </div>
                <button class="btn btn-muted shrink-0" @click="removeReference(reference)">Remove</button>
              </div>
            </div>
          </div>
        </template>
        <p v-if="!record.references?.length" class="rounded-2xl border border-dashed border-slate-200 px-4 py-3 text-sm text-slate-400 dark:border-zinc-700 dark:text-zinc-600">No references yet. Add a link, document, or ticket below.</p>
      </div>
      <form class="grid gap-3 md:grid-cols-5" @submit.prevent="addReference">
        <input v-model="referenceForm.label" class="field" placeholder="Label" required />
        <input v-model="referenceForm.url" class="field md:col-span-2" placeholder="https://..." required />
        <select v-model="referenceForm.type" class="field"><option v-for="option in ['link', 'doc', 'ticket', 'pr', 'article', 'course', 'other']" :key="option" :value="option">{{ option }}</option></select>
        <button class="btn btn-primary">Add reference</button>
        <p v-if="referenceError" class="text-sm text-red-700 md:col-span-5">{{ referenceError }}</p>
      </form>
    </section>
  </div>
</template>
