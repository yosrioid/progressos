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
  { value: '', label: 'Semua tipe' },
  { value: 'WorkLog', label: 'Work Logs' },
  { value: 'Task', label: 'Tasks' },
  { value: 'LearningEntry', label: 'Learning' },
  { value: 'DailyProgress', label: 'Daily Progress' },
  { value: 'Milestone', label: 'Milestones' },
  { value: 'Doc', label: 'Docs' },
];

const typeColors: Record<string, string> = {
  WorkLog: 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-400',
  Task: 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-400',
  LearningEntry: 'bg-teal-100 text-teal-700 dark:bg-teal-900/40 dark:text-teal-400',
  DailyProgress: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400',
  Milestone: 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-400',
  Doc: 'bg-slate-100 text-slate-600 dark:bg-zinc-800 dark:text-zinc-400',
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

function itemPath(item: any) {
  const base = typePaths[item.record_type];
  return base ? `/${base}/${item.record_id}` : null;
}

function relativeTime(dateStr: string) {
  const diff = Date.now() - new Date(dateStr).getTime();
  const m = Math.floor(diff / 60000);
  if (m < 1) return 'baru saja';
  if (m < 60) return `${m} menit lalu`;
  const h = Math.floor(m / 60);
  if (h < 24) return `${h} jam lalu`;
  const d = Math.floor(h / 24);
  if (d < 7) return `${d} hari lalu`;
  return new Date(dateStr).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
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
      </div>
      <div class="flex items-center gap-2">
        <select v-model="filterType" class="field w-auto">
          <option v-for="opt in typeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
        </select>
      </div>
    </div>

    <div v-if="loading" class="space-y-3">
      <div v-for="i in 8" :key="i" class="skeleton h-16 rounded-2xl"></div>
    </div>

    <div v-else-if="!filtered.length" class="card p-10 text-center text-sm text-slate-500 dark:text-zinc-500">
      Belum ada aktivitas tercatat.
    </div>

    <div v-else class="relative">
      <div class="absolute left-5 top-0 h-full w-px bg-slate-200 dark:bg-zinc-700"></div>
      <div class="space-y-2">
        <div
          v-for="item in filtered"
          :key="item.id"
          class="relative flex items-start gap-4 pl-12"
        >
          <!-- dot -->
          <span class="absolute left-3.5 top-4 h-3 w-3 rounded-full border-2 border-white bg-slate-300 dark:border-zinc-950 dark:bg-zinc-600"
            :class="typeColors[item.record_type]?.replace('bg-', 'border-').split(' ')[0]"
          />
          <div class="card flex w-full items-center justify-between gap-3 px-4 py-3">
            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center gap-2">
                <span class="rounded-lg px-2 py-0.5 text-[10px] font-extrabold uppercase" :class="typeColors[item.record_type] || 'bg-slate-100 text-slate-500'">
                  {{ item.record_type }}
                </span>
                <span class="text-sm font-semibold text-slate-700 dark:text-zinc-300">{{ item.label }}</span>
              </div>
              <p class="mt-0.5 truncate text-xs text-slate-400 dark:text-zinc-600">{{ recordLabel(item) }}</p>
            </div>
            <div class="flex shrink-0 items-center gap-2">
              <span class="text-xs text-slate-400 dark:text-zinc-600">{{ relativeTime(item.created_at) }}</span>
              <button v-if="itemPath(item)" class="btn btn-muted px-2 py-1 text-xs" @click="router.push(itemPath(item)!)">
                Lihat →
              </button>
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
          {{ loadingMore ? 'Memuat...' : 'Muat lebih banyak' }}
        </button>
        <p v-else class="text-xs text-slate-400 dark:text-zinc-600">Semua aktivitas sudah dimuat.</p>
      </div>
    </div>
  </div>
</template>
