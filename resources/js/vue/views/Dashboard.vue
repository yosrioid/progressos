<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { api, unwrap } from '../api';
import { formatDate, minutes } from '../format';

const data = ref<any>(null);
const report = ref<any>(null);
onMounted(async () => {
  data.value = await api.get('/api/v1/dashboard').then(unwrap);
  report.value = (await api.get('/api/v1/reports/weekly').then(unwrap)).report;
});
</script>

<template>
  <div v-if="!data" class="card p-8 text-center text-sm text-slate-500">Loading dashboard...</div>
  <template v-else>
    <div class="mb-5 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between"><div><p class="text-sm font-semibold text-teal-700">Today</p><h1 class="text-2xl font-semibold">Dashboard</h1></div><RouterLink class="btn btn-primary" to="/daily-progress">New progress</RouterLink></div>
    <section class="card mb-5 p-5"><div class="mb-4 flex items-center justify-between"><div><h2 class="text-lg font-semibold">Today Workspace</h2><p class="text-sm text-slate-500">{{ formatDate(data.today.date) }}</p></div></div><div class="grid grid-cols-2 gap-3 md:grid-cols-4">
      <div class="rounded-xl border bg-white p-4"><p class="label">Progress</p><p class="mt-2 text-2xl font-semibold text-teal-800">{{ data.summary.today_progress }}</p></div>
      <div class="rounded-xl border bg-white p-4"><p class="label">Open tasks</p><p class="mt-2 text-2xl font-semibold text-sky-800">{{ data.summary.open_tasks }}</p></div>
      <div class="rounded-xl border bg-white p-4"><p class="label">Blockers</p><p class="mt-2 text-2xl font-semibold text-rose-800">{{ data.summary.blockers }}</p></div>
      <div class="rounded-xl border bg-white p-4"><p class="label">Learning</p><p class="mt-2 text-2xl font-semibold">{{ minutes(data.summary.learning_minutes_this_week) }}</p></div>
    </div></section>
    <div class="grid gap-5 xl:grid-cols-3">
      <section class="card p-5 xl:col-span-1"><h2 class="mb-4 font-semibold">Weekly activity</h2><div class="flex h-44 items-end gap-1 rounded-xl bg-slate-50 p-3"><div v-for="item in data.weekly_activity" :key="item.date" class="flex flex-1 flex-col items-center gap-2"><div class="w-full rounded-t-md bg-gradient-to-t from-teal-700 to-sky-400" :style="{ height: `${Math.max(6, (item.work + item.learning + item.progress) * 24)}px` }" /><span class="text-[10px] text-slate-500">{{ item.date.slice(8) }}</span></div></div></section>
      <section class="card p-5"><h2 class="mb-4 font-semibold">Latest work</h2><div class="space-y-3"><RouterLink v-for="log in data.latest_work_logs" :key="log.id" :to="`/work-logs`" class="block rounded-lg border p-3 hover:bg-teal-50/40"><b>{{ log.title }}</b><p class="text-sm text-slate-500">{{ log.project_name }} · {{ minutes(log.actual_duration) }}</p></RouterLink></div></section>
      <section class="card p-5"><h2 class="mb-4 font-semibold">Projects</h2><div class="space-y-2"><RouterLink v-for="project in data.projects" :key="project.id" :to="`/projects/${project.id}`" class="block rounded-lg border p-3 hover:bg-teal-50/40"><b>{{ project.name }}</b><p class="text-sm text-slate-500">{{ project.open_tasks_count }} open tasks</p></RouterLink></div></section>
    </div>
    <div class="mt-5 grid gap-5 xl:grid-cols-3">
      <section class="card p-5 xl:col-span-2"><h2 class="mb-4 font-semibold">Monthly rhythm</h2><div class="grid grid-cols-7 gap-1 rounded-xl bg-slate-50 p-3"><div v-for="item in data.monthly_activity" :key="item.date" class="h-8 rounded-md" :class="(item.work + item.learning + item.progress) > 2 ? 'bg-teal-700' : (item.work + item.learning + item.progress) > 0 ? 'bg-teal-300' : 'bg-white border border-slate-200'" :title="item.date" /></div></section>
      <section class="card p-5"><h2 class="mb-4 font-semibold">Weekly review</h2><div v-if="report" class="space-y-3 text-sm"><div class="rounded-lg border p-3"><b>Work delta</b><p class="text-slate-500">{{ report.trends.completed_work_delta }} completed logs vs previous period</p></div><div class="rounded-lg border p-3"><b>Learning delta</b><p class="text-slate-500">{{ minutes(report.trends.learning_minutes_delta) }} vs previous period</p></div><RouterLink class="btn btn-primary w-full" to="/reports/weekly">Open report</RouterLink></div></section>
    </div>
  </template>
</template>
