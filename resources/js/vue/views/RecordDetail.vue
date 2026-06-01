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
  record.value = data[config.value.payloadKey];
  loading.value = false;
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
    <div v-if="type === 'work-logs'" class="mb-5 grid gap-3 md:grid-cols-4">
      <div class="rounded-2xl border bg-slate-50 p-4"><p class="label">Project</p><p class="mt-1 font-semibold">{{ record.project_name }}</p></div>
      <div class="rounded-2xl border bg-slate-50 p-4"><p class="label">Ticket</p><p class="mt-1 font-semibold">{{ record.ticket_code || '-' }}</p></div>
      <div class="rounded-2xl border bg-slate-50 p-4"><p class="label">Actual</p><p class="mt-1 font-semibold">{{ minutes(record.actual_duration) }}</p></div>
      <div class="rounded-2xl border bg-slate-50 p-4"><p class="label">Priority</p><p class="mt-1 font-semibold">{{ record.priority }}</p></div>
    </div>
    <div v-if="type === 'learning'" class="mb-5 grid gap-3 md:grid-cols-3">
      <div class="rounded-2xl border bg-slate-50 p-4"><p class="label">Category</p><p class="mt-1 font-semibold">{{ record.category }}</p></div>
      <div class="rounded-2xl border bg-slate-50 p-4"><p class="label">Source</p><p class="mt-1 font-semibold">{{ record.source_type }}</p></div>
      <div class="rounded-2xl border bg-slate-50 p-4"><p class="label">Rating</p><p class="mt-1 font-semibold">{{ record.rating || '-' }}</p></div>
    </div>
    <div v-if="type === 'milestones'" class="mb-5 rounded-2xl border border-teal-100 bg-teal-50/50 p-4">
      <div class="mb-2 flex items-center justify-between text-sm font-semibold"><span>Progress</span><span>{{ progressPercent }}%</span></div>
      <div class="h-3 overflow-hidden rounded-full bg-white"><div class="h-full rounded-full bg-teal-700" :style="{ width: `${progressPercent}%` }" /></div>
      <p class="mt-2 text-sm text-slate-600">{{ record.current_value }} / {{ record.target_value }} {{ record.target_type }}</p>
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
        <div v-for="[label, value] in sideMeta" :key="label" class="flex items-center justify-between gap-3 border-b border-slate-100 pb-3 last:border-0 last:pb-0">
          <span class="text-xs font-extrabold uppercase text-slate-400">{{ label }}</span>
          <span v-if="label === 'Status' || label === 'Priority' || label === 'Category'" class="pill" :class="tone(String(value))">{{ value }}</span>
          <span v-else class="text-right text-sm font-bold text-slate-700">{{ value }}</span>
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
    <section class="card p-5 lg:col-span-2">
      <div class="mb-3 flex items-center justify-between"><h2 class="font-extrabold">References</h2><span class="text-sm font-semibold text-slate-500">{{ record.references?.length || 0 }} links</span></div>
      <div class="mb-4 grid gap-2">
        <div v-for="reference in record.references" :key="reference.id" class="flex flex-col gap-2 rounded-2xl border border-slate-200 bg-slate-50 p-3 sm:flex-row sm:items-center sm:justify-between">
          <div><a class="font-semibold text-teal-700 underline" :href="reference.url" target="_blank" rel="noreferrer">{{ reference.label }}</a><p class="text-xs text-slate-500">{{ reference.type }} · {{ reference.notes || reference.url }}</p></div>
          <button class="btn btn-muted" @click="removeReference(reference)">Remove</button>
        </div>
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
