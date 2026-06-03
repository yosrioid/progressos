<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { api, unwrap } from '../api';
import { toast } from '../feedback';

type Level = 'easy' | 'medium' | 'hard';
type GameState = 'menu' | 'playing' | 'complete';
type Grid = (number | null)[][];
type Notes = number[][][];

const LEVEL_LABELS: Record<Level, string> = { easy: 'Mudah', medium: 'Sedang', hard: 'Sulit' };
const LEVEL_DESC: Record<Level, string> = {
  easy: '~40 angka terisi — cocok untuk pemanasan.',
  medium: '~32 angka terisi — butuh lebih banyak deduksi.',
  hard: '~26 angka terisi — tantangan penuh.',
};

const gameState = ref<GameState>('menu');
const sessionId = ref<number | null>(null);
const level = ref<Level>('easy');
const puzzle = ref<Grid>([]);
const solution = ref<Grid>([]);
const userGrid = ref<Grid>([]);
const notes = ref<Notes>([]);
const selectedRow = ref<number | null>(null);
const selectedCol = ref<number | null>(null);
const elapsedSeconds = ref(0);
const isPaused = ref(false);
const isNoteMode = ref(false);
const loading = ref(false);
const saving = ref(false);
const records = ref<Record<Level, any[]>>({ easy: [], medium: [], hard: [] });
const completionRecord = ref<any>(null);
const completionRank = ref<number | null>(null);
const wasPlayingBeforeHide = ref(false);

let timerInterval: ReturnType<typeof setInterval> | null = null;
let saveTimeout: ReturnType<typeof setTimeout> | null = null;

// ── Timer ──────────────────────────────────────────────────────────────

function startTimer() {
  stopTimer();
  timerInterval = setInterval(() => { elapsedSeconds.value++; }, 1000);
}

function stopTimer() {
  if (timerInterval) { clearInterval(timerInterval); timerInterval = null; }
}

function formatTime(sec: number): string {
  const m = Math.floor(sec / 60).toString().padStart(2, '0');
  const s = (sec % 60).toString().padStart(2, '0');
  return `${m}:${s}`;
}

// ── Grid helpers ───────────────────────────────────────────────────────

function emptyGrid(): Grid {
  return Array.from({ length: 9 }, () => Array(9).fill(null));
}

function emptyNotes(): Notes {
  return Array.from({ length: 9 }, () => Array.from({ length: 9 }, () => []));
}

function isGiven(r: number, c: number): boolean {
  return puzzle.value[r]?.[c] !== null && puzzle.value[r]?.[c] !== undefined;
}

function cellValue(r: number, c: number): number | null {
  if (isGiven(r, c)) return puzzle.value[r][c];
  return userGrid.value[r]?.[c] ?? null;
}

function isWrong(r: number, c: number): boolean {
  if (isGiven(r, c)) return false;
  const v = userGrid.value[r]?.[c];
  return v !== null && v !== solution.value[r]?.[c];
}

const selectedValue = computed<number | null>(() => {
  if (selectedRow.value === null || selectedCol.value === null) return null;
  return cellValue(selectedRow.value, selectedCol.value);
});

function cellClass(r: number, c: number): string {
  const sel = selectedRow.value === r && selectedCol.value === c;
  const given = isGiven(r, c);
  const wrong = isWrong(r, c);
  const val = cellValue(r, c);
  const selVal = selectedValue.value;

  const related = selectedRow.value !== null && selectedCol.value !== null && !sel && (
    r === selectedRow.value ||
    c === selectedCol.value ||
    (Math.floor(r / 3) === Math.floor(selectedRow.value! / 3) && Math.floor(c / 3) === Math.floor(selectedCol.value! / 3))
  );

  const sameNum = !sel && val !== null && selVal !== null && val === selVal;

  const classes: string[] = ['flex items-center justify-center cursor-pointer select-none relative transition-colors'];

  if (sel) classes.push('bg-teal-200 dark:bg-teal-700/60');
  else if (sameNum) classes.push('bg-teal-100 dark:bg-teal-800/40');
  else if (wrong) classes.push('bg-red-50 dark:bg-red-900/20');
  else if (related) classes.push('bg-slate-100 dark:bg-slate-700/30');
  else classes.push('bg-white dark:bg-slate-800');

  // Border right
  if (c === 2 || c === 5) classes.push('border-r-2 border-r-slate-700 dark:border-r-slate-400');
  else if (c < 8) classes.push('border-r border-r-slate-200 dark:border-r-slate-600');

  // Border bottom
  if (r === 2 || r === 5) classes.push('border-b-2 border-b-slate-700 dark:border-b-slate-400');
  else if (r < 8) classes.push('border-b border-b-slate-200 dark:border-b-slate-600');

  return classes.join(' ');
}

