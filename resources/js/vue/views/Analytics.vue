<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { api, unwrap } from '../api';
import { minutes } from '../format';

const data = ref<any>(null);
const loading = ref(true);

onMounted(async () => {
  try {
    const res = await api.get('/api/v1/analytics').then(unwrap);
    data.value = res;
  } catch {
    // error handled by global 401 interceptor; loading stays true to show skeleton
  } finally {
    loading.value = false;
  }
});

function heatColor(mins: number) {
  if (mins === 0) return 'bg-slate-100 dark:bg-zinc-800';
  if (mins < 60) return 'bg-sky-200 dark:bg-sky-900';
  if (mins < 180) return 'bg-sky-400 dark:bg-sky-700';
  return 'bg-sky-700 dark:bg-sky-500';
}

const maxVelocity = computed(() => Math.max(1, ...((data.value?.task_velocity || []).map((w: any) => w.count))));
const maxMonthly = computed(() => Math.max(1, ...((data.value?.monthly_work || []).map((m: any) => m.minutes))));
const maxCategory = computed(() => Math.max(1, ...((data.value?.by_category || []).map((c: any) => c.minutes))));
const maxLearnWork = computed(() => {
  const rows = data.value?.learn_vs_work || [];
  return Math.max(1, ...rows.flatMap((r: any) => [r.learning, r.work]));
});

const scoreColor = computed(() => {
  const s = data.value?.productivity_score ?? 0;
  if (s >= 70) return 'text-teal-700 dark:text-teal-400';
  if (s >= 40) return 'text-amber-600 dark:text-amber-400';
  return 'text-red-600 dark:text-red-400';
});

const taskStatusOrder = ['todo', 'in_progress', 'done', 'blocked'];
const taskStatusColors: Record<string, string> = {
  todo: 'bg-slate-400',
  in_progress: 'bg-sky-500',
  done: 'bg-teal-600',
  blocked: 'bg-red-500',
};
const taskStatusLabels: Record<string, string> = {
  todo: 'To Do',
  in_progress: 'In Progress',
  done: 'Done',
  blocked: 'Blocked',
};
const totalTasks = computed(() => {
  const s = data.value?.task_status || {};
  return Object.values(s).reduce((a: number, b: any) => a + Number(b), 0) || 1;
});
</script>

