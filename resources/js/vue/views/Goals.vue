<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { api, unwrap } from '../api';
import { confirmAction, toast } from '../feedback';

interface KeyResult {
  id: number;
  title: string;
  metric_type: string;
  current_value: number;
  target_value: number;
  unit: string | null;
  notes: string | null;
  status: string;
  order: number;
  progress: number;
}

interface Goal {
  id: number;
  title: string;
  description: string | null;
  period_label: string | null;
  start_date: string | null;
  end_date: string | null;
  status: string;
  color: string;
  progress: number;
  key_results: KeyResult[];
}

const goals = ref<Goal[]>([]);
const loading = ref(true);
const filterStatus = ref('');
const expandedGoalId = ref<number | null>(null);

const activeTab = ref<'goals' | 'milestones'>('goals');
const milestones = ref<any[]>([]);
const milestonesLoading = ref(false);
const milestonesLoaded = ref(false);

async function loadMilestones() {
  if (milestonesLoaded.value) return;
  milestonesLoading.value = true;
  try {
    const res = await api.get('/api/v1/milestones', { params: { per_page: 100, sort: 'end_date', direction: 'asc' } }).then(unwrap);
    milestones.value = res.milestones?.data || [];
    milestonesLoaded.value = true;
  } finally {
    milestonesLoading.value = false;
  }
}

function switchTab(tab: 'goals' | 'milestones') {
  activeTab.value = tab;
  if (tab === 'milestones' && !milestonesLoaded.value) loadMilestones();
}

function milestoneStatusClass(status: string) {
  return ({
    active: 'pill-green',
    paused: 'pill-slate',
    completed: 'pill-blue',
    cancelled: 'pill-red',
  } as Record<string, string>)[status] ?? 'pill-slate';
}

const showGoalForm = ref(false);
const editingGoal = ref<Goal | null>(null);
const goalSaving = ref(false);
const goalFormError = ref('');
const goalForm = ref({ title: '', description: '', period_label: '', status: 'active', start_date: '', end_date: '', color: '#0d9488' });
const goalColors = ['#0d9488', '#0ea5e9', '#8b5cf6', '#6366f1', '#ec4899', '#ef4444', '#f97316', '#eab308', '#22c55e'];

const showKrForm = ref(false);
const editingKr = ref<KeyResult | null>(null);
const activeGoalForKr = ref<Goal | null>(null);
const krSaving = ref(false);
const krFormError = ref('');
const krBoolDone = ref(false);
const krForm = ref({ title: '', metric_type: 'percentage', current_value: 0, target_value: 100, unit: '' });

const filteredGoals = computed(() => filterStatus.value ? goals.value.filter((g) => g.status === filterStatus.value) : goals.value);

function statusClass(status: string) {
  return ({
    active: 'bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-400',
    draft: 'bg-slate-100 text-slate-600 dark:bg-zinc-800 dark:text-zinc-400',
    completed: 'bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-400',
    abandoned: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
  } as Record<string, string>)[status] ?? '';
}

function toggleExpand(id: number) {
  expandedGoalId.value = expandedGoalId.value === id ? null : id;
}

async function load() {
  loading.value = true;
  try {
    const res = await api.get('/api/v1/goals').then(unwrap);
    goals.value = res.goals;
  } finally {
    loading.value = false;
  }
}

function openGoalForm(goal?: Goal) {
  editingGoal.value = goal ?? null;
  goalForm.value = goal
    ? { title: goal.title, description: goal.description ?? '', period_label: goal.period_label ?? '', status: goal.status, start_date: goal.start_date ?? '', end_date: goal.end_date ?? '', color: goal.color }
    : { title: '', description: '', period_label: '', status: 'active', start_date: '', end_date: '', color: '#0d9488' };
  goalFormError.value = '';
  showGoalForm.value = true;
}

function closeGoalForm() {
  showGoalForm.value = false;
  editingGoal.value = null;
}

async function submitGoalForm() {
  if (!goalForm.value.title.trim()) { goalFormError.value = 'Title is required'; return; }
  goalSaving.value = true;
  goalFormError.value = '';
  try {
    if (editingGoal.value) {
      await api.patch(`/api/v1/goals/${editingGoal.value.id}`, goalForm.value);
    } else {
      await api.post('/api/v1/goals', goalForm.value);
    }
    closeGoalForm();
    await load();
  } catch (e: any) {
    goalFormError.value = e?.response?.data?.message ?? 'Failed to save';
  } finally {
    goalSaving.value = false;
  }
}

