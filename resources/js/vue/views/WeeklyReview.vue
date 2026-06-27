<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { api, unwrap } from '../api';
import { formatDate, minutes } from '../format';
import { toast } from '../feedback';

const REFLECTION_KEY = 'weekly_review_reflection';

const report = ref<any>(null);
const snapshots = ref<any[]>([]);
const reflection = ref('');
const saving = ref(false);
const weekOffset = ref(0);
const compareA = ref<any>(null);
const compareB = ref<any>(null);
const compareMode = ref(false);

const anchor = computed(() => {
  if (!weekOffset.value) return undefined;
  const d = new Date();
  d.setDate(d.getDate() + weekOffset.value * 7);
  return d.toISOString().slice(0, 10);
});

function reflectionKey(start: string) {
  return `${REFLECTION_KEY}_${start}`;
}

async function load() {
  report.value = null;
  const params: Record<string, string> = {};
  if (anchor.value) params.date = anchor.value;
  report.value = (await api.get('/api/v1/reports/weekly', { params }).then(unwrap)).report;
  reflection.value = localStorage.getItem(reflectionKey(report.value.start)) ?? '';
  loadSnapshots();
}

async function loadSnapshots() {
  const res = await api.get('/api/v1/reports/weekly/snapshots').then(unwrap);
  snapshots.value = res.snapshots || [];
}

async function saveSnapshot() {
  if (saving.value) return;
  saving.value = true;
  const params: Record<string, string> = {};
  if (anchor.value) params.date = anchor.value;
  try {
    await api.post('/api/v1/reports/weekly/snapshots', { reflection: reflection.value }, { params });
    localStorage.setItem(reflectionKey(report.value.start), reflection.value);
    await loadSnapshots();
    toast({ tone: 'success', title: 'Snapshot saved', message: `Week of ${formatDate(report.value.start)} saved.` });
  } finally {
    saving.value = false;
  }
}

function onReflectionInput(e: Event) {
  const val = (e.target as HTMLTextAreaElement).value;
  reflection.value = val;
  if (report.value?.start) localStorage.setItem(reflectionKey(report.value.start), val);
}

function toggleCompare(snap: any) {
  if (compareA.value?.id === snap.id) { compareA.value = null; return; }
  if (compareB.value?.id === snap.id) { compareB.value = null; return; }
  if (!compareA.value) { compareA.value = snap; return; }
  if (!compareB.value) { compareB.value = snap; return; }
  compareA.value = snap;
}

function compareSelected(snap: any) {
  return compareA.value?.id === snap.id || compareB.value?.id === snap.id;
}

function compareDelta(key: string, subKey?: string) {
  if (!compareA.value || !compareB.value) return null;
  const a = subKey ? (compareA.value.payload?.[key]?.[subKey] ?? 0) : (compareA.value.payload?.[key]?.length ?? compareA.value.payload?.[key] ?? 0);
  const b = subKey ? (compareB.value.payload?.[key]?.[subKey] ?? 0) : (compareB.value.payload?.[key]?.length ?? compareB.value.payload?.[key] ?? 0);
  return Number(b) - Number(a);
}

const learningByCategory = computed(() => {
  if (!report.value?.learning_totals?.by_category) return {};
  return report.value.learning_totals.by_category as Record<string, number>;
});

const maxCategoryMins = computed(() => Math.max(1, ...Object.values(learningByCategory.value).map(Number)));

const timeByCategory = computed(() => {
  if (!report.value?.time_by_category) return {};
  return report.value.time_by_category as Record<string, number>;
});

const maxTimeMins = computed(() => Math.max(1, ...Object.values(timeByCategory.value).map(Number)));

function delta(n: number) {
  return (n >= 0 ? '+' : '') + n;
}

function minsFmt(n: number) {
  return minutes(n);
}

const isCurrentWeek = computed(() => weekOffset.value === 0);

onMounted(load);
</script>

