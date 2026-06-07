<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { api, unwrap } from '../api';
import { formatDate, minutes } from '../format';

function milestonePaceLabel(m: any): { label: string; tone: string } | null {
  const now = new Date();
  const start = m.start_date ? new Date(m.start_date + 'T00:00:00') : null;
  const end = m.end_date ? new Date(m.end_date + 'T00:00:00') : null;
  const current = Number(m.current_value || 0);
  const target = Number(m.target_value || 1);
  const remaining = target - current;
  if (remaining <= 0) return { label: 'Selesai!', tone: 'green' };
  if (!start) return null;
  const elapsedDays = Math.max(1, Math.floor((now.getTime() - start.getTime()) / 86400000));
  const dailyRate = current / elapsedDays;
  if (dailyRate <= 0) return end ? { label: 'Belum ada progress', tone: 'slate' } : null;
  const daysToFinish = Math.ceil(remaining / dailyRate);
  if (end) {
    const daysLeft = Math.floor((end.getTime() - now.getTime()) / 86400000);
    if (daysToFinish <= daysLeft) return { label: `ETA ~${daysToFinish}h lagi`, tone: 'green' };
    return { label: `Behind ~${daysToFinish - daysLeft}h`, tone: 'amber' };
  }
  return { label: `~${daysToFinish} hari lagi`, tone: 'slate' };
}

const data = ref<any>(null);
const report = ref<any>(null);
const standup = ref<any>(null);
const standupOpen = ref(false);
const standupLoading = ref(false);
const standupCopied = ref(false);
const doneTaskIds = ref<Set<number>>(new Set());
const markingDoneId = ref<number | null>(null);

onMounted(async () => {
  data.value = await api.get('/api/v1/dashboard').then(unwrap);
  api.get('/api/v1/reports/weekly').then(unwrap).then((d) => { report.value = d.report; }).catch(() => {});
});

async function markTaskDone(e: Event, task: any) {
  e.stopPropagation();
  if (markingDoneId.value || doneTaskIds.value.has(task.id)) return;
  markingDoneId.value = task.id;
  doneTaskIds.value.add(task.id);
  try {
    await api.patch(`/api/v1/tasks/${task.id}/status`, { status: 'done' });
    task.status = 'done';
  } catch {
    doneTaskIds.value.delete(task.id);
  } finally {
    markingDoneId.value = null;
  }
}

async function openStandup() {
  standupOpen.value = true;
  if (!standup.value) {
    standupLoading.value = true;
    standup.value = await api.get('/api/v1/standup').then(r => r.data);
    standupLoading.value = false;
  }
}