async function deleteGoal(goal: Goal) {
  const ok = await confirmAction({ title: 'Hapus goal', message: `Hapus "${goal.title}"?`, confirmLabel: 'Hapus' });
  if (!ok) return;
  try {
    await api.delete(`/api/v1/goals/${goal.id}`);
    await load();
  } catch (e: any) {
    toast({ tone: 'error', title: 'Error', message: e?.response?.data?.message ?? 'Failed to delete goal' });
  }
}

function openKrForm(goal: Goal, kr?: KeyResult) {
  activeGoalForKr.value = goal;
  editingKr.value = kr ?? null;
  krBoolDone.value = kr?.status === 'done';
  krForm.value = kr
    ? { title: kr.title, metric_type: kr.metric_type, current_value: kr.current_value, target_value: kr.target_value, unit: kr.unit ?? '' }
    : { title: '', metric_type: 'percentage', current_value: 0, target_value: 100, unit: '' };
  krFormError.value = '';
  showKrForm.value = true;
}

function closeKrForm() {
  showKrForm.value = false;
  editingKr.value = null;
  activeGoalForKr.value = null;
}

async function submitKrForm() {
  if (!krForm.value.title.trim()) { krFormError.value = 'Title is required'; return; }
  krSaving.value = true;
  krFormError.value = '';
  const payload: any = { ...krForm.value };
  if (payload.metric_type === 'boolean') { payload.current_value = krBoolDone.value ? 1 : 0; payload.target_value = 1; }
  try {
    if (editingKr.value) {
      await api.patch(`/api/v1/goals/${activeGoalForKr.value!.id}/key-results/${editingKr.value.id}`, payload);
    } else {
      await api.post(`/api/v1/goals/${activeGoalForKr.value!.id}/key-results`, payload);
    }
    closeKrForm();
    await load();
    expandedGoalId.value = activeGoalForKr.value?.id ?? null;
  } catch (e: any) {
    krFormError.value = e?.response?.data?.message ?? 'Failed to save';
  } finally {
    krSaving.value = false;
  }
}

async function toggleKrDone(goal: Goal, kr: KeyResult) {
  const newStatus = kr.status === 'done' ? 'active' : 'done';
  const prev = kr.status;
  kr.status = newStatus;
  try {
    await api.patch(`/api/v1/goals/${goal.id}/key-results/${kr.id}`, { status: newStatus });
    await load();
    expandedGoalId.value = goal.id;
  } catch (e: any) {
    kr.status = prev;
    toast({ tone: 'error', title: 'Error', message: e?.response?.data?.message ?? 'Failed to update status' });
  }
}

async function deleteKr(goal: Goal, kr: KeyResult) {
  const ok = await confirmAction({ title: 'Hapus key result', message: `Hapus "${kr.title}"?`, confirmLabel: 'Hapus' });
  if (!ok) return;
  try {
    await api.delete(`/api/v1/goals/${goal.id}/key-results/${kr.id}`);
    await load();
    expandedGoalId.value = goal.id;
  } catch (e: any) {
    toast({ tone: 'error', title: 'Error', message: e?.response?.data?.message ?? 'Failed to delete key result' });
  }
}

onMounted(load);
</script>

