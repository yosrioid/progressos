<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { api, unwrap } from '../api';
import { formatDate, minutes } from '../format';

const route = useRoute();
const router = useRouter();
const report = ref<any>(null);
const date = ref(String(route.query.date || ''));

async function load() {
  date.value = String(route.query.date || '');
  report.value = (await api.get(`/api/v1/reports/${route.params.period}`, { params: date.value ? { date: date.value } : {} }).then(unwrap)).report;
}

function applyDate() {
  router.push({ path: `/reports/${route.params.period}`, query: date.value ? { date: date.value } : {} });
}

function exportHref() {
  return `/api/v1/reports/${route.params.period}/export${date.value ? `?date=${encodeURIComponent(date.value)}` : ''}`;
}

function exportPdfHref() {
  return `/api/v1/reports/${route.params.period}/export-pdf${date.value ? `?date=${encodeURIComponent(date.value)}` : ''}`;
}

watch(() => route.fullPath, load);
onMounted(load);
</script>

<template>
  <div v-if="!report" class="card p-8 text-center text-sm text-slate-500">Loading report...</div>
  <template v-else>
    <div class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
      <div><p class="text-sm font-semibold text-teal-700">{{ formatDate(report.start) }} to {{ formatDate(report.end) }}</p><h1 class="text-2xl font-semibold capitalize">{{ report.period }} Report</h1></div>
      <div class="flex flex-wrap gap-2">
        <RouterLink class="btn btn-muted" to="/reports/weekly">Weekly</RouterLink>
        <RouterLink class="btn btn-muted" to="/reports/monthly">Monthly</RouterLink>
        <input v-model="date" class="field w-auto" type="date" />
        <button class="btn btn-primary" @click="applyDate">Apply</button>
        <a class="btn btn-muted" :href="exportHref()">Export CSV</a>
        <a class="btn btn-muted" :href="exportPdfHref()" target="_blank">Export PDF</a>
      </div>
    </div>
    <div class="mb-5 grid gap-3 md:grid-cols-3"><div class="card p-4"><p class="label">Completed work</p><p class="mt-2 text-2xl font-semibold">{{ report.completed_work_logs.length }}</p></div><div class="card p-4"><p class="label">Open blockers</p><p class="mt-2 text-2xl font-semibold text-rose-800">{{ report.open_blockers.length }}</p></div><div class="card p-4"><p class="label">Learning</p><p class="mt-2 text-2xl font-semibold">{{ minutes(report.learning_totals.minutes) }}</p></div></div>
    <div class="mb-5 grid gap-3 md:grid-cols-3">
      <div class="card p-4"><p class="label">Work trend</p><p class="mt-2 text-xl font-semibold" :class="report.trends.completed_work_delta >= 0 ? 'text-teal-800' : 'text-rose-800'">{{ report.trends.completed_work_delta >= 0 ? '+' : '' }}{{ report.trends.completed_work_delta }}</p></div>
      <div class="card p-4"><p class="label">Learning trend</p><p class="mt-2 text-xl font-semibold" :class="report.trends.learning_minutes_delta >= 0 ? 'text-teal-800' : 'text-rose-800'">{{ report.trends.learning_minutes_delta >= 0 ? '+' : '' }}{{ minutes(report.trends.learning_minutes_delta) }}</p></div>
      <div class="card p-4"><p class="label">Logged time trend</p><p class="mt-2 text-xl font-semibold" :class="report.trends.logged_minutes_delta >= 0 ? 'text-teal-800' : 'text-rose-800'">{{ report.trends.logged_minutes_delta >= 0 ? '+' : '' }}{{ minutes(report.trends.logged_minutes_delta) }}</p></div>
    </div>
    <div class="grid gap-5 xl:grid-cols-2">
      <section class="card p-5"><h2 class="mb-4 font-semibold">Key achievements</h2><ul class="grid gap-2"><li v-for="item in report.key_achievements" :key="item" class="rounded-lg border bg-teal-50/40 px-3 py-2 text-sm font-medium">{{ item }}</li><li v-if="report.key_achievements.length === 0" class="text-sm text-slate-500">No achievements captured for this period.</li></ul></section>
      <section class="card p-5"><h2 class="mb-4 font-semibold">Time by category</h2><div class="grid gap-3"><div v-for="(value, key) in report.time_by_category" :key="key"><div class="mb-1 flex justify-between text-sm"><b>{{ key }}</b><span>{{ minutes(value) }}</span></div><div class="h-2 rounded-full bg-slate-100"><div class="h-full rounded-full bg-teal-700" :style="{ width: `${Math.min(100, Number(value) / 6)}%` }" /></div></div></div></section>
      <section class="card p-5"><h2 class="mb-4 font-semibold">Most active projects</h2><div class="grid gap-2"><div v-for="(count, project) in report.most_active_projects" :key="project" class="rounded-lg border p-3 text-sm"><b>{{ project }}</b><p class="text-slate-500">{{ count }} logs</p></div><p v-if="Object.keys(report.most_active_projects).length === 0" class="text-sm text-slate-500">No project activity.</p></div></section>
      <section class="card p-5"><h2 class="mb-4 font-semibold">Open blockers</h2><div class="grid gap-2"><div v-for="blocker in report.open_blockers" :key="`${blocker.id}-${blocker.title}`" class="rounded-lg border p-3 text-sm"><b>{{ blocker.title }}</b><p class="text-slate-500">{{ blocker.project_name || blocker.priority }}</p></div><p v-if="report.open_blockers.length === 0" class="text-sm text-slate-500">No open blockers.</p></div></section>
    </div>
  </template>
</template>
