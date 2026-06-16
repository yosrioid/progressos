<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { api, unwrap } from '../api';
import { toast } from '../feedback';

type Level = 'beginner' | 'intermediate' | 'expert' | 'daily' | 'custom';
type CellState = 'hidden' | 'flagged' | 'question' | 'revealed';
type GameState = 'menu' | 'playing' | 'won' | 'lost';

const PRESETS: Record<string, { rows: number; cols: number; mines: number; label: string; desc: string }> = {
  beginner:     { rows: 9,  cols: 9,  mines: 10, label: 'Beginner',    desc: '9×9 · 10 mines' },
  intermediate: { rows: 16, cols: 16, mines: 40, label: 'Intermediate',  desc: '16×16 · 40 mines' },
  expert:       { rows: 16, cols: 30, mines: 99, label: 'Expert',        desc: '16×30 · 99 mines' },
  daily:        { rows: 16, cols: 16, mines: 40, label: 'Daily',         desc: 'Same puzzle every day' },
};

const NUM_CLASSES = [
  '',
  'text-blue-700 dark:text-blue-400',
  'text-green-700 dark:text-green-400',
  'text-red-600 dark:text-red-400',
  'text-blue-900 dark:text-blue-300',
  'text-red-900 dark:text-red-300',
  'text-cyan-700 dark:text-cyan-400',
  'text-purple-800 dark:text-purple-400',
  'text-slate-500 dark:text-slate-500',
];

// ── State ──────────────────────────────────────────────────────────────────────
const gameState  = ref<GameState>('menu');
const level      = ref<Level>('beginner');
const rows       = ref(9);
const cols       = ref(9);
const mineCount  = ref(10);

const mines      = ref<boolean[]>([]);
const adjCounts  = ref<number[]>([]);
const cellStates = ref<CellState[]>([]);
const hitIdx     = ref(-1);

const customRows   = ref(16);
const customCols   = ref(16);
const customMines  = ref(40);
const flagMode     = ref(false);
const firstDone    = ref(false);
const mouseOnCell  = ref(false);
const completing   = ref(false);
const loading      = ref(false);
const saving       = ref(false);

const elapsedSeconds = ref(0);
const isPaused       = ref(false);
let   timerInterval: ReturnType<typeof setInterval> | null = null;
let   saveTimeout:   ReturnType<typeof setTimeout>  | null = null;

const sessionId        = ref<number | null>(null);
const levelKey         = ref<string>('beginner');
const completionRecord = ref<any>(null);
const completionRank   = ref<number | null>(null);
const gameStats        = ref({ time: 0 });

const records     = ref<Record<string, any[]>>({});
const totals      = ref<Record<string, number>>({});
const dailyStatus = ref<any>(null);
const pendingResume = ref<any>(null);

// ── Computed ───────────────────────────────────────────────────────────────────
const total        = computed(() => rows.value * cols.value);
const flagCount    = computed(() => cellStates.value.filter(s => s === 'flagged').length);
const remaining    = computed(() => mineCount.value - flagCount.value);
const revealedCount = computed(() => cellStates.value.filter(s => s === 'revealed').length);
const progressPct  = computed(() => {
  const safe = total.value - mineCount.value;
  return safe > 0 ? Math.round(revealedCount.value / safe * 100) : 0;
});

const face = computed(() => {
  if (gameState.value === 'won') return '😎';
  if (gameState.value === 'lost') return '😵';
  if (mouseOnCell.value && gameState.value === 'playing') return '😮';
  return '🙂';
});

const cellPx = computed(() => {
  if (cols.value <= 9)  return 44;
  if (cols.value <= 16) return 36;
  if (cols.value <= 20) return 32;
  return 28;
});

const fontPx = computed(() => {
  if (cellPx.value >= 40) return 18;
  if (cellPx.value >= 32) return 15;
  return 13;
});

// ── Board helpers ──────────────────────────────────────────────────────────────
const toRow = (i: number) => Math.floor(i / cols.value);
const toCol = (i: number) => i % cols.value;

function getNeighbors(idx: number): number[] {
  const r = toRow(idx), c = toCol(idx), out: number[] = [];
  for (let dr = -1; dr <= 1; dr++) {
    for (let dc = -1; dc <= 1; dc++) {
      if (dr === 0 && dc === 0) continue;
      const nr = r + dr, nc = c + dc;
      if (nr >= 0 && nr < rows.value && nc >= 0 && nc < cols.value)
        out.push(nr * cols.value + nc);
    }
  }
  return out;
}

