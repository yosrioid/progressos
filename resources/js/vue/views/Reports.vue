<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { api, unwrap } from '../api';
import BarChart from '../components/BarChart.vue';
import { toast } from '../feedback';
import { formatDate, minutes } from '../format';

const route = useRoute();
const router = useRouter();
const report = ref<any>(null);
const date = ref(String(route.query.date || ''));
const snapshots = ref<any[]>([]);
const savingSnapshot = ref(false);
const showSnapshots = ref(false);

async function load() {
  date.value = String(route.query.date || '');
  report.value = (await api.get(`/api/v1/reports/${route.params.period}`, { params: date.value ? { date: date.value } : {} }).then(unwrap)).report;
}

async function loadSnapshots() {
  const params: Record<string, string> = {};
  if (date.value) params.date = date.value;
  const res = await api.get(`/api/v1/reports/${route.params.period}/snapshots`, { params }).then(unwrap);
  snapshots.value = res.snapshots || [];
}

async function saveSnapshot() {
  if (savingSnapshot.value) return;
  savingSnapshot.value = true;
  const params: Record<string, string> = {};
  if (date.value) params.date = date.value;
  try {
    await api.post(`/api/v1/reports/${route.params.period}/snapshots`, {}, { params });
    await loadSnapshots();
    showSnapshots.value = true;
    toast({ tone: 'success', title: 'Snapshot saved', message: `${report.value?.period} of ${formatDate(report.value?.start)} saved.` });
  } finally {
    savingSnapshot.value = false;
  }
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

const categoryLabels = computed(() => Object.keys(report.value?.time_by_category ?? {}));
const categoryValues = computed(() => Object.values(report.value?.time_by_category ?? {}).map(Number));

const projectLabels = computed(() => Object.keys(report.value?.most_active_projects ?? {}));
const projectValues = computed(() => Object.values(report.value?.most_active_projects ?? {}).map(Number));

const trendLabels = computed(() => ['Work logs', 'Learning', 'Logged time']);
const trendValues = computed(() => report.value ? [
  report.value.trends?.completed_work_delta ?? 0,
  report.value.trends?.learning_minutes_delta ?? 0,
  report.value.trends?.logged_minutes_delta ?? 0,
] : []);

watch(() => route.fullPath, load);
onMounted(async () => { await load(); loadSnapshots(); });
</script>

<template>
  <div v-if="!report" class="card p-8 text-center text-sm text-slate-500">Loading report...</div>
  <template v-else>
    <div class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
      <div>
        <p class="text-xs font-extrabold uppercase text-teal-700 dark:text-teal-500">{{ report.period }}</p>
        <h1 class="text-2xl font-extrabold capitalize">{{ report.period }} Report</h1>
        <p class="mt-1 text-sm font-medium text-slate-500 dark:text-zinc-500">{{ formatDate(report.start) }} → {{ formatDate(report.end) }}</p>
      </div>
      <div class="flex flex-wrap gap-2">
        <RouterLink class="btn btn-muted" to="/reports/weekly">Weekly</RouterLink>
        <RouterLink class="btn btn-muted" to="/reports/monthly">Monthly</RouterLink>
        <input v-model="date" class="field w-auto" type="date" />
        <button class="btn btn-primary" @click="applyDate">Apply</button>
        <button class="btn btn-muted" :disabled="savingSnapshot" @click="saveSnapshot">{{ savingSnapshot ? 'Saving…' : 'Save Snapshot' }}</button>
        <a class="btn btn-muted" :href="exportHref()">Export CSV</a>
        <a class="btn btn-muted" :href="exportPdfHref()" target="_blank">Export PDF</a>
      </div>
    </div>

    <!-- Stats -->
    <div class="mb-5 grid gap-3 md:grid-cols-3">
      <div class="card p-4"><p class="label">Completed work</p><p class="mt-2 text-2xl font-extrabold">{{ report.completed_work_logs.length }}</p></div>
      <div class="card p-4"><p class="label">Open blockers</p><p class="mt-2 text-2xl font-extrabold text-rose-700 dark:text-rose-400">{{ report.open_blockers.length }}</p></div>
      <div class="card p-4"><p class="label">Learning</p><p class="mt-2 text-2xl font-extrabold">{{ minutes(report.learning_totals.minutes) }}</p></div>
    </div>

    <!-- Trend chart -->
    <div class="card mb-5 p-5">
      <h2 class="mb-4 font-extrabold">Period-over-period delta</h2>
      <p class="mb-3 text-xs text-slate-400">Change vs previous {{ report.period }}</p>
      <BarChart
        :labels="trendLabels"
        :values="trendValues"
        :format-value="(v) => (v >= 0 ? '+' : '') + v"
      />
    </div>

    <!-- Main sections -->
    <div class="grid gap-5 xl:grid-cols-2">
      <section class="card p-5">
        <h2 class="mb-4 font-extrabold">Key achievements</h2>
        <ul class="grid gap-2">
          <li v-for="item in report.key_achievements" :key="item" class="rounded-xl border border-teal-100 bg-teal-50/40 px-3 py-2 text-sm font-semibold dark:border-teal-800/30 dark:bg-teal-900/10">{{ item }}</li>
          <li v-if="report.key_achievements.length === 0" class="text-sm text-slate-400">No achievements captured for this period.</li>
        </ul>
      </section>

      <section class="card p-5">
        <h2 class="mb-4 font-extrabold">Time by category</h2>
        <BarChart
          v-if="categoryLabels.length"
          :labels="categoryLabels"
          :values="categoryValues"
          :format-value="minutes"
        />
        <p v-else class="text-sm text-slate-400">No time logged this period.</p>
      </section>

      <section class="card p-5">
        <h2 class="mb-4 font-extrabold">Most active projects</h2>
        <BarChart
          v-if="projectLabels.length"
          :labels="projectLabels"
          :values="projectValues"
          :format-value="(v) => v + ' logs'"
        />
        <p v-else class="text-sm text-slate-400">No project activity.</p>
      </section>

      <section class="card p-5">
        <h2 class="mb-4 font-extrabold">Open blockers</h2>
        <div class="grid gap-2">
          <div v-for="blocker in report.open_blockers" :key="`${blocker.id}-${blocker.title}`" class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm dark:border-zinc-700 dark:bg-zinc-800/40"><b>{{ blocker.title }}</b><p class="mt-0.5 text-slate-500 dark:text-zinc-400">{{ blocker.project_name || blocker.priority }}</p></div>
          <p v-if="report.open_blockers.length === 0" class="text-sm text-slate-400">No open blockers.</p>
        </div>
      </section>
    </div>

    <!-- Snapshots -->
    <section v-if="snapshots.length || showSnapshots" class="card mt-5 p-5">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="font-extrabold">Saved Snapshots</h2>
        <button class="text-xs font-semibold text-slate-400 hover:text-teal-700 dark:hover:text-teal-400" @click="showSnapshots = !showSnapshots">{{ showSnapshots ? 'Hide' : 'Show' }}</button>
      </div>
      <div v-if="showSnapshots" class="grid gap-3 md:grid-cols-2">
        <div v-for="snap in snapshots" :key="snap.id" class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-zinc-700 dark:bg-zinc-800/40">
          <p class="text-xs font-extrabold text-slate-500 dark:text-zinc-400">{{ formatDate(snap.period_start) }}</p>
          <div class="mt-1.5 flex gap-4 text-sm">
            <span><b>{{ snap.data?.completed_work_logs ?? '—' }}</b> <span class="text-slate-400">work</span></span>
            <span><b>{{ snap.data?.open_blockers ?? '—' }}</b> <span class="text-slate-400">blockers</span></span>
            <span><b>{{ minutes(snap.data?.learning_minutes ?? 0) }}</b> <span class="text-slate-400">learning</span></span>
          </div>
          <p v-if="snap.reflection" class="mt-2 text-xs text-slate-500 dark:text-zinc-400 italic line-clamp-2">"{{ snap.reflection }}"</p>
        </div>
        <p v-if="!snapshots.length" class="text-sm text-slate-400">No snapshots saved yet. Click "Save Snapshot" to capture the current period.</p>
      </div>
    </section>
  </template>
</template>
