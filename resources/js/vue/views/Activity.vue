<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { api, unwrap } from '../api';

const router = useRouter();
const items = ref<any[]>([]);
const meta = ref<any>(null);
const loading = ref(true);
const loadingMore = ref(false);
const filterType = ref('');
const page = ref(1);

const typeOptions = [
  { value: '', label: 'All types' },
  { value: 'WorkLog', label: 'Work Logs' },
  { value: 'Task', label: 'Tasks' },
  { value: 'LearningEntry', label: 'Learning' },
  { value: 'DailyProgress', label: 'Daily Progress' },
  { value: 'Milestone', label: 'Milestones' },
  { value: 'Doc', label: 'Docs' },
];

const typeLabels: Record<string, string> = {
  WorkLog: 'Work Log',
  Task: 'Task',
  LearningEntry: 'Learning',
  DailyProgress: 'Daily Progress',
  Milestone: 'Milestone',
  Doc: 'Dokumen',
};

const typeBadgeColors: Record<string, string> = {
  WorkLog: 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-400',
  Task: 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-400',
  LearningEntry: 'bg-teal-100 text-teal-700 dark:bg-teal-900/40 dark:text-teal-400',
  DailyProgress: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400',
  Milestone: 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-400',
  Doc: 'bg-slate-100 text-slate-600 dark:bg-zinc-800 dark:text-zinc-400',
};

const dotColors: Record<string, string> = {
  WorkLog: 'bg-sky-500',
  Task: 'bg-violet-500',
  LearningEntry: 'bg-teal-500',
  DailyProgress: 'bg-amber-500',
  Milestone: 'bg-rose-500',
  Doc: 'bg-slate-400',
};

const actionLabels: Record<string, string> = {
  created: 'Created',
  updated: 'Updated',
  deleted: 'Deleted',
  archived: 'Archived',
  restored: 'Restored',
};

const typePaths: Record<string, string> = {
  WorkLog: 'work-logs',
  Task: 'tasks',
  LearningEntry: 'learning',
  DailyProgress: 'daily-progress',
  Milestone: 'milestones',
  Doc: 'docs',
};

const filtered = computed(() =>
  filterType.value ? items.value.filter((i) => i.record_type === filterType.value) : items.value,
);

const groupedByDate = computed(() => {
  const groups: { date: string; label: string; items: any[] }[] = [];
  let lastDate = '';
  for (const item of filtered.value) {
    const date = item.created_at.slice(0, 10);
    if (date !== lastDate) {
      groups.push({ date, label: formatDateHeader(date), items: [] });
      lastDate = date;
    }
    groups[groups.length - 1].items.push(item);
  }
  return groups;
});