function computeAdjCounts() {
  const counts = new Array(total.value).fill(0);
  for (let i = 0; i < total.value; i++) {
    if (mines.value[i]) continue;
    counts[i] = getNeighbors(i).filter(n => mines.value[n]).length;
  }
  adjCounts.value = counts;
}

function initBoard(puzzle: any, userState?: any) {
  rows.value  = puzzle.rows;
  cols.value  = puzzle.cols;
  mineCount.value = puzzle.mine_count;
  mines.value = [...puzzle.mines];
  computeAdjCounts();

  if (userState) {
    const rev   = userState.revealed  ?? new Array(total.value).fill(false);
    const flags = userState.flags     ?? new Array(total.value).fill(false);
    const qs    = userState.questions ?? new Array(total.value).fill(false);
    cellStates.value = rev.map((_: boolean, i: number) =>
      rev[i] ? 'revealed' : flags[i] ? 'flagged' : qs[i] ? 'question' : 'hidden'
    );
    firstDone.value = true;
  } else {
    cellStates.value = new Array(total.value).fill('hidden');
    firstDone.value  = false;
  }

  hitIdx.value = -1;
}

// ── Reveal / cascade ───────────────────────────────────────────────────────────
function reveal(idx: number) {
  if (gameState.value !== 'playing' || isPaused.value) return;
  if (cellStates.value[idx] !== 'hidden' && cellStates.value[idx] !== 'question') return;

  if (!firstDone.value) {
    if (mines.value[idx]) {
      const safe = mines.value.map((m, i) => !m && i !== idx ? i : -1).filter(i => i >= 0);
      if (safe.length > 0) {
        const swap = safe[Math.floor(Math.random() * safe.length)];
        mines.value[idx]  = false;
        mines.value[swap] = true;
        computeAdjCounts();
      }
    }
    firstDone.value = true;
    startTimer();
  }

  if (mines.value[idx]) {
    cellStates.value[idx] = 'revealed';
    hitIdx.value = idx;
    handleLoss();
    return;
  }

  // BFS cascade
  const queue   = [idx];
  const visited = new Set<number>();
  while (queue.length > 0) {
    const cur = queue.shift()!;
    if (visited.has(cur)) continue;
    visited.add(cur);
    if (cellStates.value[cur] !== 'hidden' && cellStates.value[cur] !== 'question') continue;
    cellStates.value[cur] = 'revealed';
    if (adjCounts.value[cur] === 0)
      for (const n of getNeighbors(cur))
        if (!visited.has(n) && (cellStates.value[n] === 'hidden' || cellStates.value[n] === 'question'))
          queue.push(n);
  }

  scheduleSave();
  checkWin();
}

function toggleFlag(idx: number) {
  if (gameState.value !== 'playing' || isPaused.value) return;
  const cur = cellStates.value[idx];
  if (cur === 'revealed') return;
  cellStates.value[idx] = cur === 'hidden' ? 'flagged' : cur === 'flagged' ? 'question' : 'hidden';
  scheduleSave();
}

function chord(idx: number) {
  if (gameState.value !== 'playing' || isPaused.value) return;
  if (cellStates.value[idx] !== 'revealed') return;
  if (adjCounts.value[idx] === 0) return;
  const nb = getNeighbors(idx);
  if (nb.filter(n => cellStates.value[n] === 'flagged').length !== adjCounts.value[idx]) return;
  for (const n of nb)
    if (cellStates.value[n] === 'hidden' || cellStates.value[n] === 'question')
      reveal(n);
}

function checkWin() {
  if (revealedCount.value === total.value - mineCount.value) handleWin();
}

// ── Win / loss ─────────────────────────────────────────────────────────────────
async function handleWin() {
  if (completing.value) return;
  completing.value = true;
  stopTimer();
  gameStats.value = { time: elapsedSeconds.value };
  mines.value.forEach((m, i) => { if (m) cellStates.value[i] = 'flagged'; });

  try {
    const data: any = await api.post(
      `/api/v1/games/minesweeper/sessions/${sessionId.value}/complete`,
      { elapsed_seconds: elapsedSeconds.value, outcome: 'won' }
    ).then(unwrap);
    completionRecord.value = data.record;
    completionRank.value   = data.rank;
    await loadRecords();
    if (levelKey.value === 'daily') await loadDailyStatus();
  } catch {
    toast({ tone: 'error', title: 'Failed to save result' });
  } finally {
    completing.value = false;
  }
  gameState.value = 'won';
}

