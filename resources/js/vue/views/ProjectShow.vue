<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { api, unwrap } from '../api';
import { toast } from '../feedback';
import { formatDate, minutes } from '../format';

const route = useRoute();
const data = ref<any>(null);
const loading = ref(true);

const maxMonthly = computed(() => Math.max(1, ...((data.value?.monthly_work || []).map((m: any) => m.minutes))));
const maxCategory = computed(() => Math.max(1, ...((data.value?.by_category || []).map((c: any) => c.minutes))));

const statusOrder = ['in_progress', 'todo', 'blocked', 'done'];
const statusColors: Record<string, string> = {
  in_progress: 'pill-blue',
  todo: 'pill-slate',
  blocked: 'pill-red',
  done: 'pill-green',
};
const priorityColors: Record<string, string> = {
  urgent: 'text-red-600',
  high: 'text-orange-500',
  medium: 'text-sky-600',
  low: 'text-slate-400',
};

onMounted(async () => {
  try {
    data.value = await api.get(`/api/v1/projects/${route.params.id}`).then(unwrap);
  } catch (e: any) {
    toast({ tone: 'error', title: 'Gagal memuat proyek', message: e?.response?.data?.message ?? 'Terjadi kesalahan.' });
  } finally {
    loading.value = false;
  }
});
</script>

