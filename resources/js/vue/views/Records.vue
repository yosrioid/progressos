<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink } from 'vue-router';
import { api, unwrap } from '../api';
import { formatDate, minutes } from '../format';
import { configs } from '../records';

const props = defineProps<{ type: string }>();
const rows = ref<any[]>([]);
const loading = ref(true);
const endpoint = computed(() => `/api/v1/${props.type}`);
const config = computed(() => configs[props.type]);
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
  rows.value = (data[config.value.listKey] || {}).data || [];
  loading.value = false;
}

watch(() => props.type, load);
onMounted(load);
</script>

<template>
  <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
    <div><h1 class="text-2xl font-semibold">{{ title }}</h1><p class="mt-1 text-sm text-slate-500">Create, review, and maintain your records from one clean workspace.</p></div>
    <RouterLink class="btn btn-primary" :to="`/${type}/create`">New {{ config.singular }}</RouterLink>
  </div>
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
</template>