async function handleLoss() {
  stopTimer();
  gameStats.value = { time: elapsedSeconds.value };
  mines.value.forEach((m, i) => {
    if (m && cellStates.value[i] !== 'flagged') cellStates.value[i] = 'revealed';
  });
  gameState.value = 'lost';
  try {
    await api.post(`/api/v1/games/minesweeper/sessions/${sessionId.value}/complete`, {
      elapsed_seconds: elapsedSeconds.value,
      outcome: 'lost',
    });
  } catch { /* ignore */ }
}

// ── Timer ──────────────────────────────────────────────────────────────────────
function startTimer() {
  stopTimer();
  timerInterval = setInterval(() => { elapsedSeconds.value++; }, 1000);
}
function stopTimer() {
  if (timerInterval) { clearInterval(timerInterval); timerInterval = null; }
}
function togglePause() {
  if (gameState.value !== 'playing') return;
  isPaused.value = !isPaused.value;
  isPaused.value ? (stopTimer(), saveProgress()) : startTimer();
}
function formatTime(s: number) {
  const m = Math.floor(s / 60).toString().padStart(2, '0');
  return `${m}:${(s % 60).toString().padStart(2, '0')}`;
}

// ── Save ───────────────────────────────────────────────────────────────────────
function scheduleSave() {
  if (saveTimeout) clearTimeout(saveTimeout);
  saveTimeout = setTimeout(saveProgress, 5000);
}

async function saveProgress() {
  if (!sessionId.value || saving.value || gameState.value === 'won' || gameState.value === 'lost') return;
  saving.value = true;
  try {
    const rev = cellStates.value.map(s => s === 'revealed');
    const flags = cellStates.value.map(s => s === 'flagged');
    const qs = cellStates.value.map(s => s === 'question');
    await api.patch(`/api/v1/games/minesweeper/sessions/${sessionId.value}`, {
      user_state:      { revealed: rev, flags, questions: qs },
      elapsed_seconds: elapsedSeconds.value,
      status:          isPaused.value ? 'paused' : 'active',
    });
  } finally {
    saving.value = false;
  }
}

async function abandonSession(id: number) {
  try {
    await api.patch(`/api/v1/games/minesweeper/sessions/${id}`, {
      elapsed_seconds: 0,
      status: 'abandoned',
    });
  } catch { /* ignore */ }
}

// ── Start / apply session ─────────────────────────────────────────────────────
function applySession(session: any) {
  sessionId.value      = session.id;
  levelKey.value       = session.level;
  elapsedSeconds.value = session.elapsed_seconds ?? 0;
  isPaused.value       = session.status === 'paused';
  completing.value     = false;
  initBoard(session.puzzle, session.user_state ?? null);
  gameState.value = 'playing';
  if (!isPaused.value && firstDone.value) startTimer();
}

async function startNewGame(chosenLevel: Level) {
  pendingResume.value = null;
  loading.value = true;

  const payload: any = { level: chosenLevel };
  if (chosenLevel === 'custom') {
    payload.rows       = customRows.value;
    payload.cols       = customCols.value;
    payload.mine_count = customMines.value;
  }

  try {
    const data: any = await api.post('/api/v1/games/minesweeper/sessions', payload).then(unwrap);
    applySession(data.session);
    flagMode.value = false;
  } catch {
    toast({ tone: 'error', title: 'Failed to start', message: 'Try again.' });
  } finally {
    loading.value = false;
  }
}

async function startDaily() {
  if (dailyStatus.value?.completed_today) return;
  if (dailyStatus.value?.session) { pendingResume.value = null; applySession(dailyStatus.value.session); return; }
  await startNewGame('daily');
}

function resumeSession() {
  if (!pendingResume.value) return;
  applySession(pendingResume.value);
  pendingResume.value = null;
  flagMode.value = false;
}

async function dismissResume() {
  if (!pendingResume.value) return;
  const id = pendingResume.value.id;
  pendingResume.value = null;
  await abandonSession(id);
}