async function copyStandup() {
  if (!standup.value?.text) return;
  await navigator.clipboard.writeText(standup.value.text);
  standupCopied.value = true;
  setTimeout(() => { standupCopied.value = false; }, 2000);
}
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
      <div class="flex gap-2">
        <button class="btn btn-muted" @click="openStandup">📋 Standup</button>
        <RouterLink class="btn btn-primary" to="/daily-progress">New progress</RouterLink>
      </div>
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
          <div
            v-for="task in data.today.tasks"
            :key="task.id"
            class="group flex items-start gap-3 rounded-2xl border p-3.5 transition"
            :class="doneTaskIds.has(task.id) || task.status === 'done' ? 'border-teal-200 bg-teal-50/60 opacity-60 dark:border-teal-800/40 dark:bg-teal-900/10' : 'border-slate-200 bg-slate-50 hover:border-teal-200 hover:bg-teal-50 dark:border-zinc-700 dark:bg-zinc-800/40 dark:hover:border-teal-700'"
          >
            <button
              class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2 transition"
              :class="doneTaskIds.has(task.id) || task.status === 'done' ? 'border-teal-500 bg-teal-500 text-white' : 'border-slate-300 hover:border-teal-400 dark:border-zinc-600'"
              :disabled="!!markingDoneId || doneTaskIds.has(task.id)"
              :title="task.status === 'done' ? 'Done' : 'Tandai selesai'"
              @click="markTaskDone($event, task)"
            >
              <svg v-if="doneTaskIds.has(task.id) || task.status === 'done'" class="h-3 w-3" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>
            </button>
            <RouterLink :to="`/tasks/${task.id}`" class="min-w-0 flex-1">
              <p class="font-extrabold text-slate-900 dark:text-zinc-100" :class="doneTaskIds.has(task.id) || task.status === 'done' ? 'line-through opacity-60' : ''">{{ task.title }}</p>
              <p class="mt-1 text-sm font-medium text-slate-500 dark:text-zinc-500">{{ task.project?.name || 'No project' }} · {{ task.status?.replaceAll('_', ' ') }}</p>
            </RouterLink>
          </div>
          <div v-if="data.today.tasks.length === 0" class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center text-sm font-semibold text-slate-500 md:col-span-2 dark:border-zinc-700 dark:bg-zinc-800/30 dark:text-zinc-500">Tidak ada task mendesak untuk hari ini.</div>
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
    <section v-if="data.focus.overdue_tasks.length || data.focus.next_actions.length || data.focus.behind_milestones.length" class="card mt-5 p-5">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="font-extrabold">Perlu Perhatian</h2>
        <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">action needed</span>
      </div>
      <div class="grid gap-4 md:grid-cols-3">
        <!-- Overdue tasks -->
        <div v-if="data.focus.overdue_tasks.length">
          <p class="label mb-2 text-red-600 dark:text-red-400">Overdue Tasks</p>
          <div class="space-y-2">
            <RouterLink v-for="t in data.focus.overdue_tasks" :key="t.id" :to="`/tasks/${t.id}`"
              class="block rounded-xl border border-red-100 bg-red-50/60 p-3 hover:bg-red-50 dark:border-red-800/30 dark:bg-red-900/10">
              <p class="text-sm font-extrabold text-slate-800 dark:text-zinc-200">{{ t.title }}</p>
              <p class="mt-0.5 text-xs font-semibold text-red-600 dark:text-red-400">Due {{ formatDate(t.due_date) }} · {{ t.priority }}</p>
            </RouterLink>
          </div>
        </div>
        <!-- Next actions from learning -->
        <div v-if="data.focus.next_actions.length">
          <p class="label mb-2 text-sky-700 dark:text-sky-400">Next Actions Belajar</p>
          <div class="space-y-2">
            <RouterLink v-for="e in data.focus.next_actions" :key="e.id" :to="`/learning/${e.id}`"
              class="block rounded-xl border border-sky-100 bg-sky-50/60 p-3 hover:bg-sky-50 dark:border-sky-800/30 dark:bg-sky-900/10">
              <p class="text-sm font-extrabold text-slate-800 dark:text-zinc-200">{{ e.topic }}</p>
              <p class="mt-0.5 text-xs text-slate-500 dark:text-zinc-400 line-clamp-2">{{ e.next_action }}</p>
            </RouterLink>
          </div>
        </div>
        <!-- Behind milestones -->
        <div v-if="data.focus.behind_milestones.length">
          <p class="label mb-2 text-amber-600 dark:text-amber-400">Milestone Behind Pace</p>
          <div class="space-y-2">
            <RouterLink v-for="m in data.focus.behind_milestones" :key="m.id" :to="`/milestones/${m.id}`"
              class="block rounded-xl border border-amber-100 bg-amber-50/60 p-3 hover:bg-amber-50 dark:border-amber-800/30 dark:bg-amber-900/10">
              <p class="text-sm font-extrabold text-slate-800 dark:text-zinc-200">{{ m.title }}</p>
              <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-amber-100 dark:bg-amber-900/40">
                <div class="h-full rounded-full bg-amber-500" :style="{ width: `${m.progress_percent}%` }" />
              </div>
              <p class="mt-1 text-xs font-semibold text-amber-700 dark:text-amber-400">{{ m.progress_percent }}% · deadline {{ formatDate(m.end_date) }}</p>
            </RouterLink>
          </div>
        </div>
      </div>
    </section>
    <div class="mt-5 grid gap-5 xl:grid-cols-3">
      <section class="card p-5 xl:col-span-2"><div class="mb-4 flex items-center justify-between"><h2 class="font-extrabold">Monthly rhythm</h2><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-500">heatmap</span></div><div class="grid grid-cols-7 gap-1 rounded-2xl bg-slate-50 p-3"><div v-for="item in data.monthly_activity" :key="item.date" class="h-8 rounded-lg transition hover:ring-2 hover:ring-teal-200" :class="(item.work + item.learning + item.progress) > 2 ? 'bg-teal-700' : (item.work + item.learning + item.progress) > 0 ? 'bg-teal-300' : 'bg-white border border-slate-200'" :title="item.date" /></div></section>
      <section class="card p-5"><h2 class="mb-4 font-extrabold">Weekly review</h2><div v-if="report" class="space-y-3 text-sm"><div class="rounded-2xl border border-slate-200 bg-slate-50 p-3.5"><b>Work delta</b><p class="mt-1 text-slate-500">{{ report.trends.completed_work_delta }} completed logs vs previous period</p></div><div class="rounded-2xl border border-slate-200 bg-slate-50 p-3.5"><b>Learning delta</b><p class="mt-1 text-slate-500">{{ minutes(report.trends.learning_minutes_delta) }} vs previous period</p></div><RouterLink class="btn btn-primary w-full" to="/reports/weekly">Open report</RouterLink></div></section>
    </div>
    <section class="card mt-5 p-5">
      <div class="mb-4 flex items-center justify-between"><h2 class="font-extrabold">Milestone progress</h2><RouterLink class="text-sm font-extrabold text-teal-700 hover:underline" to="/milestones">View all</RouterLink></div>
      <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        <RouterLink v-for="milestone in data.milestones" :key="milestone.id" :to="`/milestones/${milestone.id}`" class="rounded-2xl border border-slate-200 bg-white p-4 transition hover:border-teal-200 hover:bg-teal-50/40">
          <div class="flex items-start justify-between gap-3"><b class="text-sm text-slate-900 dark:text-zinc-100">{{ milestone.title }}</b><span class="pill shrink-0" :class="milestone.overdue ? 'pill-red' : 'pill-green'">{{ milestone.status }}</span></div>
          <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-zinc-700"><div class="h-full rounded-full bg-teal-700 transition-all" :style="{ width: `${milestone.progress_percent}%` }" /></div>
          <div class="mt-2 flex items-center justify-between gap-2">
            <p class="text-sm font-semibold text-slate-500">{{ milestone.progress_percent }}%</p>
            <span v-if="milestonePaceLabel(milestone)" class="text-xs font-bold"
              :class="milestonePaceLabel(milestone)!.tone === 'green' ? 'text-teal-700' : milestonePaceLabel(milestone)!.tone === 'amber' ? 'text-amber-600' : 'text-slate-400'">
              {{ milestonePaceLabel(milestone)!.label }}
            </span>
          </div>
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

  <!-- Standup modal -->
  <div v-if="standupOpen" class="fixed inset-0 z-50 grid place-items-center bg-slate-950/50 p-4 backdrop-blur-[2px]" @click.self="standupOpen = false">
    <section class="card w-full max-w-2xl overflow-hidden p-0 shadow-2xl">
      <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/70 px-5 py-4 dark:border-zinc-800 dark:bg-zinc-900/60">
        <div>
          <p class="text-xs font-extrabold uppercase text-teal-700 dark:text-teal-500">Daily</p>
          <h2 class="text-lg font-extrabold">Standup Generator</h2>
        </div>
        <div class="flex gap-2">
          <button class="btn btn-muted px-3" :class="standupCopied ? 'border-teal-300 text-teal-700' : ''" @click="copyStandup">
            {{ standupCopied ? '✓ Copied!' : 'Copy' }}
          </button>
          <button class="btn btn-muted px-3" @click="standupOpen = false">✕</button>
        </div>
      </div>
      <div class="max-h-[70vh] overflow-y-auto p-5">
        <div v-if="standupLoading" class="py-12 text-center text-sm text-slate-400">Generating...</div>
        <template v-else-if="standup">
          <div class="mb-4 grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-teal-100 bg-teal-50/60 p-3 dark:border-teal-800/40 dark:bg-teal-900/10">
              <p class="mb-2 text-[10px] font-extrabold uppercase text-teal-600 dark:text-teal-500">✅ Kemarin / Selesai</p>
              <ul class="space-y-1">
                <li v-for="log in standup.done_logs" :key="log.id" class="text-xs font-semibold text-slate-700 dark:text-zinc-300">· {{ log.title }}</li>
                <li v-for="task in standup.completed_tasks" :key="task.id" class="text-xs font-semibold text-slate-700 dark:text-zinc-300">· ✓ {{ task.title }}</li>
                <li v-if="!standup.done_logs.length && !standup.completed_tasks.length" class="text-xs text-slate-400">Tidak ada</li>
              </ul>
            </div>
            <div class="rounded-xl border border-sky-100 bg-sky-50/60 p-3 dark:border-sky-800/40 dark:bg-sky-900/10">
              <p class="mb-2 text-[10px] font-extrabold uppercase text-sky-600 dark:text-sky-500">📋 Hari ini</p>
              <ul class="space-y-1">
                <li v-for="task in standup.today_tasks" :key="task.id" class="text-xs font-semibold text-slate-700 dark:text-zinc-300">
                  <span v-if="task.status === 'in_progress'" class="text-sky-600">🔄 </span>
                  <span v-else>▫ </span>
                  {{ task.title }}
                </li>
                <li v-if="!standup.today_tasks.length" class="text-xs text-slate-400">Tidak ada task aktif</li>
              </ul>
            </div>
            <div class="rounded-xl border border-red-100 bg-red-50/60 p-3 dark:border-red-800/40 dark:bg-red-900/10">
              <p class="mb-2 text-[10px] font-extrabold uppercase text-red-600 dark:text-red-500">🚧 Blocker</p>
              <ul class="space-y-1">
                <li v-for="b in standup.blockers" :key="b.title" class="text-xs font-semibold text-slate-700 dark:text-zinc-300">· {{ b.title }}</li>
                <li v-if="!standup.blockers.length" class="text-xs text-slate-400">Tidak ada blocker</li>
              </ul>
            </div>
          </div>
          <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-zinc-700 dark:bg-zinc-800">
            <p class="mb-2 text-xs font-extrabold uppercase text-slate-400">Teks siap copy</p>
            <pre class="whitespace-pre-wrap text-xs leading-6 text-slate-700 dark:text-zinc-300">{{ standup.text }}</pre>
          </div>
        </template>
      </div>
    </section>
  </div>
</template>
