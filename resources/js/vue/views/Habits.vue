<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { api, unwrap } from '../api';
import { toast } from '../feedback';

interface Habit {
  id: number;
  name: string;
  description: string | null;
  color: string;
  icon: string;
  frequency: string;
  target_days: number[] | null;
  active: boolean;
  today_done: boolean;
  streak: number;
  week_dates: string[];
  heatmap: string[];
  total_logs: number;
}

const habits = ref<Habit[]>([]);
const loading = ref(true);
const today = ref('');
const expandedId = ref<number | null>(null);
const showForm = ref(false);
const editingHabit = ref<Habit | null>(null);
const saving = ref(false);
const formError = ref('');

const colorOptions = ['#0d9488', '#0ea5e9', '#8b5cf6', '#ec4899', '#ef4444', '#f97316', '#eab308', '#22c55e', '#06b6d4', '#64748b'];
const form = ref({ name: '', description: '', icon: '✓', color: '#0d9488', frequency: 'daily' });

const todayDoneCount = computed(() => habits.value.filter((h) => h.today_done).length);

const weekDays = computed(() => {
  const days = [];
  for (let i = 6; i >= 0; i--) {
    const d = new Date();
    d.setDate(d.getDate() - i);
    const dateStr = d.toISOString().split('T')[0];
    days.push({ date: dateStr, short: ['S', 'M', 'T', 'W', 'T', 'F', 'S'][d.getDay()], label: dateStr, isToday: i === 0 });
  }
  return days;
});

function isLoggedOn(habit: Habit, date: string): boolean {
  return habit.week_dates.includes(date);
}

function getHeatmap(habit: Habit) {
  const cells = [];
  for (let i = 89; i >= 0; i--) {
    const d = new Date();
    d.setDate(d.getDate() - i);
    const date = d.toISOString().split('T')[0];
    cells.push({ date, done: habit.heatmap.includes(date) });
  }
  return cells;
}

async function load() {
  loading.value = true;
  try {
    const res = await api.get('/api/v1/habits').then(unwrap);
    habits.value = res.habits;
    today.value = res.today;
  } finally {
    loading.value = false;
  }
}

async function toggleToday(habit: Habit) {
  const prev = habit.today_done;
  habit.today_done = !prev;
  if (prev) {
    habit.streak = Math.max(0, habit.streak - 1);
    try {
      await api.delete(`/api/v1/habits/${habit.id}/log?date=${today.value}`);
    } catch {
      habit.today_done = prev;
    }
  } else {
    habit.streak += 1;
    habit.week_dates = [...habit.week_dates, today.value];
    try {
      await api.post(`/api/v1/habits/${habit.id}/log`, { date: today.value });
    } catch {
      habit.today_done = prev;
      habit.streak -= 1;
    }
  }
}

function openForm(habit?: Habit) {
  editingHabit.value = habit ?? null;
  form.value = habit
    ? { name: habit.name, description: habit.description ?? '', icon: habit.icon, color: habit.color, frequency: habit.frequency }
    : { name: '', description: '', icon: '✓', color: '#0d9488', frequency: 'daily' };
  formError.value = '';
  showForm.value = true;
}

function closeForm() {
  showForm.value = false;
  editingHabit.value = null;
}

async function submitForm() {
  if (!form.value.name.trim()) { formError.value = 'Name is required'; return; }
  saving.value = true;
  formError.value = '';
  try {
    if (editingHabit.value) {
      await api.patch(`/api/v1/habits/${editingHabit.value.id}`, form.value);
    } else {
      await api.post('/api/v1/habits', form.value);
    }
    closeForm();
    await load();
  } catch (e: any) {
    formError.value = e?.response?.data?.message ?? 'Failed to save';
  } finally {
    saving.value = false;
  }
}

async function confirmDelete(habit: Habit) {
  if (!confirm(`Delete habit "${habit.name}"? All logs will also be removed.`)) return;
  try {
    await api.delete(`/api/v1/habits/${habit.id}`);
    await load();
  } catch (e: any) {
    toast({ tone: 'error', title: 'Error', message: e?.response?.data?.message ?? 'Failed to delete habit' });
  }
}

