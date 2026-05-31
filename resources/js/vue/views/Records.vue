<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { api, unwrap } from '../api';
import { formatDate, minutes } from '../format';

const props = defineProps<{ type: string }>();
const rows = ref<any[]>([]);
const loading = ref(true);
const endpoint = computed(() => `/api/v1/${props.type}`);
const title = computed(() => ({
  'daily-progress': 'Daily Progress',
  'work-logs': 'Work Logs',
  tasks: 'Tasks',
  learning: 'Learning',
  milestones: 'Milestones',
} as any)[props.type] || props.type);

async function load() {
  loading.value = true;
  const data = await api.get(endpoint.value).then(unwrap);
  rows.value = (data.entries || data.logs || data.tasks || data.milestones || {}).data || [];
  loading.value = false;
}

watch(() => props.type, load);
onMounted(load);
</script>

<template>
  <div class="mb-5"><h1 class="text-2xl font-semibold">{{ title }}</h1><p class="mt-1 text-sm text-slate-500">Vue + Pinia UI reading from REST endpoints.</p></div>
  <div v-if="loading" class="card p-8 text-center text-sm text-slate-500">Loading...</div>
  <div v-else-if="rows.length === 0" class="card p-8 text-center text-sm text-slate-500">No records yet.</div>
  <div v-else class="grid gap-3">
    <article v-for="row in rows" :key="row.id" class="card p-4">
      <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
        <div><h2 class="font-semibold">{{ row.title || row.topic }}</h2><p class="text-sm text-slate-500">{{ formatDate(row.date || row.due_date || row.end_date) }}</p></div>
        <span class="rounded-lg bg-slate-100 px-2 py-1 text-xs font-bold text-slate-600">{{ row.status || row.category || minutes(row.duration_minutes || row.actual_duration) }}</span>
      </div>
      <p v-if="row.project_name || row.project?.name" class="mt-2 text-sm text-slate-500">{{ row.project_name || row.project?.name }}</p>
    </article>
  </div>
</template>