<template>
  <div>
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <p class="text-xs font-extrabold uppercase text-teal-700 dark:text-teal-500">{{ activeTab === 'milestones' ? 'Tracker' : 'OKR' }}</p>
        <h1 class="text-3xl font-extrabold tracking-tight">{{ activeTab === 'milestones' ? 'Milestones' : 'Goals & OKR' }}</h1>
        <p class="mt-1 text-sm font-medium text-slate-500 dark:text-zinc-500">{{ activeTab === 'milestones' ? 'Measurable targets and progress markers' : 'Set goals and track key results' }}</p>
      </div>
      <div class="flex items-center gap-2">
        <div class="inline-flex rounded-xl border border-slate-200 bg-white p-0.5 dark:border-zinc-700 dark:bg-zinc-900">
          <button class="rounded-lg px-3 py-1.5 text-sm font-bold transition" :class="activeTab === 'goals' ? 'bg-slate-900 text-white dark:bg-zinc-100 dark:text-zinc-900' : 'text-slate-500 hover:text-slate-700 dark:text-zinc-400'" @click="switchTab('goals')">Goals</button>
          <button class="rounded-lg px-3 py-1.5 text-sm font-bold transition" :class="activeTab === 'milestones' ? 'bg-slate-900 text-white dark:bg-zinc-100 dark:text-zinc-900' : 'text-slate-500 hover:text-slate-700 dark:text-zinc-400'" @click="switchTab('milestones')">Milestones</button>
        </div>
        <template v-if="activeTab === 'goals'">
          <select v-model="filterStatus" class="field w-auto">
            <option value="">All statuses</option>
            <option value="active">Active</option>
            <option value="draft">Draft</option>
            <option value="completed">Completed</option>
            <option value="abandoned">Abandoned</option>
          </select>
          <button class="btn btn-primary" @click="openGoalForm()">+ New Goal</button>
        </template>
        <RouterLink v-else class="btn btn-primary" to="/milestones/create">+ New Milestone</RouterLink>
      </div>
    </div>

    <!-- Milestones tab -->
    <template v-if="activeTab === 'milestones'">
      <div v-if="milestonesLoading" class="grid gap-3">
        <div v-for="i in 3" :key="i" class="skeleton h-16 rounded-2xl" />
      </div>
      <div v-else-if="!milestones.length" class="card p-12 text-center">
        <div class="mx-auto mb-4 grid h-12 w-12 place-items-center rounded-xl bg-teal-50 dark:bg-teal-900/20">
          <svg class="h-6 w-6 text-teal-700 dark:text-teal-400" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>
        </div>
        <h2 class="text-base font-extrabold">Belum ada milestone</h2>
        <RouterLink class="btn btn-primary mt-4 inline-flex" to="/milestones/create">+ Buat Milestone Pertama</RouterLink>
      </div>
      <div v-else class="grid gap-3">
        <RouterLink v-for="m in milestones" :key="m.id" :to="`/milestones/${m.id}`" class="card block p-4 hover:border-teal-200 hover:shadow-sm">
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
              <h2 class="truncate font-extrabold text-slate-900 dark:text-zinc-100">{{ m.title }}</h2>
              <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-sm text-slate-500 dark:text-zinc-500">
                <span v-if="m.end_date">Due {{ m.end_date }}</span>
                <span v-if="m.category" class="text-slate-400">· {{ m.category }}</span>
              </div>
            </div>
            <span class="pill shrink-0" :class="milestoneStatusClass(m.status)">{{ m.status?.replace('_', ' ') }}</span>
          </div>
          <div v-if="m.current_value != null && m.target_value" class="mt-3">
            <div class="mb-1 flex justify-between text-xs font-semibold text-slate-500">
              <span>{{ m.current_value }} / {{ m.target_value }}{{ m.unit ? ' ' + m.unit : '' }}</span>
              <span>{{ Math.round((m.current_value / m.target_value) * 100) }}%</span>
            </div>
            <div class="h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-zinc-800">
              <div class="h-full rounded-full bg-teal-600 transition-all" :style="{ width: `${Math.min(100, Math.round((m.current_value / m.target_value) * 100))}%` }" />
            </div>
          </div>
        </RouterLink>
      </div>
    </template>

    <!-- Goals tab content -->
    <template v-if="activeTab === 'goals'">

    <!-- Loading -->
    <div v-if="loading" class="grid gap-3">
      <div v-for="i in 3" :key="i" class="skeleton h-20 rounded-2xl"></div>
    </div>

    <!-- Empty state -->
    <div v-else-if="!filteredGoals.length" class="card p-12 text-center">
      <div class="mx-auto mb-4 grid h-12 w-12 place-items-center rounded-xl bg-teal-50 dark:bg-teal-900/20">
        <svg class="h-6 w-6 text-teal-700 dark:text-teal-400" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>
      </div>
      <h2 class="text-base font-extrabold text-slate-900 dark:text-zinc-100">Belum ada goal</h2>
      <p class="mx-auto mt-2 max-w-xs text-sm text-slate-400 dark:text-zinc-500">Buat goal pertama untuk mulai tracking progress kamu.</p>
      <button class="btn btn-primary mt-4" @click="openGoalForm()">+ New Goal</button>
    </div>

    <!-- Goals list -->
    <div v-else class="grid gap-3">
      <div v-for="goal in filteredGoals" :key="goal.id" class="card overflow-hidden p-0">
        <!-- Goal header -->
        <div class="flex items-start gap-3 p-4 cursor-pointer" @click="toggleExpand(goal.id)">
          <div class="w-3 h-3 rounded-full mt-1.5 flex-shrink-0" :style="{ background: goal.color }"></div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="font-extrabold text-slate-900 dark:text-zinc-100">{{ goal.title }}</span>
              <span :class="['text-xs font-semibold px-2 py-0.5 rounded-full', statusClass(goal.status)]">{{ goal.status }}</span>
              <span v-if="goal.period_label" class="text-xs font-medium text-slate-400 dark:text-zinc-500">{{ goal.period_label }}</span>
            </div>
            <p v-if="goal.description" class="text-xs text-slate-500 dark:text-zinc-400 mt-0.5 truncate">{{ goal.description }}</p>
            <div class="flex items-center gap-2 mt-2">
              <div class="flex-1 h-1.5 bg-slate-100 dark:bg-zinc-800 rounded-full overflow-hidden">
                <div class="h-1.5 rounded-full transition-all" :style="{ width: goal.progress + '%', background: goal.color }"></div>
              </div>
              <span class="text-xs font-extrabold text-slate-500 dark:text-zinc-400 w-10 text-right">{{ goal.progress }}%</span>
            </div>
          </div>
          <div class="flex items-center gap-1">
            <button class="btn-icon-edit" title="Edit" @click.stop="openGoalForm(goal)">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5Z"/></svg>
            </button>
            <button class="btn-icon-delete" title="Delete" @click.stop="deleteGoal(goal)">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0 1 16.138 21H7.862a2 2 0 0 1-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v3M4 7h16"/></svg>
            </button>
            <span class="text-xs text-slate-400 ml-1">{{ expandedGoalId === goal.id ? '▲' : '▼' }}</span>
          </div>
        </div>

        <!-- Key Results (expanded) -->
        <div v-if="expandedGoalId === goal.id" class="border-t border-slate-100 dark:border-zinc-800 bg-slate-50/50 dark:bg-zinc-800/30 p-4 space-y-3">
          <p v-if="!goal.key_results.length" class="text-sm text-slate-400 dark:text-zinc-500">No key results yet</p>

          <div v-for="kr in goal.key_results" :key="kr.id" class="flex items-start gap-3">
            <button
              :class="['w-5 h-5 rounded border-2 flex-shrink-0 mt-0.5 flex items-center justify-center text-xs font-bold transition-colors', kr.status === 'done' ? 'bg-teal-600 border-teal-600 text-white' : 'border-slate-300 dark:border-zinc-600 hover:border-teal-400']"
              @click="toggleKrDone(goal, kr)"
            >{{ kr.status === 'done' ? '✓' : '' }}</button>
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <span :class="['text-sm font-semibold', kr.status === 'done' ? 'line-through text-slate-400 dark:text-zinc-500' : 'text-slate-800 dark:text-zinc-200']">{{ kr.title }}</span>
                <span class="text-xs text-slate-400 dark:text-zinc-500">{{ kr.current_value }}{{ kr.unit ? ' ' + kr.unit : '' }} / {{ kr.target_value }}{{ kr.unit ? ' ' + kr.unit : '' }}</span>
              </div>
              <div class="flex items-center gap-2 mt-1">
                <div class="flex-1 h-1 bg-slate-100 dark:bg-zinc-800 rounded-full overflow-hidden max-w-xs">
                  <div class="h-1 bg-teal-600 rounded-full" :style="{ width: kr.progress + '%' }"></div>
                </div>
                <span class="text-xs text-slate-400 dark:text-zinc-500">{{ kr.progress }}%</span>
              </div>
            </div>
            <div class="flex items-center gap-1">
              <button class="btn-icon-edit" @click="openKrForm(goal, kr)">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5Z"/></svg>
              </button>
              <button class="btn-icon-delete" @click="deleteKr(goal, kr)">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0 1 16.138 21H7.862a2 2 0 0 1-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v3M4 7h16"/></svg>
              </button>
            </div>
          </div>

          <button class="text-xs font-semibold text-teal-700 dark:text-teal-500 hover:underline" @click="openKrForm(goal)">+ Add Key Result</button>
        </div>
      </div>
    </div>

    </template><!-- end goals tab -->

    <!-- Goal form modal -->
    <div v-if="showGoalForm" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 px-4" @click.self="closeGoalForm">
      <div class="bg-white dark:bg-zinc-900 rounded-2xl shadow-2xl w-full max-w-lg p-6 space-y-4 border border-slate-200 dark:border-zinc-700">
        <h2 class="text-lg font-extrabold text-slate-900 dark:text-zinc-100">{{ editingGoal ? 'Edit Goal' : 'New Goal' }}</h2>

        <label class="block">
          <span class="label mb-1">Title *</span>
          <input v-model="goalForm.title" class="field" />
        </label>

        <label class="block">
          <span class="label mb-1">Description</span>
          <textarea v-model="goalForm.description" class="field min-h-16" rows="2"></textarea>
        </label>

        <div class="grid grid-cols-2 gap-3">
          <label class="block">
            <span class="label mb-1">Period</span>
            <input v-model="goalForm.period_label" class="field" placeholder="Q2 2026" />
          </label>
          <label class="block">
            <span class="label mb-1">Status</span>
            <select v-model="goalForm.status" class="field">
              <option value="draft">Draft</option>
              <option value="active">Active</option>
              <option value="completed">Completed</option>
              <option value="abandoned">Abandoned</option>
            </select>
          </label>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <label class="block">
            <span class="label mb-1">Start Date</span>
            <input v-model="goalForm.start_date" type="date" class="field" />
          </label>
          <label class="block">
            <span class="label mb-1">End Date</span>
            <input v-model="goalForm.end_date" type="date" class="field" />
          </label>
        </div>

        <div>
          <span class="label mb-2 block">Color</span>
          <div class="flex gap-2 flex-wrap">
            <button
              v-for="c in goalColors"
              :key="c"
              :class="['w-6 h-6 rounded-full border-2 transition-transform', goalForm.color === c ? 'border-slate-900 dark:border-white scale-110' : 'border-transparent']"
              :style="{ background: c }"
              @click="goalForm.color = c"
            ></button>
          </div>
        </div>

        <p v-if="goalFormError" class="text-sm font-semibold text-red-600">{{ goalFormError }}</p>

        <div class="flex justify-end gap-2 pt-1">
          <button class="btn btn-muted" @click="closeGoalForm">Cancel</button>
          <button class="btn btn-primary" :disabled="goalSaving" @click="submitGoalForm">
            {{ goalSaving ? 'Saving...' : (editingGoal ? 'Save' : 'Create') }}
          </button>
        </div>
      </div>
    </div>

    <!-- KR form modal -->
    <div v-if="showKrForm" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 px-4" @click.self="closeKrForm">
      <div class="bg-white dark:bg-zinc-900 rounded-2xl shadow-2xl w-full max-w-md p-6 space-y-4 border border-slate-200 dark:border-zinc-700">
        <h2 class="text-lg font-extrabold text-slate-900 dark:text-zinc-100">{{ editingKr ? 'Edit Key Result' : 'New Key Result' }}</h2>

        <label class="block">
          <span class="label mb-1">Title *</span>
          <input v-model="krForm.title" class="field" />
        </label>

        <div class="grid grid-cols-2 gap-3">
          <label class="block">
            <span class="label mb-1">Metric Type</span>
            <select v-model="krForm.metric_type" class="field">
              <option value="percentage">Percentage (%)</option>
              <option value="number">Number</option>
              <option value="boolean">Yes/No</option>
            </select>
          </label>
          <label class="block">
            <span class="label mb-1">Unit</span>
            <input v-model="krForm.unit" class="field" placeholder="hrs, tasks, …" />
          </label>
        </div>

        <div v-if="krForm.metric_type !== 'boolean'" class="grid grid-cols-2 gap-3">
          <label class="block">
            <span class="label mb-1">Current Value</span>
            <input v-model.number="krForm.current_value" type="number" class="field" />
          </label>
          <label class="block">
            <span class="label mb-1">Target</span>
            <input v-model.number="krForm.target_value" type="number" class="field" />
          </label>
        </div>

        <label v-if="krForm.metric_type === 'boolean'" class="flex items-center gap-2">
          <input id="bool_done" v-model="krBoolDone" type="checkbox" class="w-4 h-4" />
          <span class="text-sm font-semibold text-slate-700 dark:text-zinc-300">Completed</span>
        </label>

        <p v-if="krFormError" class="text-sm font-semibold text-red-600">{{ krFormError }}</p>

        <div class="flex justify-end gap-2 pt-1">
          <button class="btn btn-muted" @click="closeKrForm">Cancel</button>
          <button class="btn btn-primary" :disabled="krSaving" @click="submitKrForm">
            {{ krSaving ? 'Saving...' : (editingKr ? 'Save' : 'Add') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
