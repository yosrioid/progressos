<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const emit = defineEmits<{ (e: 'log', payload: { title: string; minutes: number }): void }>();

const STORAGE_KEY = 'work_timer';

type TimerMode = 'free' | 'pomodoro' | 'deep';

const running = ref(false);
const startedAt = ref<number | null>(null);
const elapsed = ref(0);
const label = ref('');
const showInput = ref(false);
const mode = ref<TimerMode>('free');
const sessionCount = ref(0);
const isBreak = ref(false);
let ticker: ReturnType<typeof setInterval> | null = null;

const MODES: Record<TimerMode, { work: number; break: number; label: string }> = {
  free: { work: 0, break: 0, label: 'Free' },
  pomodoro: { work: 25 * 60, break: 5 * 60, label: '25/5' },
  deep: { work: 50 * 60, break: 10 * 60, label: '50/10' },
};

const targetSeconds = computed(() => mode.value === 'free' ? 0 : (isBreak.value ? MODES[mode.value].break : MODES[mode.value].work));
const remaining = computed(() => targetSeconds.value > 0 ? Math.max(0, targetSeconds.value - elapsed.value) : null);

function save() {
  localStorage.setItem(STORAGE_KEY, JSON.stringify({
    running: running.value, startedAt: startedAt.value, label: label.value,
    mode: mode.value, sessionCount: sessionCount.value, isBreak: isBreak.value,
  }));
}

function tick() {
  if (startedAt.value) elapsed.value = Math.floor((Date.now() - startedAt.value) / 1000);
  if (remaining.value === 0 && running.value && targetSeconds.value > 0) {
    handlePhaseEnd();
  }
}

function handlePhaseEnd() {
  if (ticker) clearInterval(ticker);
  running.value = false;
  if (!isBreak.value) {
    const mins = Math.round(elapsed.value / 60);
    emit('log', { title: label.value.trim() || 'Pomodoro session', minutes: mins });
    sessionCount.value++;
    isBreak.value = true;
    elapsed.value = 0;
    startedAt.value = Date.now();
    running.value = true;
    ticker = setInterval(tick, 1000);
  } else {
    isBreak.value = false;
    elapsed.value = 0;
    startedAt.value = null;
  }
  save();
}

function start() {
  if (!label.value.trim()) return;
  startedAt.value = Date.now() - elapsed.value * 1000;
  running.value = true;
  showInput.value = false;
  ticker = setInterval(tick, 1000);
  save();
}

function pause() {
  running.value = false;
  if (ticker) clearInterval(ticker);
  save();
}

function stop() {
  if (ticker) clearInterval(ticker);
  const mins = Math.max(1, Math.round(elapsed.value / 60));
  const title = label.value.trim() || 'Work session';
  if (!isBreak.value) emit('log', { title, minutes: mins });
  running.value = false;
  startedAt.value = null;
  elapsed.value = 0;
  label.value = '';
  showInput.value = false;
  isBreak.value = false;
  sessionCount.value = 0;
  localStorage.removeItem(STORAGE_KEY);
}

function discard() {
  if (ticker) clearInterval(ticker);
  running.value = false;
  startedAt.value = null;
  elapsed.value = 0;
  label.value = '';
  showInput.value = false;
  isBreak.value = false;
  sessionCount.value = 0;
  localStorage.removeItem(STORAGE_KEY);
}

function skipBreak() {
  isBreak.value = false;
  elapsed.value = 0;
  startedAt.value = null;
  if (ticker) clearInterval(ticker);
  running.value = false;
  save();
}

