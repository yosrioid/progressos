<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { api, unwrap } from '../api';
import { formatDate, minutes } from '../format';
import { configs } from '../records';

const props = defineProps<{ type: string; id: string }>();
const router = useRouter();
const config = computed(() => configs[props.type]);
const record = ref<any>(null);
const loading = ref(true);
const deleting = ref(false);

const title = computed(() => record.value?.[config.value.titleKey] || config.value.singular);
const meta = computed(() => {
  const row = record.value || {};
  return [
    row.date || row.due_date || row.start_date ? formatDate(row.date || row.due_date || row.start_date) : null,
    row.project_name || row.project?.name || row.category || null,
    row.status || null,
  ].filter(Boolean).join(' / ');
});

function visibleEntries(row: any) {
  return Object.entries(row || {}).filter(([key, value]) => {
    if (['id', 'user_id', 'project_id', 'deleted_at', 'created_at', 'updated_at'].includes(key)) return false;
    if (value === null || value === '' || value === undefined) return false;
    if (Array.isArray(value) && value.length === 0) return false;
    if (typeof value === 'object' && !Array.isArray(value)) return false;
    return true;
  });
}

function renderValue(key: string, value: any) {
  if (Array.isArray(value)) return value.map((item) => item.name || item).join(', ');
  if (String(key).includes('duration') || key === 'duration_minutes') return minutes(value);
  if (String(key).includes('date')) return formatDate(value);
  return String(value).replaceAll('_', ' ');
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
  if (!confirm(`Delete this ${config.value.singular.toLowerCase()}?`)) return;
  deleting.value = true;
  await api.delete(`${config.value.endpoint}/${props.id}`);
  await router.push(`/${props.type}`);
}

onMounted(load);
</script>

<template>
  <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
    <div>
      <RouterLink class="text-sm font-semibold text-teal-700" :to="`/${type}`">Back to {{ config.singular }}</RouterLink>
      <h1 class="mt-1 text-2xl font-semibold">{{ title }}</h1>
      <p v-if="meta" class="mt-1 text-sm text-slate-500">{{ meta }}</p>
    </div>
    <div class="flex gap-2">
      <RouterLink class="btn btn-muted" :to="`/${type}/${id}/edit`">Edit</RouterLink>
      <button class="btn border-red-200 bg-red-50 text-red-700 hover:bg-red-100" :disabled="deleting" @click="destroy">{{ deleting ? 'Deleting...' : 'Delete' }}</button>
    </div>
  </div>

  <div v-if="loading" class="card p-8 text-center text-sm text-slate-500">Loading record...</div>
  <section v-else class="card p-5">
    <div v-if="type === 'milestones'" class="mb-5 rounded-xl border border-teal-100 bg-teal-50/50 p-4">
      <div class="mb-2 flex items-center justify-between text-sm font-semibold"><span>Progress</span><span>{{ record.progress_percent ?? Math.min(100, Math.round((Number(record.current_value || 0) / Number(record.target_value || 1)) * 100)) }}%</span></div>
      <div class="h-3 overflow-hidden rounded-full bg-white"><div class="h-full rounded-full bg-teal-700" :style="{ width: `${record.progress_percent ?? Math.min(100, Math.round((Number(record.current_value || 0) / Number(record.target_value || 1)) * 100))}%` }" /></div>
      <p class="mt-2 text-sm text-slate-600">{{ record.current_value }} / {{ record.target_value }} {{ record.target_type }}</p>
    </div>
    <div class="grid gap-4 md:grid-cols-2">
      <div v-for="[key, value] in visibleEntries(record)" :key="key" class="rounded-xl border border-slate-200 bg-white p-4" :class="String(value).length > 120 ? 'md:col-span-2' : ''">
        <p class="label mb-2">{{ key.replaceAll('_', ' ') }}</p>
        <p class="whitespace-pre-wrap text-sm leading-6 text-slate-700">
          <template v-for="(part, index) in linkParts(renderValue(key, value))" :key="index">
            <a v-if="part.href" class="font-semibold text-teal-700 underline" :href="part.href" target="_blank" rel="noreferrer">{{ part.text }}</a>
            <span v-else>{{ part.text }}</span>
          </template>
        </p>
      </div>
    </div>
  </section>
</template>