function numClass(r: number, c: number): string {
  const given = isGiven(r, c);
  const wrong = isWrong(r, c);
  if (wrong) return 'text-red-500 font-semibold';
  if (given) return 'text-slate-800 dark:text-slate-100 font-black';
  return 'text-teal-600 dark:text-teal-400 font-semibold';
}

// ── Selection & Input ─────────────────────────────────────────────────

function selectCell(r: number, c: number) {
  if (gameState.value !== 'playing' || isPaused.value) return;
  selectedRow.value = r;
  selectedCol.value = c;
}

function inputNumber(num: number | null) {
  if (gameState.value !== 'playing' || isPaused.value) return;
  const r = selectedRow.value;
  const c = selectedCol.value;
  if (r === null || c === null || isGiven(r, c)) return;

  if (isNoteMode.value && num !== null) {
    const idx = notes.value[r][c].indexOf(num);
    if (idx === -1) notes.value[r][c].push(num);
    else notes.value[r][c].splice(idx, 1);
    notes.value[r][c].sort();
  } else {
    userGrid.value[r][c] = num;
    if (num !== null) notes.value[r][c] = [];
  }

  scheduleSave();
  checkCompletion();
}

function handleKeydown(e: KeyboardEvent) {
  if (gameState.value !== 'playing') return;
  const r = selectedRow.value;
  const c = selectedCol.value;

  if (e.key >= '1' && e.key <= '9') { inputNumber(parseInt(e.key)); return; }
  if (e.key === '0' || e.key === 'Backspace' || e.key === 'Delete') { inputNumber(null); return; }
  if (e.key === 'n' || e.key === 'N') { isNoteMode.value = !isNoteMode.value; return; }
  if (e.key === 'p' || e.key === 'P' || e.key === ' ') { e.preventDefault(); togglePause(); return; }

  if (r === null || c === null) return;
  if (e.key === 'ArrowUp' && r > 0) { selectedRow.value = r - 1; e.preventDefault(); }
  if (e.key === 'ArrowDown' && r < 8) { selectedRow.value = r + 1; e.preventDefault(); }
  if (e.key === 'ArrowLeft' && c > 0) { selectedCol.value = c - 1; e.preventDefault(); }
  if (e.key === 'ArrowRight' && c < 8) { selectedCol.value = c + 1; e.preventDefault(); }
}

// ── Completion ─────────────────────────────────────────────────────────

function isBoardFull(): boolean {
  for (let r = 0; r < 9; r++) {
    for (let c = 0; c < 9; c++) {
      if (!isGiven(r, c) && (userGrid.value[r]?.[c] === null || userGrid.value[r]?.[c] === undefined)) return false;
    }
  }
  return true;
}

async function checkCompletion() {
  if (!isBoardFull()) return;
  // Build complete grid: given cells + user cells
  const fullGrid: Grid = puzzle.value.map((row, r) =>
    row.map((cell, c) => cell ?? userGrid.value[r]?.[c] ?? null)
  );
  // Quick local check against solution
  for (let r = 0; r < 9; r++) {
    for (let c = 0; c < 9; c++) {
      if (fullGrid[r][c] !== solution.value[r]?.[c]) return;
    }
  }
  // Submit to backend
  stopTimer();
  try {
    const data: any = await api.post(`/api/v1/games/sudoku/sessions/${sessionId.value}/complete`, {
      user_state: fullGrid,
      elapsed_seconds: elapsedSeconds.value,
    }).then(unwrap);

    if (data.correct) {
      completionRecord.value = data.record;
      completionRank.value = data.rank;
      gameState.value = 'complete';
      await loadRecords();
    }
  } catch {
    toast({ tone: 'error', title: 'Gagal menyimpan', message: 'Selamat! Tapi ada error saat menyimpan record.' });
  }
}

// ── Pause ──────────────────────────────────────────────────────────────

function togglePause() {
  if (gameState.value !== 'playing') return;
  if (isPaused.value) {
    isPaused.value = false;
    startTimer();
  } else {
    isPaused.value = true;
    stopTimer();
    saveProgress();
  }
}

function handleVisibilityChange() {
  if (document.hidden) {
    if (gameState.value === 'playing' && !isPaused.value) {
      wasPlayingBeforeHide.value = true;
      isPaused.value = true;
      stopTimer();
      saveProgress();
    }
  } else {
    if (wasPlayingBeforeHide.value && gameState.value === 'playing') {
      wasPlayingBeforeHide.value = false;
      isPaused.value = false;
      startTimer();
    }
  }
}