<template>
  <div>
    <div class="mb-5">
      <p class="text-xs font-extrabold uppercase text-teal-700 dark:text-teal-500">Insights</p>
      <h1 class="text-2xl font-extrabold">Analytics</h1>
      <p class="mt-1 text-sm font-medium text-slate-500 dark:text-zinc-500">All productivity metrics in one view.</p>
    </div>

    <div v-if="loading" class="grid gap-4">
      <div v-for="i in 4" :key="i" class="skeleton h-48 rounded-2xl"></div>
    </div>

    <template v-else-if="data">
      <!-- Summary cards -->
      <div class="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="card p-4">
          <p class="label">Work sessions</p>
          <p class="mt-2 text-3xl font-extrabold">{{ data.totals.work_logs }}</p>
          <p class="mt-1 text-xs font-semibold text-slate-400">{{ minutes(data.totals.work_minutes) }} total</p>
        </div>
        <div class="card p-4">
          <p class="label">Tasks done</p>
          <p class="mt-2 text-3xl font-extrabold">{{ data.totals.tasks_done }}</p>
          <p class="mt-1 text-xs font-semibold text-slate-400">{{ data.totals.completion_rate }}% completion rate</p>
        </div>
        <div class="card p-4">
          <p class="label">Learning</p>
          <p class="mt-2 text-3xl font-extrabold">{{ minutes(data.totals.learning_minutes) }}</p>
          <p class="mt-1 text-xs font-semibold text-slate-400">all time</p>
        </div>
        <div class="card p-4">
          <p class="label">Productivity score</p>
          <p class="mt-2 text-3xl font-extrabold" :class="scoreColor">{{ data.productivity_score }}</p>
          <p class="mt-1 text-xs font-semibold text-slate-400">composite index / 100</p>
        </div>
      </div>

      <div class="grid gap-6 lg:grid-cols-2">
        <!-- Work log heatmap -->
        <section class="card p-5 lg:col-span-2">
          <h2 class="mb-1 font-extrabold">Work Session Heatmap</h2>
          <p class="mb-4 text-xs font-semibold text-slate-400">Last 90 days — duration per day</p>
          <div class="flex flex-wrap gap-1">
            <div
              v-for="day in data.work_heatmap"
              :key="day.date"
              class="h-4 w-4 rounded-sm transition"
              :class="heatColor(day.minutes)"
              :title="`${day.date}: ${minutes(day.minutes)}`"
            />
          </div>
          <div class="mt-2 flex items-center gap-2 text-xs text-slate-400">
            <span>Less</span>
            <div class="h-3 w-3 rounded-sm bg-sky-200 dark:bg-sky-900" />
            <div class="h-3 w-3 rounded-sm bg-sky-400 dark:bg-sky-700" />
            <div class="h-3 w-3 rounded-sm bg-sky-700" />
            <span>More</span>
          </div>
        </section>

        <!-- Task velocity -->
        <section class="card p-5">
          <h2 class="mb-1 font-extrabold">Task Velocity</h2>
          <p class="mb-4 text-xs font-semibold text-slate-400">Tasks completed per week — last 8 weeks</p>
          <div class="flex h-36 items-end gap-1.5 rounded-xl bg-slate-50 p-3 dark:bg-zinc-800/50">
            <div v-for="w in data.task_velocity" :key="w.week" class="flex flex-1 flex-col items-center gap-1">
              <span class="text-xs font-extrabold text-teal-700 dark:text-teal-400">{{ w.count || '' }}</span>
              <div
                class="w-full rounded-t-lg bg-teal-600 transition-all"
                :style="{ height: `${Math.max(4, Math.round((w.count / maxVelocity) * 80))}px` }"
              />
              <span class="text-[9px] font-bold text-slate-400">{{ w.week }}</span>
            </div>
          </div>
        </section>

        <!-- Monthly work hours -->
        <section class="card p-5">
          <h2 class="mb-1 font-extrabold">Monthly Work Hours</h2>
          <p class="mb-4 text-xs font-semibold text-slate-400">Last 6 months</p>
          <div class="flex h-36 items-end gap-1.5 rounded-xl bg-slate-50 p-3 dark:bg-zinc-800/50">
            <div v-for="m in data.monthly_work" :key="m.month" class="flex flex-1 flex-col items-center gap-1">
              <div
                class="w-full rounded-t-lg bg-sky-600 transition-all"
                :style="{ height: `${Math.max(4, Math.round((m.minutes / maxMonthly) * 80))}px` }"
                :title="`${m.month}: ${minutes(m.minutes)}`"
              />
              <span class="text-[9px] font-bold text-slate-400">{{ m.month.slice(5) }}</span>
            </div>
          </div>
        </section>

        <!-- Work by category -->
        <section class="card p-5">
          <h2 class="mb-1 font-extrabold">Work by Category</h2>
          <p class="mb-4 text-xs font-semibold text-slate-400">All work time by category</p>
          <div class="space-y-3">
            <div v-for="cat in data.by_category.slice(0, 8)" :key="cat.category">
              <div class="mb-1 flex justify-between text-sm">
                <span class="font-semibold capitalize">{{ cat.category || 'other' }}</span>
                <span class="text-slate-500">{{ minutes(cat.minutes) }} · {{ cat.logs }} logs</span>
              </div>
              <div class="h-1.5 rounded-full bg-slate-100 dark:bg-zinc-800">
                <div class="h-full rounded-full bg-sky-600" :style="{ width: `${(cat.minutes / maxCategory) * 100}%` }" />
              </div>
            </div>
          </div>
        </section>

        <!-- Task status donut-style breakdown -->
        <section class="card p-5">
          <h2 class="mb-1 font-extrabold">Task Status</h2>
          <p class="mb-4 text-xs font-semibold text-slate-400">All tasks by status</p>
          <div class="space-y-3">
            <div v-for="status in taskStatusOrder" :key="status" class="flex items-center gap-3">
              <span class="h-3 w-3 shrink-0 rounded-full" :class="taskStatusColors[status]" />
              <span class="flex-1 text-sm font-semibold capitalize">{{ taskStatusLabels[status] }}</span>
              <span class="w-8 text-right text-sm font-bold text-slate-700 dark:text-zinc-300">{{ data.task_status[status] ?? 0 }}</span>
              <div class="w-24 overflow-hidden rounded-full bg-slate-100 dark:bg-zinc-800">
                <div class="h-2 rounded-full transition-all" :class="taskStatusColors[status]"
                  :style="{ width: `${((data.task_status[status] ?? 0) / totalTasks) * 100}%` }" />
              </div>
            </div>
          </div>
          <p class="mt-3 text-right text-xs text-slate-400">{{ data.totals.tasks_total }} tasks total</p>
        </section>

        <!-- Learn vs Work last 4 weeks -->
        <section class="card p-5 lg:col-span-2">
          <h2 class="mb-1 font-extrabold">Learning vs Work</h2>
          <p class="mb-4 text-xs font-semibold text-slate-400">Learning vs work minutes — last 4 weeks</p>
          <div class="flex h-44 items-end gap-3 rounded-xl bg-slate-50 p-4 dark:bg-zinc-800/50">
            <div v-for="w in data.learn_vs_work" :key="w.week" class="flex flex-1 flex-col items-end gap-1">
              <div class="flex w-full items-end justify-center gap-1">
                <div class="flex w-5 flex-col items-center gap-1">
                  <div class="w-full rounded-t-lg bg-teal-500" :style="{ height: `${Math.max(4, Math.round((w.learning / maxLearnWork) * 120))}px` }" :title="`Learning: ${minutes(w.learning)}`" />
                </div>
                <div class="flex w-5 flex-col items-center gap-1">
                  <div class="w-full rounded-t-lg bg-sky-500" :style="{ height: `${Math.max(4, Math.round((w.work / maxLearnWork) * 120))}px` }" :title="`Work: ${minutes(w.work)}`" />
                </div>
              </div>
              <span class="w-full text-center text-[9px] font-bold text-slate-400">{{ w.week }}</span>
            </div>
          </div>
          <div class="mt-3 flex gap-4 text-xs font-bold text-slate-500">
            <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-teal-500" />Learning</span>
            <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-sky-500" />Work</span>
          </div>
        </section>

        <!-- Habit completion rate -->
        <section v-if="data.habit_weeks?.length" class="card p-5">
          <h2 class="mb-1 font-extrabold">Habit Completion Rate</h2>
          <p class="mb-4 text-xs font-semibold text-slate-400">% of active habits logged — last 4 weeks</p>
          <div class="flex h-36 items-end gap-1.5 rounded-xl bg-slate-50 p-3 dark:bg-zinc-800/50">
            <div v-for="w in data.habit_weeks" :key="w.week" class="flex flex-1 flex-col items-center gap-1">
              <span class="text-xs font-extrabold text-teal-700 dark:text-teal-400">{{ w.rate > 0 ? w.rate + '%' : '' }}</span>
              <div class="w-full rounded-t-lg bg-teal-600 transition-all" :style="{ height: `${Math.max(4, Math.round(w.rate * 0.8))}px` }" :title="`${w.week}: ${w.rate}% (${w.logged} habits / ${w.active} active)`" />
              <span class="text-[9px] font-bold text-slate-400">{{ w.week }}</span>
            </div>
          </div>
        </section>

        <!-- Active goals -->
        <section v-if="data.active_goals?.length" class="card p-5">
          <h2 class="mb-1 font-extrabold">Active Goals</h2>
          <p class="mb-4 text-xs font-semibold text-slate-400">OKR progress by goal</p>
          <div class="space-y-4">
            <div v-for="goal in data.active_goals" :key="goal.id">
              <div class="mb-1 flex items-center justify-between gap-2">
                <div class="flex items-center gap-2 min-w-0">
                  <span class="h-2.5 w-2.5 rounded-full flex-shrink-0" :style="{ background: goal.color }"></span>
                  <span class="text-sm font-semibold text-slate-700 dark:text-zinc-300 truncate">{{ goal.title }}</span>
                </div>
                <span class="text-sm font-extrabold text-slate-600 dark:text-zinc-400 flex-shrink-0">{{ goal.progress }}%</span>
              </div>
              <div class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-zinc-800">
                <div class="h-full rounded-full transition-all" :style="{ width: `${goal.progress}%`, background: goal.color }" />
              </div>
            </div>
          </div>
        </section>
      </div>
    </template>
  </div>
</template>