function backToMenu() {
  stopTimer();
  if (saveTimeout) clearTimeout(saveTimeout);
  if (gameState.value === 'playing') saveProgress();
  gameState.value = 'menu';
  completionRecord.value = null;
  completionRank.value   = null;
}

async function exitWithoutSave() {
  stopTimer();
  if (saveTimeout) clearTimeout(saveTimeout);
  if (sessionId.value) await abandonSession(sessionId.value);
  gameState.value = 'menu';
  completionRecord.value = null;
  completionRank.value   = null;
}

// ── Load data ─────────────────────────────────────────────────────────────────
async function loadActive() {
  try {
    const data: any = await api.get('/api/v1/games/minesweeper/active').then(unwrap);
    if (data.session) pendingResume.value = data.session;
  } catch { /* ignore */ }
}

async function loadDailyStatus() {
  try {
    const data: any = await api.get('/api/v1/games/minesweeper/daily').then(unwrap);
    dailyStatus.value = data;
  } catch { /* ignore */ }
}

async function loadRecords() {
  try {
    const data: any = await api.get('/api/v1/games/minesweeper/records').then(unwrap);
    records.value = data.records ?? {};
    totals.value  = data.totals  ?? {};
  } catch { /* ignore */ }
}

// ── Cell display ───────────────────────────────────────────────────────────────
function cellClass(idx: number) {
  const state   = cellStates.value[idx];
  const isMine  = mines.value[idx];
  const isLost  = gameState.value === 'lost';
  const isHit   = hitIdx.value === idx;

  const base = 'relative flex items-center justify-center select-none';

  if (isLost && isMine && state !== 'flagged') {
    return isHit
      ? `${base} bg-red-500 dark:bg-red-600 cursor-default`
      : `${base} bg-slate-300 dark:bg-zinc-600 cursor-default`;
  }
  if (state === 'revealed') {
    return `${base} bg-slate-200 dark:bg-zinc-700 cursor-default border border-slate-300/60 dark:border-zinc-600/60`;
  }
  return `${base} bg-slate-400 hover:brightness-105 active:brightness-95 dark:bg-zinc-500 cursor-pointer shadow-[inset_2px_2px_0_rgba(255,255,255,0.65),inset_-2px_-2px_0_rgba(0,0,0,0.3)] dark:shadow-[inset_2px_2px_0_rgba(255,255,255,0.12),inset_-2px_-2px_0_rgba(0,0,0,0.5)]`;
}

function cellContent(idx: number): string {
  const state  = cellStates.value[idx];
  const isMine = mines.value[idx];
  const isLost = gameState.value === 'lost';

  if (state === 'flagged') {
    if (isLost && !isMine) return '✗'; // wrong flag
    return '🚩';
  }
  if (state === 'question') return '?';
  if (state === 'revealed') {
    if (isMine) return '💣';
    return adjCounts.value[idx] > 0 ? String(adjCounts.value[idx]) : '';
  }
  return '';
}

function contentClass(idx: number): string {
  const state = cellStates.value[idx];
  const n     = adjCounts.value[idx];
  if (state === 'revealed' && !mines.value[idx] && n > 0)
    return `font-black ${NUM_CLASSES[n] ?? 'text-slate-600'}`;
  if (state === 'flagged' && gameState.value === 'lost' && !mines.value[idx])
    return 'font-black text-red-600 dark:text-red-400';
  if (state === 'question') return 'font-black text-orange-600 dark:text-orange-400';
  return '';
}

function handleClick(idx: number) {
  if (gameState.value !== 'playing' || isPaused.value) return;
  if (flagMode.value) { toggleFlag(idx); return; }
  reveal(idx);
}

function handleRightClick(idx: number) {
  if (gameState.value !== 'playing' || isPaused.value) return;
  toggleFlag(idx);
}

function handleDblClick(idx: number) {
  chord(idx);
}

function levelPillClass(lv: string) {
  if (lv === 'beginner')     return 'border border-teal-200 bg-teal-50 text-teal-700 dark:border-teal-700/40 dark:bg-teal-900/20 dark:text-teal-400';
  if (lv === 'intermediate') return 'border border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-700/40 dark:bg-sky-900/20 dark:text-sky-400';
  if (lv === 'expert')       return 'border border-red-200 bg-red-50 text-red-700 dark:border-red-700/40 dark:bg-red-900/20 dark:text-red-400';
  if (lv === 'daily')        return 'border border-indigo-200 bg-indigo-50 text-indigo-700 dark:border-indigo-700/40 dark:bg-indigo-900/20 dark:text-indigo-400';
  if (lv === 'custom')       return 'border border-orange-200 bg-orange-50 text-orange-700 dark:border-orange-700/40 dark:bg-orange-900/20 dark:text-orange-400';
  return 'pill-slate';
}

