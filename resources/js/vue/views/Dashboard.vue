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
  <div v-if="!data" class="card p-8 text-center text-sm font-semibold text-slate-500">Loading dashboard...</div>
  <template v-else>
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <p class="text-sm font-extrabold text-teal-700">Today · {{ formatDate(data.today.date) }}</p>
        <h1 class="mt-1 text-3xl font-extrabold tracking-tight">Dashboard</h1>
        <p class="mt-1 text-sm font-medium text-slate-500">A focused overview of progress, work, blockers, and learning momentum.</p>
      </div>
      <RouterLink class="btn btn-primary" to="/daily-progress">New progress</RouterLink>
    </div>
    <section class="mb-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_18px_50px_rgb(15_23_42/0.055)]">
      <div class="border-b border-slate-100 bg-gradient-to-r from-teal-50 via-white to-sky-50 px-5 py-4">
        <h2 class="text-lg font-extrabold">Today Workspace</h2>
        <p class="text-sm font-medium text-slate-500">Key signals for the current workday.</p>
      </div>
      <div class="grid grid-cols-2 gap-3 p-4 md:grid-cols-4">
        <div class="rounded-2xl border border-teal-100 bg-teal-50/70 p-4"><p class="label">Progress</p><p class="mt-3 text-3xl font-extrabold text-teal-800">{{ data.summary.today_progress }}</p><p class="mt-1 text-xs font-semibold text-teal-700">daily entries</p></div>
        <div class="rounded-2xl border border-sky-100 bg-sky-50/70 p-4"><p class="label">Open tasks</p><p class="mt-3 text-3xl font-extrabold text-sky-800">{{ data.summary.open_tasks }}</p><p class="mt-1 text-xs font-semibold text-sky-700">needs attention</p></div>
        <div class="rounded-2xl border border-rose-100 bg-rose-50/70 p-4"><p class="label">Blockers</p><p class="mt-3 text-3xl font-extrabold text-rose-800">{{ data.summary.blockers }}</p><p class="mt-1 text-xs font-semibold text-rose-700">active friction</p></div>
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4"><p class="label">Learning</p><p class="mt-3 text-3xl font-extrabold text-slate-900">{{ minutes(data.summary.learning_minutes_this_week) }}</p><p class="mt-1 text-xs font-semibold text-slate-500">this week</p></div>
      </div>
    </section>
    <div class="grid gap-5 xl:grid-cols-3">
      <section class="card p-5 xl:col-span-1"><div class="mb-4 flex items-center justify-between"><h2 class="font-extrabold">Weekly activity</h2><span class="rounded-full bg-teal-50 px-2.5 py-1 text-xs font-bold text-teal-700">7 days</span></div><div class="flex h-44 items-end gap-1.5 rounded-2xl bg-slate-50 p-3"><div v-for="item in data.weekly_activity" :key="item.date" class="flex flex-1 flex-col items-center gap-2"><div class="w-full rounded-t-lg bg-gradient-to-t from-teal-700 to-sky-400 shadow-sm" :style="{ height: `${Math.max(6, (item.work + item.learning + item.progress) * 24)}px` }" /><span class="text-[10px] font-bold text-slate-500">{{ item.date.slice(8) }}</span></div></div></section>
      <section class="card p-5"><h2 class="mb-4 font-extrabold">Latest work</h2><div class="space-y-3"><RouterLink v-for="log in data.latest_work_logs" :key="log.id" :to="`/work-logs`" class="block rounded-2xl border border-slate-200 p-3.5 transition hover:-translate-y-0.5 hover:border-teal-200 hover:bg-teal-50/40"><b class="text-slate-900">{{ log.title }}</b><p class="mt-1 text-sm font-medium text-slate-500">{{ log.project_name }} · {{ minutes(log.actual_duration) }}</p></RouterLink></div></section>
      <section class="card p-5"><h2 class="mb-4 font-extrabold">Projects</h2><div class="space-y-2"><RouterLink v-for="project in data.projects" :key="project.id" :to="`/projects/${project.id}`" class="block rounded-2xl border border-slate-200 p-3.5 transition hover:-translate-y-0.5 hover:border-teal-200 hover:bg-teal-50/40"><b class="text-slate-900">{{ project.name }}</b><p class="mt-1 text-sm font-medium text-slate-500">{{ project.open_tasks_count }} open tasks</p></RouterLink></div></section>
    </div>
    <div class="mt-5 grid gap-5 xl:grid-cols-3">
      <section class="card p-5 xl:col-span-2">
        <div class="mb-4 flex items-center justify-between"><h2 class="font-extrabold">Today focus</h2><RouterLink class="text-sm font-extrabold text-teal-700 hover:underline" to="/tasks">Open tasks</RouterLink></div>
        <div class="grid gap-3 md:grid-cols-2">
          <RouterLink v-for="task in data.today.tasks" :key="task.id" :to="`/tasks/${task.id}`" class="rounded-2xl border border-slate-200 bg-slate-50 p-3.5 transition hover:border-teal-200 hover:bg-teal-50">
            <p class="font-extrabold text-slate-900">{{ task.title }}</p>
            <p class="mt-1 text-sm font-medium text-slate-500">{{ task.project?.name || 'No project' }} · {{ task.status?.replaceAll('_', ' ') }}</p>
          </RouterLink>
          <div v-if="data.today.tasks.length === 0" class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center text-sm font-semibold text-slate-500 md:col-span-2">No urgent tasks for today.</div>
        </div>
      </section>
      <section class="card p-5">
        <h2 class="mb-4 font-extrabold">Streaks</h2>
        <div class="grid gap-3">
          <div class="rounded-2xl border border-teal-100 bg-teal-50 p-4"><p class="label">Daily progress</p><p class="mt-2 text-3xl font-extrabold text-teal-800">{{ data.streaks.daily_progress }}</p><p class="text-xs font-bold text-teal-700">days</p></div>
          <div class="rounded-2xl border border-sky-100 bg-sky-50 p-4"><p class="label">Learning</p><p class="mt-2 text-3xl font-extrabold text-sky-800">{{ data.streaks.learning }}</p><p class="text-xs font-bold text-sky-700">days</p></div>
        </div>
      </section>
    </div>
    <div class="mt-5 grid gap-5 xl:grid-cols-3">
      <section class="card p-5 xl:col-span-2"><div class="mb-4 flex items-center justify-between"><h2 class="font-extrabold">Monthly rhythm</h2><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-500">heatmap</span></div><div class="grid grid-cols-7 gap-1 rounded-2xl bg-slate-50 p-3"><div v-for="item in data.monthly_activity" :key="item.date" class="h-8 rounded-lg transition hover:ring-2 hover:ring-teal-200" :class="(item.work + item.learning + item.progress) > 2 ? 'bg-teal-700' : (item.work + item.learning + item.progress) > 0 ? 'bg-teal-300' : 'bg-white border border-slate-200'" :title="item.date" /></div></section>
      <section class="card p-5"><h2 class="mb-4 font-extrabold">Weekly review</h2><div v-if="report" class="space-y-3 text-sm"><div class="rounded-2xl border border-slate-200 bg-slate-50 p-3.5"><b>Work delta</b><p class="mt-1 text-slate-500">{{ report.trends.completed_work_delta }} completed logs vs previous period</p></div><div class="rounded-2xl border border-slate-200 bg-slate-50 p-3.5"><b>Learning delta</b><p class="mt-1 text-slate-500">{{ minutes(report.trends.learning_minutes_delta) }} vs previous period</p></div><RouterLink class="btn btn-primary w-full" to="/reports/weekly">Open report</RouterLink></div></section>
    </div>
    <section class="card mt-5 p-5">
      <div class="mb-4 flex items-center justify-between"><h2 class="font-extrabold">Milestone progress</h2><RouterLink class="text-sm font-extrabold text-teal-700 hover:underline" to="/milestones">View all</RouterLink></div>
      <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        <RouterLink v-for="milestone in data.milestones" :key="milestone.id" :to="`/milestones/${milestone.id}`" class="rounded-2xl border border-slate-200 bg-white p-4 transition hover:border-teal-200 hover:bg-teal-50/40">
          <div class="flex items-start justify-between gap-3"><b class="text-slate-900">{{ milestone.title }}</b><span class="pill" :class="milestone.overdue ? 'pill-red' : 'pill-green'">{{ milestone.status }}</span></div>
          <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-teal-700" :style="{ width: `${milestone.progress_percent}%` }" /></div>
          <p class="mt-2 text-sm font-semibold text-slate-500">{{ milestone.progress_percent }}% complete</p>
        </RouterLink>
      </div>
    </section>
    <section class="card mt-5 p-5">
      <div class="mb-4 flex items-center justify-between"><h2 class="font-extrabold">Recent activity</h2><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-500">audit trail</span></div>
      <div class="grid gap-2">
        <div v-for="item in data.recent_activity" :key="item.id" class="flex items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-3">
          <div>
            <p class="font-extrabold text-slate-900">{{ item.label }}</p>
            <p class="text-sm font-medium text-slate-500">{{ item.record_type }} #{{ item.record_id }}</p>
          </div>
          <span class="text-xs font-bold text-slate-400">{{ formatDate(item.created_at) }}</span>
        </div>
        <div v-if="data.recent_activity.length === 0" class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center text-sm font-semibold text-slate-500">No activity yet.</div>
      </div>
    </section>
  </template>
</template>