// ── Save ───────────────────────────────────────────────────────────────

function scheduleSave() {
  if (saveTimeout) clearTimeout(saveTimeout);
  saveTimeout = setTimeout(saveProgress, 5000);
}

async function saveProgress() {
  if (!sessionId.value || saving.value || gameState.value === 'complete') return;
  saving.value = true;
  try {
    await api.patch(`/api/v1/games/sudoku/sessions/${sessionId.value}`, {
      user_state: userGrid.value,
      notes_state: notes.value,
      elapsed_seconds: elapsedSeconds.value,
      status: isPaused.value ? 'paused' : 'active',
    });
  } finally {
    saving.value = false;
  }
}

// ── Game start / load ──────────────────────────────────────────────────

function applySession(session: any) {
  sessionId.value = session.id;
  level.value = session.level;
  puzzle.value = session.puzzle;
  solution.value = session.solution;
  elapsedSeconds.value = session.elapsed_seconds ?? 0;

  userGrid.value = session.user_state ?? emptyGrid();
  notes.value = session.notes_state ?? emptyNotes();

  // Ensure correct dimensions if saved state is partial
  if (userGrid.value.length !== 9) userGrid.value = emptyGrid();
  if (notes.value.length !== 9) notes.value = emptyNotes();

  isPaused.value = session.status === 'paused';
  gameState.value = 'playing';

  if (!isPaused.value) startTimer();
}

async function startNewGame(chosenLevel: Level) {
  loading.value = true;
  try {
    const data: any = await api.post('/api/v1/games/sudoku/sessions', { level: chosenLevel }).then(unwrap);
    applySession(data.session);
    selectedRow.value = null;
    selectedCol.value = null;
    isNoteMode.value = false;
  } catch {
    toast({ tone: 'error', title: 'Gagal memulai', message: 'Coba lagi.' });
  } finally {
    loading.value = false;
  }
}

async function loadActiveSession() {
  try {
    const data: any = await api.get('/api/v1/games/sudoku/active').then(unwrap);
    if (data.session) applySession(data.session);
  } catch { /* ignore */ }
}

async function loadRecords() {
  try {
    const data: any = await api.get('/api/v1/games/sudoku/records').then(unwrap);
    records.value = data.records ?? { easy: [], medium: [], hard: [] };
  } catch { /* ignore */ }
}

function backToMenu() {
  stopTimer();
  if (saveTimeout) clearTimeout(saveTimeout);
  if (gameState.value === 'playing') saveProgress();
  gameState.value = 'menu';
  completionRecord.value = null;
  completionRank.value = null;
}

// ── Lifecycle ──────────────────────────────────────────────────────────

onMounted(() => {
  document.addEventListener('keydown', handleKeydown);
  document.addEventListener('visibilitychange', handleVisibilityChange);
  loadActiveSession();
  loadRecords();
});

onUnmounted(() => {
  stopTimer();
  if (saveTimeout) clearTimeout(saveTimeout);
  document.removeEventListener('keydown', handleKeydown);
  document.removeEventListener('visibilitychange', handleVisibilityChange);
});

const bestRecord = computed(() => records.value[level.value]?.[0] ?? null);
</script>