<template>
  <div>
    <!-- Header -->
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <p class="text-xs font-extrabold uppercase text-teal-700 dark:text-teal-500">Review</p>
        <h1 class="text-2xl font-extrabold">Weekly Review</h1>
        <p v-if="report" class="mt-1 text-sm font-semibold text-slate-500 dark:text-zinc-500">{{ formatDate(report.start) }} – {{ formatDate(report.end) }}</p>
      </div>
      <div class="flex items-center gap-2">
        <button class="btn btn-muted px-3" :disabled="weekOffset <= -12" @click="weekOffset--; load()">← Prev</button>
        <button class="btn btn-muted px-3" :class="isCurrentWeek ? 'opacity-40' : ''" :disabled="isCurrentWeek" @click="weekOffset++; load()">Next →</button>
        <button v-if="!isCurrentWeek" class="btn btn-muted px-3 text-teal-700" @click="weekOffset = 0; load()">This week</button>
      </div>
    </div>

    <div v-if="!report" class="card p-8 text-center text-sm text-slate-500 dark:text-zinc-500">Loading...</div>
    <template v-else>
      <!-- Stat cards -->
      <div class="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="card p-4">
          <p class="label">Learning</p>
          <p class="mt-2 text-2xl font-extrabold">{{ minsFmt(report.learning_totals.minutes) }}</p>
          <p class="mt-1 text-xs font-semibold" :class="report.trends.learning_minutes_delta >= 0 ? 'text-teal-700 dark:text-teal-400' : 'text-red-600 dark:text-red-400'">
            {{ delta(report.trends.learning_minutes_delta) }} min vs last week
          </p>
        </div>
        <div class="card p-4">
          <p class="label">Tasks Done</p>
          <p class="mt-2 text-2xl font-extrabold">{{ report.tasks_done?.length ?? 0 }}</p>
          <p class="mt-1 text-xs font-semibold text-slate-400 dark:text-zinc-500">completed this week</p>
        </div>
        <div class="card p-4">
          <p class="label">Work Sessions</p>
          <p class="mt-2 text-2xl font-extrabold">{{ report.completed_work_logs.length }}</p>
          <p class="mt-1 text-xs font-semibold" :class="report.trends.completed_work_delta >= 0 ? 'text-teal-700 dark:text-teal-400' : 'text-red-600 dark:text-red-400'">
            {{ delta(report.trends.completed_work_delta) }} vs last week
          </p>
        </div>
        <div class="card p-4">
          <p class="label">Open Blockers</p>
          <p class="mt-2 text-2xl font-extrabold" :class="report.open_blockers.length > 0 ? 'text-red-700 dark:text-red-400' : ''">{{ report.open_blockers.length }}</p>
          <p class="mt-1 text-xs font-semibold text-slate-400 dark:text-zinc-500">unresolved</p>
        </div>
      </div>

      <div class="grid gap-6 lg:grid-cols-3">
        <!-- Left column -->
        <div class="lg:col-span-2 grid gap-6 content-start">
          <!-- Tasks done this week -->
          <section class="card p-5">
            <h2 class="mb-3 text-sm font-extrabold uppercase text-slate-500 dark:text-zinc-500">Tasks Completed</h2>
            <ul v-if="report.tasks_done?.length" class="grid gap-2">
              <li v-for="task in report.tasks_done" :key="task.id" class="flex items-start gap-2 rounded-lg border border-slate-100 px-3 py-2.5 text-sm dark:border-zinc-800">
                <svg class="mt-0.5 h-4 w-4 shrink-0 text-teal-600" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>
                <span class="font-semibold">{{ task.title }}</span>
              </li>
            </ul>
            <p v-else class="text-sm text-slate-400 dark:text-zinc-600">No tasks completed this week.</p>
          </section>

          <!-- Key achievements -->
          <section v-if="report.key_achievements.length" class="card p-5">
            <h2 class="mb-3 text-sm font-extrabold uppercase text-slate-500 dark:text-zinc-500">Key Achievements</h2>
            <ul class="grid gap-2">
              <li v-for="item in report.key_achievements" :key="item" class="rounded-lg border border-teal-100 bg-teal-50/50 px-3 py-2 text-sm font-medium dark:border-teal-900/40 dark:bg-teal-900/10">{{ item }}</li>
            </ul>
          </section>

          <!-- Learning by category -->
          <section v-if="Object.keys(learningByCategory).length" class="card p-5">
            <h2 class="mb-3 text-sm font-extrabold uppercase text-slate-500 dark:text-zinc-500">Learning by Category</h2>
            <div class="grid gap-3">
              <div v-for="(mins, cat) in learningByCategory" :key="String(cat)">
                <div class="mb-1 flex justify-between text-sm"><span class="font-semibold capitalize">{{ cat }}</span><span class="text-slate-500">{{ minsFmt(Number(mins)) }}</span></div>
                <div class="h-1.5 rounded-full bg-slate-100 dark:bg-zinc-800"><div class="h-full rounded-full bg-teal-600" :style="{ width: `${(Number(mins) / maxCategoryMins) * 100}%` }" /></div>
              </div>
            </div>
          </section>

          <!-- Work by category -->
          <section v-if="Object.keys(timeByCategory).length" class="card p-5">
            <h2 class="mb-3 text-sm font-extrabold uppercase text-slate-500 dark:text-zinc-500">Work by Category</h2>
            <div class="grid gap-3">
              <div v-for="(mins, cat) in timeByCategory" :key="String(cat)">
                <div class="mb-1 flex justify-between text-sm"><span class="font-semibold capitalize">{{ cat }}</span><span class="text-slate-500">{{ minsFmt(Number(mins)) }}</span></div>
                <div class="h-1.5 rounded-full bg-slate-100 dark:bg-zinc-800"><div class="h-full rounded-full bg-sky-600" :style="{ width: `${(Number(mins) / maxTimeMins) * 100}%` }" /></div>
              </div>
            </div>
          </section>
        </div>

        <!-- Right column: reflection + snapshots -->
        <div class="grid gap-6 content-start">
          <!-- Reflection -->
          <section class="card p-5">
            <h2 class="mb-3 text-sm font-extrabold uppercase text-slate-500 dark:text-zinc-500">This Week's Reflection</h2>
            <textarea
              class="field min-h-36 w-full resize-y text-sm"
              placeholder="What went well? What needs improvement next week?"
              :value="reflection"
              @input="onReflectionInput"
            />
            <button class="btn btn-primary mt-3 w-full" :disabled="saving" @click="saveSnapshot">
              {{ saving ? 'Saving...' : 'Save Snapshot' }}
            </button>
            <p class="mt-2 text-xs text-slate-400 dark:text-zinc-600">Saving a snapshot locks this week's data and reflection.</p>
          </section>

          <!-- Past snapshots -->
          <section v-if="snapshots.length" class="card p-5">
            <div class="mb-3 flex items-center justify-between">
              <h2 class="text-sm font-extrabold uppercase text-slate-500 dark:text-zinc-500">Saved Snapshots</h2>
              <button class="text-xs font-bold" :class="compareMode ? 'text-teal-700 dark:text-teal-400' : 'text-slate-400 dark:text-zinc-600'" @click="compareMode = !compareMode; compareA = null; compareB = null">
                {{ compareMode ? 'Exit compare' : 'Compare' }}
              </button>
            </div>
            <p v-if="compareMode && (!compareA || !compareB)" class="mb-3 rounded-lg bg-teal-50 px-3 py-2 text-xs font-semibold text-teal-700 dark:bg-teal-900/20 dark:text-teal-400">
              Select 2 snapshots to compare.
            </p>
            <!-- Comparison panel -->
            <div v-if="compareMode && compareA && compareB" class="mb-4 rounded-xl border border-teal-100 bg-teal-50/50 p-3 dark:border-teal-800/40 dark:bg-teal-900/10">
              <p class="mb-2 text-xs font-extrabold text-teal-700 dark:text-teal-400">{{ formatDate(compareA.period_start) }} vs {{ formatDate(compareB.period_start) }}</p>
              <div class="grid grid-cols-3 gap-2 text-xs">
                <div>
                  <p class="text-slate-400 dark:text-zinc-600">Learning</p>
                  <p class="font-extrabold" :class="(compareDelta('learning_totals', 'minutes') ?? 0) >= 0 ? 'text-teal-700 dark:text-teal-400' : 'text-red-600 dark:text-red-400'">
                    {{ (compareDelta('learning_totals', 'minutes') ?? 0) >= 0 ? '+' : '' }}{{ minsFmt(compareDelta('learning_totals', 'minutes') ?? 0) }}
                  </p>
                </div>
                <div>
                  <p class="text-slate-400 dark:text-zinc-600">Tasks</p>
                  <p class="font-extrabold" :class="(compareDelta('tasks_done') ?? 0) >= 0 ? 'text-teal-700 dark:text-teal-400' : 'text-red-600 dark:text-red-400'">
                    {{ (compareDelta('tasks_done') ?? 0) >= 0 ? '+' : '' }}{{ compareDelta('tasks_done') }}
                  </p>
                </div>
                <div>
                  <p class="text-slate-400 dark:text-zinc-600">Sessions</p>
                  <p class="font-extrabold" :class="(compareDelta('completed_work_logs') ?? 0) >= 0 ? 'text-teal-700 dark:text-teal-400' : 'text-red-600 dark:text-red-400'">
                    {{ (compareDelta('completed_work_logs') ?? 0) >= 0 ? '+' : '' }}{{ compareDelta('completed_work_logs') }}
                  </p>
                </div>
              </div>
            </div>
            <div class="grid gap-3">
              <div
                v-for="snap in snapshots"
                :key="snap.id"
                class="cursor-pointer rounded-xl border p-3 text-xs transition"
                :class="compareSelected(snap) ? 'border-teal-300 bg-teal-50/60 dark:border-teal-700 dark:bg-teal-900/20' : 'border-slate-100 dark:border-zinc-800 hover:border-slate-200'"
                @click="compareMode ? toggleCompare(snap) : undefined"
              >
                <div class="flex items-center justify-between gap-2">
                  <p class="font-extrabold text-slate-700 dark:text-zinc-300">{{ formatDate(snap.period_start) }}</p>
                  <svg v-if="compareSelected(snap)" class="h-3.5 w-3.5 text-teal-600" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>
                </div>
                <div class="mt-1.5 flex gap-4 text-slate-500 dark:text-zinc-500">
                  <span>{{ minsFmt(snap.payload?.learning_totals?.minutes ?? 0) }} learning</span>
                  <span>{{ snap.payload?.tasks_done?.length ?? 0 }} tasks</span>
                  <span>{{ snap.payload?.completed_work_logs?.length ?? 0 }} sessions</span>
                </div>
                <p v-if="snap.payload?.reflection" class="mt-2 line-clamp-2 italic text-slate-500 dark:text-zinc-500">{{ snap.payload.reflection }}</p>
              </div>
            </div>
          </section>
        </div>
      </div>
    </template>
  </div>
</template>