function formatDateHeader(dateStr: string): string {
  const date = new Date(dateStr + 'T00:00:00');
  const today = new Date();
  const yesterday = new Date(today);
  yesterday.setDate(yesterday.getDate() - 1);
  if (dateStr === today.toISOString().slice(0, 10)) return 'Today';
  if (dateStr === yesterday.toISOString().slice(0, 10)) return 'Yesterday';
  return date.toLocaleDateString('en-US', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
}

function itemPath(item: any) {
  const base = typePaths[item.record_type];
  return base ? `/${base}/${item.record_id}` : null;
}

function timeOnly(dateStr: string) {
  return new Date(dateStr).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
}

function recordLabel(item: any) {
  return item.metadata?.title || item.metadata?.topic || item.metadata?.name || `#${item.record_id}`;
}

async function load() {
  loading.value = true;
  page.value = 1;
  const data = await api.get('/api/v1/activity', { params: { per_page: 30 } }).then(unwrap);
  items.value = data.activity?.data || [];
  meta.value = data.activity;
  loading.value = false;
}

async function loadMore() {
  if (!meta.value || meta.value.current_page >= meta.value.last_page) return;
  loadingMore.value = true;
  page.value++;
  const data = await api.get('/api/v1/activity', { params: { per_page: 30, page: page.value } }).then(unwrap);
  items.value.push(...(data.activity?.data || []));
  meta.value = data.activity;
  loadingMore.value = false;
}

onMounted(load);
</script>

<template>
  <div>
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <p class="text-xs font-extrabold uppercase text-teal-700 dark:text-teal-500">Timeline</p>
        <h1 class="text-2xl font-extrabold">Activity</h1>
        <p class="mt-1 text-sm font-medium text-slate-500 dark:text-zinc-500">All changes and records, newest first.</p>
      </div>
      <select v-model="filterType" class="field w-auto">
        <option v-for="opt in typeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
      </select>
    </div>

    <div v-if="loading" class="space-y-4">
      <div v-for="i in 3" :key="i" class="space-y-2">
        <div class="skeleton h-5 w-32 rounded-lg"></div>
        <div v-for="j in 3" :key="j" class="skeleton h-16 rounded-2xl"></div>
      </div>
    </div>

    <div v-else-if="!filtered.length" class="card p-10 text-center">
      <div class="mx-auto mb-4 grid h-14 w-14 place-items-center rounded-2xl bg-slate-100 dark:bg-zinc-800">
        <svg class="h-6 w-6 text-slate-400" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" viewBox="0 0 24 24"><path d="M12 8v4l3 3M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
      </div>
      <p class="font-extrabold text-slate-700 dark:text-zinc-300">No activity yet</p>
      <p class="mt-1 text-sm text-slate-400 dark:text-zinc-600">Start logging work, tasks, or learning.</p>
    </div>

    <div v-else class="space-y-6">
      <div v-for="group in groupedByDate" :key="group.date">
        <!-- Date header -->
        <div class="mb-3 flex items-center gap-3">
          <span class="text-xs font-extrabold uppercase tracking-wider text-slate-400 dark:text-zinc-600">{{ group.label }}</span>
          <div class="flex-1 border-t border-slate-100 dark:border-zinc-800"></div>
          <span class="text-xs font-semibold text-slate-400 dark:text-zinc-600">{{ group.items.length }}</span>
        </div>

        <!-- Timeline items -->
        <div class="relative">
          <div class="absolute left-4 top-0 h-full w-px bg-slate-100 dark:bg-zinc-800"></div>
          <div class="space-y-2">
            <div
              v-for="item in group.items"
              :key="item.id"
              class="relative flex items-start gap-4 pl-10"
            >
              <!-- Dot -->
              <span
                class="absolute left-2.5 top-4 h-3 w-3 rounded-full border-2 border-white dark:border-zinc-950"
                :class="dotColors[item.record_type] || 'bg-slate-400'"
              />
              <!-- Card -->
              <div
                class="card flex w-full cursor-pointer items-center justify-between gap-3 px-4 py-3 transition hover:border-teal-200 hover:shadow-sm dark:hover:border-teal-800"
                @click="itemPath(item) && router.push(itemPath(item)!)"
              >
                <div class="min-w-0 flex-1">
                  <div class="flex flex-wrap items-center gap-1.5">
                    <span class="rounded-md px-1.5 py-0.5 text-[10px] font-extrabold uppercase tracking-wide" :class="typeBadgeColors[item.record_type] || 'bg-slate-100 text-slate-500'">
                      {{ typeLabels[item.record_type] || item.record_type }}
                    </span>
                    <span v-if="item.label && actionLabels[item.label]" class="text-[10px] font-semibold text-slate-400 dark:text-zinc-600">{{ actionLabels[item.label] }}</span>
                    <span v-else-if="item.label" class="text-[10px] font-semibold text-slate-400 dark:text-zinc-600">{{ item.label }}</span>
                  </div>
                  <p class="mt-1 truncate text-sm font-semibold text-slate-700 dark:text-zinc-300">{{ recordLabel(item) }}</p>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                  <span class="text-xs tabular-nums text-slate-400 dark:text-zinc-600">{{ timeOnly(item.created_at) }}</span>
                  <svg class="h-3.5 w-3.5 text-slate-300 dark:text-zinc-700" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="mt-4 text-center">
        <button
          v-if="meta && meta.current_page < meta.last_page"
          class="btn btn-muted"
          :disabled="loadingMore"
          @click="loadMore"
        >
          {{ loadingMore ? 'Loading...' : 'Load more' }}
        </button>
        <p v-else class="text-xs text-slate-400 dark:text-zinc-600">All activity loaded.</p>
      </div>
    </div>
  </div>
</template>