<template>
  <div class="mx-auto max-w-2xl space-y-5">
    <!-- Header -->
    <div class="flex items-center gap-3">
      <RouterLink to="/games" class="grid h-8 w-8 shrink-0 place-items-center rounded-xl border border-slate-200 bg-white text-slate-500 hover:border-teal-200 hover:text-teal-700">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6" /></svg>
      </RouterLink>
      <div>
        <p class="text-xs font-extrabold uppercase text-teal-700">Games</p>
        <h1 class="text-2xl font-extrabold tracking-tight">Sudoku</h1>
      </div>
    </div>

    <!-- ── MENU ── -->
    <template v-if="gameState === 'menu'">
      <div class="grid gap-3 sm:grid-cols-3">
        <button
          v-for="lv in (['easy', 'medium', 'hard'] as Level[])"
          :key="lv"
          class="card flex flex-col gap-3 p-5 text-left transition hover:border-teal-300 hover:shadow-md"
          :class="level === lv ? 'border-teal-400 ring-1 ring-teal-300' : ''"
          @click="level = lv"
        >
          <span class="pill" :class="lv === 'easy' ? 'pill-green' : lv === 'medium' ? 'pill-slate' : 'bg-orange-50 text-orange-700 border border-orange-200'">{{ LEVEL_LABELS[lv] }}</span>
          <p class="text-xs font-medium text-slate-500">{{ LEVEL_DESC[lv] }}</p>
        </button>
      </div>

      <button
        class="btn btn-primary w-full py-3 text-base"
        :disabled="loading"
        @click="startNewGame(level)"
      >
        {{ loading ? 'Membuat puzzle...' : `Mulai — ${LEVEL_LABELS[level]}` }}
      </button>

      <!-- Records -->
      <div v-if="Object.values(records).some(r => r.length)" class="card overflow-hidden p-0">
        <div class="border-b border-slate-100 bg-slate-50/70 px-5 py-3">
          <p class="text-xs font-extrabold uppercase text-teal-700">Personal Records</p>
        </div>
        <div class="divide-y divide-slate-100">
          <div v-for="lv in (['easy', 'medium', 'hard'] as Level[])" :key="lv">
            <div v-if="records[lv].length" class="px-5 py-3">
              <p class="mb-2 text-xs font-extrabold uppercase text-slate-500">{{ LEVEL_LABELS[lv] }}</p>
              <div class="space-y-1">
                <div
                  v-for="(rec, idx) in records[lv]"
                  :key="rec.id"
                  class="flex items-center gap-3 text-sm"
                >
                  <span class="w-5 text-center font-black" :class="idx === 0 ? 'text-yellow-500' : 'text-slate-400'">#{{ idx + 1 }}</span>
                  <span class="font-mono font-bold">{{ formatTime(rec.duration_seconds) }}</span>
                  <span class="text-slate-400">{{ rec.completed_at?.slice(0, 10) }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- ── PLAYING ── -->
    <template v-else-if="gameState === 'playing'">
      <!-- Controls bar -->
      <div class="flex items-center justify-between gap-4">
        <div class="flex items-center gap-2">
          <span class="pill" :class="level === 'easy' ? 'pill-green' : level === 'medium' ? 'pill-slate' : 'bg-orange-50 text-orange-700 border border-orange-200'">
            {{ LEVEL_LABELS[level] }}
          </span>
          <span v-if="saving" class="text-xs font-semibold text-slate-400">Menyimpan…</span>
        </div>
        <div class="flex items-center gap-2">
          <span class="font-mono text-xl font-black tabular-nums text-slate-800 dark:text-slate-100">{{ formatTime(elapsedSeconds) }}</span>
          <button
            class="grid h-9 w-9 place-items-center rounded-xl border border-slate-200 bg-white text-slate-600 hover:border-teal-200 hover:text-teal-700 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300"
            :title="isPaused ? 'Lanjutkan (P)' : 'Pause (P)'"
            @click="togglePause"
          >
            <svg v-if="isPaused" class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z" /></svg>
            <svg v-else class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z" /></svg>
          </button>
          <button
            class="grid h-9 w-9 place-items-center rounded-xl border border-slate-200 bg-white text-slate-500 hover:border-slate-300 dark:border-slate-600 dark:bg-slate-800"
            title="Kembali ke menu"
            @click="backToMenu"
          >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="M3 12h18M3 6h18M3 18h18" /></svg>
          </button>
        </div>
      </div>

      <!-- Pause overlay -->
      <div v-if="isPaused" class="card flex flex-col items-center gap-4 py-12 text-center">
        <p class="text-2xl font-black">⏸ Dijeda</p>
        <p class="text-sm font-medium text-slate-500">Waktu berjalan ketika kamu kembali ke halaman ini.</p>
        <button class="btn btn-primary" @click="togglePause">Lanjutkan</button>
      </div>

      <template v-else>
        <!-- Sudoku Grid -->
        <div class="overflow-hidden rounded-2xl border-2 border-slate-700 shadow-md dark:border-slate-400">
          <div class="grid grid-cols-9">
            <div
              v-for="idx in 81"
              :key="idx - 1"
              class="aspect-square"
              :class="cellClass(Math.floor((idx - 1) / 9), (idx - 1) % 9)"
              @click="selectCell(Math.floor((idx - 1) / 9), (idx - 1) % 9)"
            >
              <!-- Given or user number -->
              <template v-if="cellValue(Math.floor((idx - 1) / 9), (idx - 1) % 9) !== null">
                <span
                  class="text-sm leading-none sm:text-base"
                  :class="numClass(Math.floor((idx - 1) / 9), (idx - 1) % 9)"
                >
                  {{ cellValue(Math.floor((idx - 1) / 9), (idx - 1) % 9) }}
                </span>
              </template>
              <!-- Notes -->
              <template v-else-if="notes[Math.floor((idx - 1) / 9)]?.[(idx - 1) % 9]?.length">
                <div class="grid w-full h-full grid-cols-3 gap-0 p-px">
                  <span
                    v-for="n in 9"
                    :key="n"
                    class="flex items-center justify-center text-[7px] font-bold leading-none text-slate-400 sm:text-[8px]"
                  >{{ notes[Math.floor((idx - 1) / 9)][(idx - 1) % 9].includes(n) ? n : '' }}</span>
                </div>
              </template>
            </div>
          </div>
        </div>

        <!-- Number Pad -->
        <div class="space-y-3">
          <div class="grid grid-cols-5 gap-2 sm:grid-cols-10">
            <button
              v-for="n in 9"
              :key="n"
              class="aspect-square rounded-xl border border-slate-200 bg-white text-base font-black text-slate-700 transition hover:border-teal-300 hover:bg-teal-50 hover:text-teal-700 active:scale-95 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200"
              :class="selectedValue === n ? 'border-teal-400 bg-teal-50 text-teal-700 dark:bg-teal-900/30 dark:text-teal-300' : ''"
              @click="inputNumber(n)"
            >{{ n }}</button>
            <button
              class="aspect-square rounded-xl border border-slate-200 bg-white text-sm font-bold text-slate-500 transition hover:border-red-200 hover:bg-red-50 hover:text-red-600 active:scale-95 dark:border-slate-600 dark:bg-slate-800"
              title="Hapus (Backspace)"
              @click="inputNumber(null)"
            >
              <svg class="mx-auto h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="M21 6H8L2 12l6 6h13a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2z" /><path d="m15 9-6 6M9 9l6 6" /></svg>
            </button>
          </div>

          <div class="flex items-center justify-between gap-4">
            <button
              class="flex items-center gap-2 rounded-xl border px-3 py-2 text-sm font-bold transition"
              :class="isNoteMode
                ? 'border-teal-300 bg-teal-50 text-teal-700 dark:border-teal-600 dark:bg-teal-900/30 dark:text-teal-300'
                : 'border-slate-200 bg-white text-slate-600 hover:border-teal-200 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300'"
              @click="isNoteMode = !isNoteMode"
            >
              <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z" /></svg>
              Catatan (N)
            </button>
            <p class="text-xs font-medium text-slate-400">Arrows: gerak · P: pause</p>
          </div>
        </div>
      </template>
    </template>

    <!-- ── COMPLETE ── -->
    <template v-else-if="gameState === 'complete'">
      <div class="card flex flex-col items-center gap-5 py-10 text-center">
        <div class="text-5xl">🎉</div>
        <div>
          <h2 class="text-2xl font-black">Selesai!</h2>
          <p class="mt-1 text-sm font-medium text-slate-500">Level {{ LEVEL_LABELS[level] }}</p>
        </div>
        <div class="flex flex-col items-center gap-1">
          <span class="font-mono text-4xl font-black tabular-nums text-teal-600">{{ formatTime(elapsedSeconds) }}</span>
          <span v-if="completionRank === 1" class="pill bg-yellow-50 text-yellow-700 border border-yellow-200">🏆 Record baru!</span>
          <span v-else-if="completionRank" class="text-sm font-semibold text-slate-500">Rank #{{ completionRank }} (personal)</span>
        </div>

        <!-- Personal best for this level -->
        <div v-if="records[level].length" class="w-full max-w-xs rounded-2xl border border-slate-100 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/50">
          <p class="mb-2 text-xs font-extrabold uppercase text-slate-500">Top records — {{ LEVEL_LABELS[level] }}</p>
          <div class="space-y-1">
            <div
              v-for="(rec, idx) in records[level].slice(0, 5)"
              :key="rec.id"
              class="flex items-center gap-3 text-sm"
              :class="rec.id === completionRecord?.id ? 'font-black text-teal-700 dark:text-teal-400' : ''"
            >
              <span class="w-5 text-center" :class="idx === 0 ? 'text-yellow-500 font-black' : 'text-slate-400 font-bold'">#{{ idx + 1 }}</span>
              <span class="font-mono">{{ formatTime(rec.duration_seconds) }}</span>
              <span class="text-slate-400 text-xs">{{ rec.completed_at?.slice(0, 10) }}</span>
            </div>
          </div>
        </div>

        <div class="flex gap-3">
          <button class="btn btn-primary" @click="startNewGame(level)">Main lagi — {{ LEVEL_LABELS[level] }}</button>
          <button class="btn btn-muted" @click="backToMenu">Pilih level</button>
        </div>
      </div>
    </template>
  </div>
</template>