<template>
  <!-- Loading skeleton -->
  <div v-if="loading" class="space-y-4">
    <div class="skeleton h-16 rounded-2xl"></div>
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
      <div v-for="i in 5" :key="i" class="skeleton h-24 rounded-2xl"></div>
    </div>
    <div class="grid gap-4 lg:grid-cols-2">
      <div class="skeleton h-64 rounded-2xl"></div>
      <div class="skeleton h-64 rounded-2xl"></div>
    </div>
  </div>
  <template v-else-if="data">
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <p class="text-xs font-extrabold uppercase text-teal-700 dark:text-teal-500">Project workspace</p>
        <h1 class="text-2xl font-extrabold">{{ data.project.name }}</h1>
        <p v-if="data.project.description" class="mt-1 text-sm text-slate-500 dark:text-zinc-500">{{ data.project.description }}</p>
      </div>
      <div class="flex flex-wrap gap-2">
        <RouterLink class="btn btn-primary" :to="`/work-logs/create`">Log work</RouterLink>
        <RouterLink class="btn btn-muted" :to="`/tasks/create`">New task</RouterLink>
        <RouterLink class="btn btn-muted" to="/projects">← Projects</RouterLink>
      </div>
    </div>

    <!-- Metrics -->
    <div class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-5">
      <div class="card p-4">
        <p class="label">Open tasks</p>
        <p class="mt-2 text-2xl font-extrabold">{{ data.metrics.open_tasks }}</p>
      </div>
      <div class="card p-4">
        <p class="label">Done tasks</p>
        <p class="mt-2 text-2xl font-extrabold text-teal-700 dark:text-teal-400">{{ data.metrics.completed_tasks }}</p>
      </div>
      <div class="card p-4">
        <p class="label">Completion</p>
        <p class="mt-2 text-2xl font-extrabold">{{ data.metrics.completion_rate }}%</p>
      </div>
      <div class="card p-4">
        <p class="label">Time logged</p>
        <p class="mt-2 text-2xl font-extrabold">{{ minutes(data.metrics.logged_minutes) }}</p>
      </div>
      <div class="card p-4">
        <p class="label">Blockers</p>
        <p class="mt-2 text-2xl font-extrabold" :class="data.metrics.blockers > 0 ? 'text-red-700 dark:text-red-400' : ''">{{ data.metrics.blockers }}</p>
      </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
      <!-- Tasks -->
      <section class="card p-5 xl:col-span-2">
        <div class="mb-4 flex items-center justify-between">
          <h2 class="font-extrabold">Tasks</h2>
          <RouterLink class="text-sm font-extrabold text-teal-700 hover:underline dark:text-teal-400" :to="`/tasks?project_id=${data.project.id}`">View all</RouterLink>
        </div>
        <div v-if="!data.tasks.length" class="py-6 text-center text-sm text-slate-400 dark:text-zinc-600">No tasks yet.</div>
        <div v-else class="divide-y divide-slate-100 dark:divide-zinc-800">
          <RouterLink
            v-for="task in data.tasks"
            :key="task.id"
            :to="`/tasks/${task.id}`"
            class="flex items-center gap-3 py-2.5 hover:pl-1 transition-all"
          >
            <span class="pill shrink-0" :class="statusColors[task.status] || 'pill-slate'">{{ task.status?.replaceAll('_', ' ') }}</span>
            <span class="flex-1 text-sm font-semibold text-slate-800 dark:text-zinc-200">{{ task.title }}</span>
            <span v-if="task.priority" class="shrink-0 text-xs font-bold" :class="priorityColors[task.priority]">{{ task.priority }}</span>
            <span v-if="task.due_date" class="shrink-0 text-xs text-slate-400 dark:text-zinc-600">{{ formatDate(task.due_date) }}</span>
          </RouterLink>
        </div>
      </section>

      <!-- Monthly work chart -->
      <section class="card p-5">
        <h2 class="mb-1 font-extrabold">Monthly Work</h2>
        <p class="mb-4 text-xs font-semibold text-slate-400">Last 6 months</p>
        <div v-if="data.monthly_work.length" class="flex h-28 items-end gap-1.5 rounded-xl bg-slate-50 p-3 dark:bg-zinc-800/50">
          <div v-for="m in data.monthly_work" :key="m.month" class="flex flex-1 flex-col items-center gap-1">
            <div
              class="w-full rounded-t-lg bg-sky-600"
              :style="{ height: `${Math.max(4, Math.round((m.minutes / maxMonthly) * 68))}px` }"
              :title="`${m.month}: ${minutes(m.minutes)}`"
            />
            <span class="text-[9px] font-bold text-slate-400">{{ m.month.slice(5) }}</span>
          </div>
        </div>
        <p v-else class="py-4 text-center text-xs text-slate-400">No data yet.</p>

        <div v-if="data.by_category.length" class="mt-4">
          <p class="label mb-2">By category</p>
          <div class="space-y-2">
            <div v-for="cat in data.by_category.slice(0, 5)" :key="cat.category">
              <div class="mb-1 flex justify-between text-xs">
                <span class="font-semibold capitalize">{{ cat.category || 'other' }}</span>
                <span class="text-slate-400">{{ minutes(cat.minutes) }}</span>
              </div>
              <div class="h-1 rounded-full bg-slate-100 dark:bg-zinc-800">
                <div class="h-full rounded-full bg-sky-600" :style="{ width: `${(cat.minutes / maxCategory) * 100}%` }" />
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Recent work logs -->
      <section class="card p-5 xl:col-span-2">
        <div class="mb-4 flex items-center justify-between">
          <h2 class="font-extrabold">Recent Work Logs</h2>
          <RouterLink class="text-sm font-extrabold text-teal-700 hover:underline dark:text-teal-400" :to="`/work-logs?project_name=${encodeURIComponent(data.project.name)}`">View all</RouterLink>
        </div>
        <div v-if="!data.workLogs.length" class="py-6 text-center text-sm text-slate-400 dark:text-zinc-600">No work logs yet.</div>
        <div v-else class="divide-y divide-slate-100 dark:divide-zinc-800">
          <RouterLink
            v-for="log in data.workLogs"
            :key="log.id"
            :to="`/work-logs/${log.id}`"
            class="flex items-center gap-3 py-2.5 hover:pl-1 transition-all"
          >
            <span class="shrink-0 text-xs text-slate-400 dark:text-zinc-600">{{ formatDate(log.date) }}</span>
            <span class="flex-1 text-sm font-semibold text-slate-800 dark:text-zinc-200">{{ log.title }}</span>
            <span class="shrink-0 text-xs font-bold text-slate-500">{{ minutes(log.actual_duration) }}</span>
          </RouterLink>
        </div>
      </section>

      <!-- Task status breakdown -->
      <section class="card p-5">
        <h2 class="mb-4 font-extrabold">Task Breakdown</h2>
        <div class="space-y-3">
          <div v-for="status in statusOrder" :key="status" class="flex items-center gap-3">
            <span class="pill shrink-0" :class="statusColors[status]">{{ status.replaceAll('_', ' ') }}</span>
            <div class="flex-1 overflow-hidden rounded-full bg-slate-100 dark:bg-zinc-800">
              <div
                class="h-2 rounded-full bg-teal-600"
                :class="status === 'blocked' ? '!bg-red-500' : status === 'done' ? '!bg-teal-600' : status === 'in_progress' ? '!bg-sky-500' : '!bg-slate-400'"
                :style="{ width: `${Math.round(((data.tasks_by_status[status] ?? 0) / Math.max(1, data.tasks.length)) * 100)}%` }"
              />
            </div>
            <span class="w-6 shrink-0 text-right text-sm font-bold text-slate-600 dark:text-zinc-400">{{ data.tasks_by_status[status] ?? 0 }}</span>
          </div>
        </div>
      </section>
    </div>
  </template>
</template>