function parseLevelLabel(lv: string) {
  if (PRESETS[lv]) return PRESETS[lv].label;
  if (lv.startsWith('custom_')) {
    const m = lv.match(/custom_(\d+)x(\d+)_(\d+)/);
    return m ? `${m[1]}×${m[2]} · ${m[3]}` : 'Custom';
  }
  return lv;
}

const totalWins = computed(() =>
  ['beginner', 'intermediate', 'expert', 'daily'].reduce((s, l) => s + (totals.value[l] || 0), 0)
);

// ── Lifecycle ─────────────────────────────────────────────────────────────────
onMounted(() => {
  loadActive();
  loadDailyStatus();
  loadRecords();
});

onUnmounted(() => {
  stopTimer();
  if (saveTimeout) clearTimeout(saveTimeout);
});
</script>

<template>
  <div class="mx-auto max-w-2xl space-y-4">
    <!-- Header -->
    <div class="flex items-center gap-3">
      <RouterLink
        to="/games"
        class="grid h-8 w-8 shrink-0 place-items-center rounded-xl border border-slate-200 bg-white text-slate-500 hover:border-teal-200 hover:text-teal-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400"
      >
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
      </RouterLink>
      <div>
        <p class="text-xs font-extrabold uppercase text-teal-700">Games</p>
        <h1 class="text-xl font-extrabold tracking-tight">Minesweeper</h1>
      </div>
    </div>

    <!-- ── MENU ── -->
    <template v-if="gameState === 'menu'">

      <!-- Resume banner -->
      <div v-if="pendingResume" class="card flex items-center justify-between gap-3 border-teal-200 bg-teal-50/60 p-4 dark:border-teal-700/60 dark:bg-teal-900/10">
        <div class="min-w-0">
          <p class="text-xs font-extrabold uppercase text-teal-700">Saved session</p>
          <p class="mt-0.5 text-sm font-bold text-slate-700 dark:text-slate-200">
            {{ parseLevelLabel(pendingResume.level) }}
            <span class="font-mono font-semibold text-slate-500"> · {{ formatTime(pendingResume.elapsed_seconds) }}</span>
          </p>
        </div>
        <div class="flex shrink-0 gap-2">
          <button class="btn btn-primary py-1.5 px-3 text-sm" @click="resumeSession">Resume</button>
          <button class="btn btn-muted py-1.5 px-3 text-sm" @click="dismissResume">Discard</button>
        </div>
      </div>

      <!-- Daily challenge -->
      <div class="card overflow-hidden p-0">
        <div class="flex items-center gap-3 border-b border-slate-100 bg-gradient-to-r from-indigo-50 to-slate-50 px-5 py-3.5 dark:border-slate-700 dark:from-indigo-900/20 dark:to-slate-800/50">
          <svg class="h-5 w-5 shrink-0 text-indigo-500" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><rect width="18" height="18" x="3" y="4" rx="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
          <p class="text-sm font-extrabold text-indigo-800 dark:text-indigo-300">Daily Challenge</p>
        </div>
        <div class="flex items-center justify-between gap-3 px-5 py-4">
          <div class="min-w-0">
            <p class="text-xs font-medium text-slate-500">16×16 · 40 mines. Same puzzle every day.</p>
            <div class="mt-2">
              <template v-if="dailyStatus?.completed_today">
                <div class="flex items-center gap-2">
                  <span class="flex h-5 w-5 items-center justify-center rounded-full bg-teal-500">
                    <svg class="h-3 w-3 text-white" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>
                  </span>
                  <span class="text-sm font-bold text-teal-700 dark:text-teal-400">
                    Completed today
                    <span v-if="dailyStatus.record" class="font-mono"> — {{ formatTime(dailyStatus.record.duration_seconds) }}</span>
                  </span>
                </div>
              </template>
              <template v-else-if="dailyStatus?.session">
                <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">In progress · <span class="font-mono">{{ formatTime(dailyStatus.session.elapsed_seconds) }}</span></p>
              </template>
              <template v-else>
                <p class="text-sm font-semibold text-slate-400">Not started today</p>
              </template>
            </div>
          </div>
          <button
            v-if="!dailyStatus?.completed_today"
            class="shrink-0 rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm font-bold text-indigo-700 hover:bg-indigo-100 dark:border-indigo-700 dark:bg-indigo-900/20 dark:text-indigo-400"
            @click="startDaily"
          >{{ dailyStatus?.session ? 'Resume' : 'Start' }}</button>
          <div v-else class="shrink-0 text-2xl">🏅</div>
        </div>
      </div>

      <!-- Level selection -->
      <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
        <button
          v-for="(preset, lv) in PRESETS"
          :key="lv"
          v-if="lv !== 'daily'"
          class="card flex flex-col gap-2 p-4 text-left transition hover:border-teal-300"
          :class="level === lv ? 'border-teal-400 ring-1 ring-teal-300' : ''"
          @click="level = lv as Level"
        >
          <span class="pill text-xs" :class="levelPillClass(lv)">{{ preset.label }}</span>
          <p class="text-xs font-medium text-slate-500">{{ preset.desc }}</p>
          <p v-if="totals[lv] > 0" class="text-xs font-bold text-slate-400">{{ totals[lv] }}× menang</p>
        </button>
        <button
          class="card flex flex-col gap-2 p-4 text-left transition hover:border-teal-300"
          :class="level === 'custom' ? 'border-teal-400 ring-1 ring-teal-300' : ''"
          @click="level = 'custom'"
        >
          <span class="pill text-xs" :class="levelPillClass('custom')">Custom</span>
          <p class="text-xs font-medium text-slate-500">Custom size & mines</p>
        </button>
      </div>

      <!-- Custom config -->
      <div v-if="level === 'custom'" class="card grid gap-4 p-5 sm:grid-cols-3">
        <label>
          <span class="label mb-1.5">Rows (5–30)</span>
          <input v-model.number="customRows" class="field" type="number" min="5" max="30" />
        </label>
        <label>
          <span class="label mb-1.5">Columns (5–50)</span>
          <input v-model.number="customCols" class="field" type="number" min="5" max="50" />
        </label>
        <label>
          <span class="label mb-1.5">Mines</span>
          <input v-model.number="customMines" class="field" type="number" :min="1" :max="customRows * customCols - 9" />
        </label>
        <p class="text-xs font-semibold text-slate-400 sm:col-span-3">
          {{ customRows }}×{{ customCols }} = {{ customRows * customCols }} cells ·
          {{ Math.round(customMines / (customRows * customCols) * 100) }}% mines
        </p>
      </div>

      <button
        class="btn btn-primary w-full py-3 text-base"
        :disabled="loading"
        @click="startNewGame(level)"
      >
        {{ loading ? 'Setting up board...' : `Start — ${level === 'custom' ? 'Custom' : PRESETS[level]?.label}` }}
      </button>

      <!-- Records -->
      <div v-if="Object.values(records).some(r => r.length)" class="card overflow-hidden p-0">
        <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/70 px-5 py-3 dark:border-slate-700 dark:bg-slate-800/50">
          <p class="text-xs font-extrabold uppercase text-teal-700">Personal Records</p>
          <p v-if="totalWins > 0" class="text-xs font-bold text-slate-400">{{ totalWins }} total wins</p>
        </div>
        <div class="divide-y divide-slate-100 dark:divide-slate-700">
          <div v-for="lv in ['beginner','intermediate','expert','daily']" :key="lv">
            <div v-if="records[lv]?.length" class="px-5 py-3">
              <div class="mb-2 flex items-center gap-2">
                <span class="pill text-xs" :class="levelPillClass(lv)">{{ parseLevelLabel(lv) }}</span>
                <span class="text-xs font-bold text-slate-400">{{ totals[lv] }}× wins</span>
              </div>
              <div class="space-y-1">
                <div v-for="(rec, idx) in records[lv]" :key="rec.id" class="flex items-center gap-3 text-sm">
                  <span class="w-5 text-center font-black" :class="idx === 0 ? 'text-yellow-500' : 'text-slate-400'">#{{ idx + 1 }}</span>
                  <span class="font-mono font-bold">{{ formatTime(rec.duration_seconds) }}</span>
                  <span class="text-xs text-slate-400">{{ rec.completed_at?.slice(0, 10) }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- ── PLAYING + LOST (board stays visible on loss) ── -->
    <template v-else-if="gameState === 'playing' || gameState === 'lost'">

      <!-- Game header -->
      <div class="card flex items-center justify-between gap-3 px-4 py-3" :class="gameState === 'lost' ? 'border-red-200 dark:border-red-800/40' : ''">
        <!-- Mine counter -->
        <div class="flex items-center gap-1.5">
          <span class="text-lg">💣</span>
          <span class="min-w-[2.5rem] font-mono text-xl font-black tabular-nums" :class="remaining < 0 ? 'text-red-500' : ''">{{ remaining }}</span>
        </div>

        <!-- Smiley + progress -->
        <div class="flex flex-1 flex-col items-center gap-1.5">
          <button
            class="text-2xl leading-none transition hover:scale-110 active:scale-95"
            title="Restart"
            aria-label="Restart game"
            @click="startNewGame(levelKey.startsWith('custom') ? 'custom' : levelKey as Level)"
          >{{ face }}</button>
          <div class="h-1 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
            <div class="h-full rounded-full bg-teal-500 transition-all duration-300" :style="{ width: progressPct + '%' }"></div>
          </div>
        </div>

        <!-- Timer + controls -->
        <div class="flex items-center gap-2">
          <span v-if="saving" class="text-xs text-slate-400">↑</span>
          <span class="font-mono text-sm font-black tabular-nums sm:text-base">{{ formatTime(elapsedSeconds) }}</span>
          <button
            class="grid h-8 w-8 place-items-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:border-teal-200 hover:text-teal-700 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-300"
            :title="isPaused ? 'Resume' : 'Pause'"
            :aria-label="isPaused ? 'Resume game' : 'Pause game'"
            @click="togglePause"
          >
            <svg v-if="isPaused" class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
            <svg v-else class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
          </button>
          <!-- Flag mode (mobile) -->
          <button
            class="grid h-8 w-8 place-items-center rounded-xl border transition text-sm"
            :class="flagMode ? 'border-teal-400 bg-teal-50 text-teal-700 dark:border-teal-600 dark:bg-teal-900/30 dark:text-teal-400' : 'border-slate-200 bg-white text-slate-500 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-400'"
            title="Flag mode"
            aria-label="Toggle flag mode"
            @click="flagMode = !flagMode"
          >🚩</button>
          <button
            class="grid h-8 w-8 place-items-center rounded-xl border border-red-200 bg-red-50 text-red-600 transition hover:bg-red-100 dark:border-red-800/40 dark:bg-red-900/20 dark:text-red-400"
            title="Exit"
            aria-label="Exit without saving"
            @click="exitWithoutSave"
          >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          </button>
        </div>
      </div>

      <!-- Pause overlay -->
      <div v-if="isPaused" class="card flex flex-col items-center gap-5 py-14 text-center">
        <div class="rounded-2xl bg-slate-100 p-5 dark:bg-slate-700/50">
          <svg class="h-10 w-10 text-slate-500" fill="currentColor" viewBox="0 0 24 24"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
        </div>
        <div>
          <p class="text-xl font-black">Paused</p>
          <p class="mt-1 text-sm text-slate-500">Board is hidden to keep it fair.</p>
        </div>
        <button class="btn btn-primary px-8" @click="togglePause">Resume</button>
      </div>

      <!-- Board -->
      <div v-if="!isPaused" class="overflow-x-auto rounded-2xl" :class="gameState === 'lost' ? 'opacity-90' : ''">
        <div
          class="mx-auto border-4 dark:border-zinc-500"
          :class="gameState === 'lost' ? 'border-red-400 dark:border-red-700' : 'border-slate-500'"
          :style="{ display: 'grid', gridTemplateColumns: `repeat(${cols}, ${cellPx}px)`, width: `${cols * cellPx}px` }"
        >
          <div
            v-for="idx in total"
            :key="idx - 1"
            :class="cellClass(idx - 1)"
            :style="{ width: cellPx + 'px', height: cellPx + 'px', fontSize: fontPx + 'px' }"
            @click="handleClick(idx - 1)"
            @contextmenu.prevent="handleRightClick(idx - 1)"
            @dblclick="handleDblClick(idx - 1)"
            @mousedown="gameState === 'playing' && (mouseOnCell = true)"
            @mouseup="mouseOnCell = false"
            @mouseleave="mouseOnCell = false"
          >
            <span :class="contentClass(idx - 1)">{{ cellContent(idx - 1) }}</span>
          </div>
        </div>
      </div>

      <!-- Mobile hint (playing only) -->
      <p v-if="gameState === 'playing'" class="hidden text-center text-xs font-medium text-slate-400 sm:block">
        Left click: reveal · Right click: flag/question · Double click: chord
      </p>

      <!-- ── LOST overlay card (below the board) ── -->
      <div v-if="gameState === 'lost'" class="card border-red-200 bg-red-50/60 p-5 dark:border-red-800/40 dark:bg-red-900/10">
        <div class="flex items-center gap-4">
          <span class="text-4xl">💥</span>
          <div class="flex-1">
            <h2 class="text-lg font-black text-red-600 dark:text-red-400">Mine Hit!</h2>
            <p class="text-sm font-semibold text-slate-500">
              Time: <span class="font-mono font-black">{{ formatTime(gameStats.time) }}</span>
              · Level: <span class="font-bold">{{ parseLevelLabel(levelKey) }}</span>
              · Mines: <span class="font-bold">{{ mineCount }}</span>
            </p>
          </div>
          <div class="flex shrink-0 gap-2">
            <button
              class="btn btn-primary"
              @click="startNewGame(levelKey.startsWith('custom') ? 'custom' : levelKey as Level)"
            >Try again</button>
            <button class="btn btn-muted" @click="backToMenu">Menu</button>
          </div>
        </div>
      </div>
    </template>

    <!-- ── WON ── -->
    <template v-else-if="gameState === 'won'">
      <div class="card flex flex-col items-center gap-5 py-10 text-center">
        <div class="rounded-full bg-teal-50 p-5 dark:bg-teal-900/20">
          <span class="text-5xl">🏆</span>
        </div>
        <div>
          <h2 class="text-2xl font-black">Done!</h2>
          <p class="mt-1 text-sm font-medium text-slate-500">Level {{ parseLevelLabel(levelKey) }}</p>
        </div>
        <div>
          <span class="font-mono text-5xl font-black tabular-nums text-teal-600">{{ formatTime(gameStats.time) }}</span>
          <div class="mt-2 flex justify-center">
            <span v-if="completionRank === 1" class="pill border border-yellow-200 bg-yellow-50 text-yellow-700 dark:bg-yellow-900/20 dark:text-yellow-400">New record!</span>
            <span v-else-if="completionRank" class="text-sm font-semibold text-slate-500">Rank #{{ completionRank }} personal</span>
          </div>
        </div>

        <!-- Top records -->
        <div v-if="records[levelKey]?.length" class="w-full max-w-xs rounded-2xl border border-slate-100 bg-slate-50 p-4 text-left dark:border-slate-700 dark:bg-slate-800/50">
          <p class="mb-3 text-xs font-extrabold uppercase text-slate-500">Top Records — {{ parseLevelLabel(levelKey) }}</p>
          <div class="space-y-2">
            <div
              v-for="(rec, idx) in records[levelKey].slice(0, 5)"
              :key="rec.id"
              class="flex items-center gap-3 text-sm"
              :class="rec.id === completionRecord?.id ? 'font-black text-teal-700 dark:text-teal-400' : ''"
            >
              <span class="w-5 text-center font-black" :class="idx === 0 ? 'text-yellow-500' : 'text-slate-400'">#{{ idx + 1 }}</span>
              <span class="font-mono">{{ formatTime(rec.duration_seconds) }}</span>
              <span class="text-xs text-slate-400">{{ rec.completed_at?.slice(0, 10) }}</span>
              <span v-if="rec.id === completionRecord?.id" class="ml-auto text-xs font-bold text-teal-600 dark:text-teal-400">new</span>
            </div>
          </div>
        </div>

        <div class="flex gap-3">
          <button class="btn btn-primary" @click="startNewGame(levelKey.startsWith('custom') ? 'custom' : levelKey as Level)">Play again</button>
          <button class="btn btn-muted" @click="backToMenu">Back to menu</button>
        </div>
      </div>
    </template>
  </div>
</template>