const display = computed(() => {
  const secs = remaining.value !== null ? remaining.value : elapsed.value;
  const h = Math.floor(secs / 3600);
  const m = Math.floor((secs % 3600) / 60);
  const s = secs % 60;
  if (h > 0) return `${h}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
  return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
});

const progressPct = computed(() => {
  if (!targetSeconds.value) return null;
  return Math.min(100, (elapsed.value / targetSeconds.value) * 100);
});

onMounted(() => {
  const stored = localStorage.getItem(STORAGE_KEY);
  if (stored) {
    const state = JSON.parse(stored);
    label.value = state.label || '';
    mode.value = state.mode || 'free';
    sessionCount.value = state.sessionCount || 0;
    isBreak.value = state.isBreak || false;
    if (state.running && state.startedAt) {
      startedAt.value = state.startedAt;
      running.value = true;
      tick();
      ticker = setInterval(tick, 1000);
    } else if (state.startedAt) {
      startedAt.value = state.startedAt;
      elapsed.value = Math.floor((Date.now() - state.startedAt) / 1000);
    }
  }
});

onBeforeUnmount(() => { if (ticker) clearInterval(ticker); });
</script>

<template>
  <!-- Idle -->
  <div v-if="!elapsed && !showInput">
    <button
      class="flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-bold text-slate-500 hover:bg-slate-100 hover:text-slate-800 dark:text-zinc-500 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
      title="Start work timer"
      @click="showInput = true"
    >
      <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
      Timer
    </button>
  </div>

  <!-- Label + mode input -->
  <div v-else-if="showInput && !elapsed" class="flex items-center gap-1.5">
    <!-- Mode selector -->
    <div class="flex rounded-lg border border-slate-200 bg-white text-[10px] font-bold overflow-hidden dark:border-zinc-700 dark:bg-zinc-900">
      <button
        v-for="(m, key) in MODES"
        :key="key"
        :class="['px-2 py-1 transition', mode === key ? 'bg-teal-700 text-white' : 'text-slate-500 hover:text-slate-800 dark:text-zinc-500 dark:hover:text-zinc-200']"
        @click="mode = key as TimerMode"
      >{{ m.label }}</button>
    </div>
    <input
      v-model="label"
      class="h-7 w-32 rounded-lg border border-slate-200 bg-white px-2 text-xs font-semibold focus:border-teal-400 focus:outline-none dark:border-zinc-700 dark:bg-zinc-900"
      placeholder="What are you working on?"
      autofocus
      @keydown.enter="start"
      @keydown.esc="showInput = false"
    />
    <button :disabled="!label.trim()" class="rounded-lg bg-teal-700 px-2.5 py-1 text-xs font-bold text-white hover:bg-teal-600 disabled:opacity-40" @click="start">Start</button>
    <button class="rounded-lg px-1.5 py-1 text-xs text-slate-400 hover:text-slate-700 dark:hover:text-zinc-300" @click="showInput = false">✕</button>
  </div>

  <!-- Active / paused timer -->
  <div v-else class="flex items-center gap-1.5">
    <span v-if="mode !== 'free'" :class="['text-[9px] font-extrabold uppercase px-1.5 py-0.5 rounded', isBreak ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400']">
      {{ isBreak ? 'Break' : `#${sessionCount + 1}` }}
    </span>
    <div class="relative">
      <span :class="['min-w-[3rem] rounded-lg bg-slate-100 px-2 py-1 text-center text-xs font-extrabold tabular-nums block dark:bg-zinc-800', running ? (isBreak ? 'text-green-600 dark:text-green-400' : 'text-teal-700 dark:text-teal-400') : 'text-slate-500 dark:text-zinc-400']">
        {{ display }}
      </span>
      <svg v-if="progressPct !== null" class="absolute inset-0 pointer-events-none" width="100%" height="100%" viewBox="0 0 100 100" preserveAspectRatio="none">
        <rect x="0" y="0" width="100" height="100" rx="6" fill="none" :stroke="isBreak ? '#22c55e' : '#6366f1'" stroke-width="2" stroke-dasharray="400" :stroke-dashoffset="400 - progressPct * 4" opacity="0.4"/>
      </svg>
    </div>
    <span v-if="!isBreak && label" class="max-w-[6rem] truncate text-xs font-semibold text-slate-500 dark:text-zinc-400">{{ label }}</span>
    <button v-if="isBreak" class="rounded-lg px-2 py-1 text-[10px] font-bold text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-zinc-800" @click="skipBreak">Skip</button>
    <button v-if="running" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-zinc-800" title="Pause" @click="pause">
      <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 24 24"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>
    </button>
    <button v-else class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-teal-700 dark:hover:bg-zinc-800" title="Resume" @click="start">
      <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 24 24"><polygon points="5,3 19,12 5,21"/></svg>
    </button>
    <button v-if="!isBreak" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-teal-700 dark:hover:bg-zinc-800" title="Stop & log" @click="stop">
      <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="M9 11 3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
    </button>
    <button class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-red-500 dark:hover:bg-zinc-800" title="Discard" @click="discard">
      <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
    </button>
  </div>
</template>