onMounted(load);
</script>

<template>
  <div>
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <p class="text-xs font-extrabold uppercase text-teal-700 dark:text-teal-500">Consistency</p>
        <h1 class="text-2xl font-extrabold">Habit Tracker</h1>
        <p class="mt-1 text-sm font-medium text-slate-500 dark:text-zinc-500">Build habits one day at a time</p>
      </div>
      <button class="btn btn-primary" @click="openForm()">+ New Habit</button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="grid gap-3">
      <div v-for="i in 3" :key="i" class="skeleton h-24 rounded-2xl"></div>
    </div>

    <!-- Empty state -->
    <div v-else-if="!habits.length" class="card p-12 text-center">
      <div class="mx-auto mb-4 grid h-12 w-12 place-items-center rounded-xl bg-teal-50 dark:bg-teal-900/20">
        <svg class="h-6 w-6 text-teal-700 dark:text-teal-400" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" viewBox="0 0 24 24"><path d="M9 11l3 3L22 4M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
      </div>
      <h2 class="text-base font-extrabold text-slate-900 dark:text-zinc-100">Belum ada habit</h2>
      <p class="mx-auto mt-2 max-w-xs text-sm text-slate-400 dark:text-zinc-500">Buat habit pertama untuk mulai tracking kebiasaan harian kamu.</p>
      <button class="btn btn-primary mt-4" @click="openForm()">+ New Habit</button>
    </div>

    <template v-else>
      <!-- Today summary -->
      <div class="card mb-4 flex items-center gap-3 p-3">
        <span class="text-sm font-extrabold text-slate-700 dark:text-zinc-200">{{ todayDoneCount }}/{{ habits.length }} done today</span>
        <div class="flex-1 h-2 bg-slate-100 dark:bg-zinc-800 rounded-full overflow-hidden">
          <div class="h-2 bg-teal-600 rounded-full transition-all" :style="{ width: (habits.length ? todayDoneCount / habits.length * 100 : 0) + '%' }"></div>
        </div>
        <span class="text-xs text-slate-400 dark:text-zinc-500">{{ today }}</span>
      </div>

      <!-- Habits list -->
      <div class="grid gap-3">
        <div v-for="habit in habits" :key="habit.id" class="card group p-4">
          <div class="flex items-start gap-3">
            <!-- Check button -->
            <button
              :title="habit.today_done ? 'Undo' : 'Mark done'"
              :class="['w-10 h-10 rounded-xl text-lg font-bold flex items-center justify-center flex-shrink-0 transition-all', habit.today_done ? 'text-white shadow-sm' : 'bg-slate-100 dark:bg-zinc-800 text-slate-400 hover:bg-slate-200 dark:hover:bg-zinc-700']"
              :style="habit.today_done ? { background: habit.color } : {}"
              @click="toggleToday(habit)"
            >
              {{ habit.today_done ? '✓' : habit.icon }}
            </button>

            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="font-extrabold text-slate-900 dark:text-zinc-100">{{ habit.name }}</span>
                <span v-if="habit.streak > 0" class="text-xs font-bold text-orange-500">🔥 {{ habit.streak }} days</span>
                <span class="text-xs text-slate-400 dark:text-zinc-500">{{ habit.total_logs }} total</span>
              </div>
              <p v-if="habit.description" class="text-xs text-slate-500 dark:text-zinc-400 mt-0.5 truncate">{{ habit.description }}</p>

              <!-- Week view (last 7 days) -->
              <div class="flex gap-1 mt-2">
                <div
                  v-for="day in weekDays"
                  :key="day.date"
                  :class="['w-6 h-6 rounded text-center text-xs leading-6 font-semibold transition-colors', isLoggedOn(habit, day.date) ? 'text-white' : day.isToday ? 'bg-slate-200 dark:bg-zinc-700 text-slate-500' : 'bg-slate-100 dark:bg-zinc-800 text-slate-300 dark:text-zinc-600']"
                  :style="isLoggedOn(habit, day.date) ? { background: habit.color } : {}"
                  :title="day.label"
                >
                  {{ day.short }}
                </div>
              </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-1">
              <button class="rounded-lg p-1.5 text-slate-300 hover:text-teal-600 dark:text-zinc-700 dark:hover:text-teal-400 transition-colors" aria-label="Edit habit" @click="openForm(habit)">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5Z"/></svg>
              </button>
              <button class="rounded-lg p-1.5 text-slate-300 hover:text-red-400 dark:text-zinc-700 dark:hover:text-red-400 transition-colors" aria-label="Hapus habit" @click="confirmDelete(habit)">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
              </button>
            </div>
          </div>

          <!-- Heatmap toggle -->
          <div v-if="expandedId === habit.id" class="mt-3 pt-3 border-t border-slate-100 dark:border-zinc-800">
            <p class="text-xs font-extrabold uppercase text-slate-400 dark:text-zinc-500 mb-2">Last 90 days</p>
            <div class="flex flex-wrap gap-0.5">
              <div
                v-for="cell in getHeatmap(habit)"
                :key="cell.date"
                :class="['w-3 h-3 rounded-sm', cell.done ? '' : 'bg-slate-100 dark:bg-zinc-800']"
                :style="cell.done ? { background: habit.color } : {}"
                :title="cell.date"
              ></div>
            </div>
          </div>
          <button class="mt-2 text-xs font-semibold text-slate-400 hover:text-teal-600 dark:hover:text-teal-400 transition-colors" @click="expandedId = expandedId === habit.id ? null : habit.id">
            {{ expandedId === habit.id ? '▲ Hide heatmap' : '▼ View 90-day heatmap' }}
          </button>
        </div>
      </div>
    </template>

    <!-- Form modal -->
    <div v-if="showForm" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 px-4" @click.self="closeForm">
      <div class="bg-white dark:bg-zinc-900 rounded-2xl shadow-2xl w-full max-w-md p-6 space-y-4 border border-slate-200 dark:border-zinc-700">
        <h2 class="text-lg font-extrabold text-slate-900 dark:text-zinc-100">{{ editingHabit ? 'Edit Habit' : 'New Habit' }}</h2>

        <label class="block">
          <span class="label mb-1">Name *</span>
          <input v-model="form.name" class="field" placeholder="E.g. Exercise 30 minutes" />
        </label>

        <label class="block">
          <span class="label mb-1">Description</span>
          <input v-model="form.description" class="field" placeholder="Optional" />
        </label>

        <div class="flex gap-3">
          <label class="block flex-1">
            <span class="label mb-1">Icon</span>
            <input v-model="form.icon" class="field" placeholder="✓" maxlength="4" />
          </label>
          <div class="flex-1">
            <span class="label mb-2 block">Color</span>
            <div class="flex gap-2 flex-wrap pt-1">
              <button
                v-for="c in colorOptions"
                :key="c"
                :class="['w-6 h-6 rounded-full border-2 transition-transform', form.color === c ? 'border-slate-900 dark:border-white scale-110' : 'border-transparent']"
                :style="{ background: c }"
                @click="form.color = c"
              ></button>
            </div>
          </div>
        </div>

        <label class="block">
          <span class="label mb-1">Frequency</span>
          <select v-model="form.frequency" class="field">
            <option value="daily">Daily</option>
            <option value="weekly">Weekly</option>
          </select>
        </label>

        <p v-if="formError" class="text-sm font-semibold text-red-600">{{ formError }}</p>

        <div class="flex justify-end gap-2 pt-1">
          <button class="btn btn-muted" @click="closeForm">Cancel</button>
          <button class="btn btn-primary" :disabled="saving" @click="submitForm">
            {{ saving ? 'Saving...' : (editingHabit ? 'Save' : 'Create') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
