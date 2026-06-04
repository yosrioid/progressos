<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { api, unwrap } from '../api';
import { toast } from '../feedback';

type Level = 'easy' | 'medium' | 'hard' | 'expert' | 'daily';
type GameState = 'menu' | 'playing' | 'complete' | 'failed';
type Grid = (number | null)[][];
type Notes = number[][][];
type Move = { r: number; c: number; prevValue: number | null; prevNotes: number[] };

const LEVEL_LABELS: Record<string, string> = {
  easy: 'Mudah', medium: 'Sedang', hard: 'Sulit', expert: 'Expert', daily: 'Harian',
};
const LEVEL_DESC: Record<string, string> = {
  easy:   '~40 angka terisi — cocok untuk pemanasan.',
  medium: '~32 angka terisi — butuh lebih banyak deduksi.',
  hard:   '~26 angka terisi — tantangan penuh.',
  expert: '~22 angka terisi — untuk yang sudah mahir.',
};
const REGULAR_LEVELS: Level[] = ['easy', 'medium', 'hard', 'expert'];
const MAX_MISTAKES = 3;
const MAX_HINTS = 3;

const gameState    = ref<GameState>('menu');
const sessionId    = ref<number | null>(null);
const level        = ref<Level>('easy');
const puzzle       = ref<Grid>([]);
const solution     = ref<Grid>([]);
const userGrid     = ref<Grid>([]);
const notes        = ref<Notes>([]);
const selectedRow  = ref<number | null>(null);
const selectedCol  = ref<number | null>(null);
const elapsedSeconds = ref(0);
const isPaused     = ref(false);
const isNoteMode   = ref(false);
const loading      = ref(false);
const dailyLoading = ref(false);
const saving       = ref(false);
const mistakes     = ref(0);
const hintsUsed    = ref(0);
const history      = ref<Move[]>([]);
const hintedCells  = ref<Set<string>>(new Set());
const records      = ref<Record<string, any[]>>({ easy: [], medium: [], hard: [], expert: [], daily: [] });
const totals       = ref<Record<string, number>>({ easy: 0, medium: 0, hard: 0, expert: 0, daily: 0 });
const completionRecord = ref<any>(null);
const completionRank   = ref<number | null>(null);
const wasPlayingBeforeHide = ref(false);
const gameStats    = ref({ mistakes: 0, hintsUsed: 0 });
const showGameMenu = ref(false);
const pendingResume = ref<any>(null);
const dailyStatus  = ref<{ completed_today: boolean; record: any; session: any } | null>(null);

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

const numberCounts = computed(() => {
  const counts: Record<number, number> = {};
  for (let n = 1; n <= 9; n++) counts[n] = 0;
  if (!puzzle.value.length) return counts;
  for (let r = 0; r < 9; r++) {
    for (let c = 0; c < 9; c++) {
      const v = cellValue(r, c);
      if (v !== null) counts[v]++;
    }
  }
  return counts;
});

const progressPercent = computed(() => {
  if (!puzzle.value.length) return 0;
  let filled = 0;
  for (let r = 0; r < 9; r++) {
    for (let c = 0; c < 9; c++) {
      if (cellValue(r, c) !== null) filled++;
    }
  }
  return Math.round((filled / 81) * 100);
});

// ── Cell styling ───────────────────────────────────────────────────────

function cellClass(r: number, c: number): string {
  const sel    = selectedRow.value === r && selectedCol.value === c;
  const wrong  = isWrong(r, c);
  const val    = cellValue(r, c);
  const selVal = selectedValue.value;

  const related = selectedRow.value !== null && selectedCol.value !== null && !sel && (
    r === selectedRow.value ||
    c === selectedCol.value ||
    (Math.floor(r / 3) === Math.floor(selectedRow.value! / 3) &&
     Math.floor(c / 3) === Math.floor(selectedCol.value! / 3))
  );
  const sameNum = !sel && val !== null && selVal !== null && val === selVal;

  const cls = ['flex items-center justify-center cursor-pointer select-none relative transition-colors'];

  if (sel)           cls.push('bg-teal-200 dark:bg-teal-700/60');
  else if (sameNum)  cls.push('bg-teal-100/80 dark:bg-teal-800/40');
  else if (wrong)    cls.push('bg-red-50 dark:bg-red-900/20');
  else if (related)  cls.push('bg-slate-100/80 dark:bg-slate-700/30');
  else               cls.push('bg-white dark:bg-slate-800');

  if (c === 2 || c === 5) cls.push('border-r-2 border-r-slate-600 dark:border-r-slate-400');
  else if (c < 8)         cls.push('border-r border-r-slate-200 dark:border-r-slate-600/50');

  if (r === 2 || r === 5) cls.push('border-b-2 border-b-slate-600 dark:border-b-slate-400');
  else if (r < 8)         cls.push('border-b border-b-slate-200 dark:border-b-slate-600/50');

  return cls.join(' ');
}

function numClass(r: number, c: number): string {
  const wrong  = isWrong(r, c);
  const given  = isGiven(r, c);
  const hinted = hintedCells.value.has(`${r},${c}`);
  if (wrong)   return 'text-red-500 font-bold';
  if (hinted)  return 'text-purple-600 dark:text-purple-400 font-bold';
  if (given)   return 'text-slate-800 dark:text-slate-100 font-black';
  return 'text-teal-600 dark:text-teal-400 font-bold';
}

function levelPillClass(lv: string): string {
  if (lv === 'easy')   return 'pill-green';
  if (lv === 'medium') return 'pill-slate';
  if (lv === 'hard')   return 'border border-orange-200 bg-orange-50 text-orange-700';
  if (lv === 'expert') return 'border border-red-200 bg-red-50 text-red-700';
  if (lv === 'daily')  return 'border border-indigo-200 bg-indigo-50 text-indigo-700';
  return 'pill-slate';
}

// ── Selection & Input ─────────────────────────────────────────────────

function selectCell(r: number, c: number) {
  if (gameState.value !== 'playing' || isPaused.value) return;
  selectedRow.value = r;
  selectedCol.value = c;
}

function nextEmptyCell(fromR: number, fromC: number): [number, number] | null {
  for (let i = fromR * 9 + fromC + 1; i < 81; i++) {
    const r = Math.floor(i / 9);
    const c = i % 9;
    if (!isGiven(r, c) && userGrid.value[r][c] === null) return [r, c];
  }
  for (let i = 0; i < fromR * 9 + fromC; i++) {
    const r = Math.floor(i / 9);
    const c = i % 9;
    if (!isGiven(r, c) && userGrid.value[r][c] === null) return [r, c];
  }
  return null;
}

function clearRelatedNotes(r: number, c: number, num: number) {
  for (let col = 0; col < 9; col++) {
    if (col !== c) { const i = notes.value[r][col].indexOf(num); if (i !== -1) notes.value[r][col].splice(i, 1); }
  }
  for (let row = 0; row < 9; row++) {
    if (row !== r) { const i = notes.value[row][c].indexOf(num); if (i !== -1) notes.value[row][c].splice(i, 1); }
  }
  const br = Math.floor(r / 3) * 3;
  const bc = Math.floor(c / 3) * 3;
  for (let row = br; row < br + 3; row++) {
    for (let col = bc; col < bc + 3; col++) {
      if (row !== r || col !== c) { const i = notes.value[row][col].indexOf(num); if (i !== -1) notes.value[row][col].splice(i, 1); }
    }
  }
}

function inputNumber(num: number | null) {
  if (gameState.value !== 'playing' || isPaused.value) return;
  const r = selectedRow.value;
  const c = selectedCol.value;
  if (r === null || c === null || isGiven(r, c)) return;

  if (isNoteMode.value && num !== null) {
    history.value.push({ r, c, prevValue: userGrid.value[r][c], prevNotes: [...notes.value[r][c]] });
    const idx = notes.value[r][c].indexOf(num);
    if (idx === -1) notes.value[r][c].push(num);
    else notes.value[r][c].splice(idx, 1);
    notes.value[r][c].sort();
  } else {
    history.value.push({ r, c, prevValue: userGrid.value[r][c], prevNotes: [...notes.value[r][c]] });
    userGrid.value[r][c] = num;
    if (num !== null) {
      notes.value[r][c] = [];
      if (num === solution.value[r]?.[c]) {
        clearRelatedNotes(r, c, num);
        // Auto-advance to next empty cell
        const next = nextEmptyCell(r, c);
        if (next) { selectedRow.value = next[0]; selectedCol.value = next[1]; }
      } else {
        mistakes.value++;
        if (mistakes.value >= MAX_MISTAKES) {
          stopTimer();
          gameStats.value = { mistakes: mistakes.value, hintsUsed: hintsUsed.value };
          gameState.value = 'failed';
          return;
        }
      }
    }
  }

  scheduleSave();
  checkCompletion();
}

function undo() {
  if (!history.value.length || gameState.value !== 'playing' || isPaused.value) return;
  const last = history.value.pop()!;
  userGrid.value[last.r][last.c] = last.prevValue;
  notes.value[last.r][last.c] = [...last.prevNotes];
  scheduleSave();
}

function useHint() {
  if (gameState.value !== 'playing' || isPaused.value) return;
  if (hintsUsed.value >= MAX_HINTS) {
    toast({ tone: 'error', title: 'Hint habis', message: 'Sudah memakai semua hint yang tersedia.' });
    return;
  }
  const r = selectedRow.value;
  const c = selectedCol.value;
  if (r === null || c === null) {
    toast({ tone: 'error', title: 'Pilih sel dulu', message: 'Klik sel kosong yang ingin diisi.' });
    return;
  }
  if (isGiven(r, c)) {
    toast({ tone: 'error', title: 'Bukan sel kosong', message: 'Pilih sel yang belum terisi.' });
    return;
  }
  const correct = solution.value[r]?.[c];
  if (!correct) return;
  if (userGrid.value[r][c] === correct) {
    toast({ tone: 'error', title: 'Sel sudah benar', message: 'Nilai di sel ini sudah benar.' });
    return;
  }
  hintsUsed.value++;
  userGrid.value[r][c] = correct;
  notes.value[r][c] = [];
  // Create new Set to trigger Vue reactivity (Set.add() is not tracked)
  hintedCells.value = new Set([...hintedCells.value, `${r},${c}`]);
  clearRelatedNotes(r, c, correct);
  const next = nextEmptyCell(r, c);
  if (next) { selectedRow.value = next[0]; selectedCol.value = next[1]; }
  scheduleSave();
  checkCompletion();
}

function handleKeydown(e: KeyboardEvent) {
  if (gameState.value !== 'playing') return;
  const r = selectedRow.value;
  const c = selectedCol.value;

  if ((e.ctrlKey || e.metaKey) && e.key === 'z') { e.preventDefault(); undo(); return; }
  if (e.key === 'h' || e.key === 'H') { useHint(); return; }
  if (e.key >= '1' && e.key <= '9') { inputNumber(parseInt(e.key)); return; }
  if (e.key === '0' || e.key === 'Backspace' || e.key === 'Delete') { inputNumber(null); return; }
  if (e.key === 'n' || e.key === 'N') { isNoteMode.value = !isNoteMode.value; return; }
  if (e.key === 'p' || e.key === 'P' || e.key === ' ') { e.preventDefault(); togglePause(); return; }

  if (e.key === 'Tab') {
    e.preventDefault();
    const from = (r ?? 0) * 9 + (c ?? -1);
    const next = nextEmptyCell(Math.floor(from / 9), from % 9);
    if (next) { selectedRow.value = next[0]; selectedCol.value = next[1]; }
    return;
  }

  if (r === null || c === null) return;
  if (e.key === 'ArrowUp'    && r > 0) { selectedRow.value = r - 1; e.preventDefault(); }
  if (e.key === 'ArrowDown'  && r < 8) { selectedRow.value = r + 1; e.preventDefault(); }
  if (e.key === 'ArrowLeft'  && c > 0) { selectedCol.value = c - 1; e.preventDefault(); }
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
  const fullGrid: Grid = puzzle.value.map((row, r) =>
    row.map((cell, c) => cell ?? userGrid.value[r]?.[c] ?? null)
  );
  for (let r = 0; r < 9; r++) {
    for (let c = 0; c < 9; c++) {
      if (fullGrid[r][c] !== solution.value[r]?.[c]) return;
    }
  }
  stopTimer();
  gameStats.value = { mistakes: mistakes.value, hintsUsed: hintsUsed.value };
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
      if (level.value === 'daily') await loadDailyStatus();
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

async function abandonSession(id: number) {
  try {
    await api.patch(`/api/v1/games/sudoku/sessions/${id}`, {
      user_state: null,
      notes_state: null,
      elapsed_seconds: 0,
      status: 'abandoned',
    });
  } catch { /* ignore */ }
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
  if (userGrid.value.length !== 9) userGrid.value = emptyGrid();
  if (notes.value.length !== 9) notes.value = emptyNotes();
  isPaused.value = session.status === 'paused';
  mistakes.value = 0;
  hintsUsed.value = 0;
  history.value = [];
  hintedCells.value = new Set();
  gameState.value = 'playing';
  if (!isPaused.value) startTimer();
}

async function startNewGame(chosenLevel: Level) {
  pendingResume.value = null;
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

async function startDailyChallenge() {
  if (dailyStatus.value?.completed_today) return;
  if (dailyStatus.value?.session) {
    // Resume existing daily session
    pendingResume.value = null;
    applySession(dailyStatus.value.session);
    selectedRow.value = null;
    selectedCol.value = null;
    isNoteMode.value = false;
    return;
  }
  dailyLoading.value = true;
  try {
    const data: any = await api.post('/api/v1/games/sudoku/sessions', { level: 'daily' }).then(unwrap);
    pendingResume.value = null;
    applySession(data.session);
    selectedRow.value = null;
    selectedCol.value = null;
    isNoteMode.value = false;
  } catch {
    toast({ tone: 'error', title: 'Gagal memulai tantangan', message: 'Coba lagi.' });
  } finally {
    dailyLoading.value = false;
  }
}

async function loadActiveSession() {
  try {
    const data: any = await api.get('/api/v1/games/sudoku/active').then(unwrap);
    if (data.session) pendingResume.value = data.session;
  } catch { /* ignore */ }
}

async function loadDailyStatus() {
  try {
    const data: any = await api.get('/api/v1/games/sudoku/daily').then(unwrap);
    dailyStatus.value = data;
  } catch { /* ignore */ }
}

function resumeSession() {
  if (!pendingResume.value) return;
  applySession(pendingResume.value);
  pendingResume.value = null;
  selectedRow.value = null;
  selectedCol.value = null;
  isNoteMode.value = false;
}

async function dismissResume() {
  if (!pendingResume.value) return;
  const id = pendingResume.value.id;
  pendingResume.value = null;
  await abandonSession(id);
}

async function loadRecords() {
  try {
    const data: any = await api.get('/api/v1/games/sudoku/records').then(unwrap);
    records.value = data.records ?? { easy: [], medium: [], hard: [], expert: [], daily: [] };
    totals.value  = data.totals  ?? { easy: 0, medium: 0, hard: 0, expert: 0, daily: 0 };
  } catch { /* ignore */ }
}

function backToMenu() {
  showGameMenu.value = false;
  stopTimer();
  if (saveTimeout) clearTimeout(saveTimeout);
  if (gameState.value === 'playing') saveProgress();
  gameState.value = 'menu';
  completionRecord.value = null;
  completionRank.value = null;
}

async function exitWithoutSave() {
  showGameMenu.value = false;
  stopTimer();
  if (saveTimeout) clearTimeout(saveTimeout);
  if (sessionId.value) await abandonSession(sessionId.value);
  pendingResume.value = null;
  gameState.value = 'menu';
  completionRecord.value = null;
  completionRank.value = null;
}

function restartPuzzle() {
  showGameMenu.value = false;
  stopTimer();
  if (saveTimeout) clearTimeout(saveTimeout);
  userGrid.value = emptyGrid();
  notes.value = emptyNotes();
  mistakes.value = 0;
  hintsUsed.value = 0;
  history.value = [];
  hintedCells.value = new Set();
  elapsedSeconds.value = 0;
  selectedRow.value = null;
  selectedCol.value = null;
  isNoteMode.value = false;
  isPaused.value = false;
  startTimer();
  saveProgress();
}

// ── Lifecycle ──────────────────────────────────────────────────────────

onMounted(() => {
  document.addEventListener('keydown', handleKeydown);
  document.addEventListener('visibilitychange', handleVisibilityChange);
  loadActiveSession();
  loadDailyStatus();
  loadRecords();
});

onUnmounted(() => {
  stopTimer();
  if (saveTimeout) clearTimeout(saveTimeout);
  document.removeEventListener('keydown', handleKeydown);
  document.removeEventListener('visibilitychange', handleVisibilityChange);
});

const totalCompleted = computed(() =>
  Object.entries(totals.value)
    .filter(([k]) => k !== 'daily')
    .reduce((sum, [, v]) => sum + v, 0)
);
</script>

<template>
  <div class="mx-auto max-w-lg space-y-4">
    <!-- Header -->
    <div class="flex items-center gap-3">
      <RouterLink
        to="/games"
        class="grid h-8 w-8 shrink-0 place-items-center rounded-xl border border-slate-200 bg-white text-slate-500 hover:border-teal-200 hover:text-teal-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400"
      >
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6" /></svg>
      </RouterLink>
      <div>
        <p class="text-xs font-extrabold uppercase text-teal-700">Games</p>
        <h1 class="text-xl font-extrabold tracking-tight">Sudoku</h1>
      </div>
    </div>

    <!-- ── MENU ── -->
    <template v-if="gameState === 'menu'">

      <!-- Resume banner -->
      <div
        v-if="pendingResume"
        class="card flex items-center justify-between gap-3 border-teal-200 bg-teal-50/60 p-4 dark:border-teal-700/60 dark:bg-teal-900/10"
      >
        <div class="min-w-0">
          <p class="text-xs font-extrabold uppercase text-teal-700">Sesi tersimpan</p>
          <p class="mt-0.5 truncate text-sm font-bold text-slate-700 dark:text-slate-200">
            {{ LEVEL_LABELS[pendingResume.level] }}
            <span class="font-mono font-semibold text-slate-500"> · {{ formatTime(pendingResume.elapsed_seconds) }}</span>
          </p>
        </div>
        <div class="flex shrink-0 gap-2">
          <button class="btn btn-primary py-1.5 px-3 text-sm" @click="resumeSession">Lanjutkan</button>
          <button class="btn btn-muted py-1.5 px-3 text-sm" @click="dismissResume">Hapus</button>
        </div>
      </div>

      <!-- Daily Challenge card -->
      <div class="card overflow-hidden p-0">
        <div class="flex items-center gap-3 border-b border-slate-100 bg-gradient-to-r from-indigo-50 to-slate-50 px-5 py-3.5 dark:border-slate-700 dark:from-indigo-900/20 dark:to-slate-800/50">
          <svg class="h-5 w-5 shrink-0 text-indigo-500" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><rect width="18" height="18" x="3" y="4" rx="2" ry="2" /><line x1="16" x2="16" y1="2" y2="6" /><line x1="8" x2="8" y1="2" y2="6" /><line x1="3" x2="21" y1="10" y2="10" /></svg>
          <p class="text-sm font-extrabold text-indigo-800 dark:text-indigo-300">Tantangan Harian</p>
        </div>
        <div class="flex items-center justify-between gap-3 px-5 py-4">
          <div class="min-w-0">
            <p class="text-xs font-medium text-slate-500">Puzzle baru setiap hari. Semua orang mendapat puzzle yang sama.</p>
            <div class="mt-2">
              <!-- Completed today -->
              <template v-if="dailyStatus?.completed_today">
                <div class="flex items-center gap-2">
                  <span class="flex h-5 w-5 items-center justify-center rounded-full bg-teal-500">
                    <svg class="h-3 w-3 text-white" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5" /></svg>
                  </span>
                  <span class="text-sm font-bold text-teal-700 dark:text-teal-400">
                    Selesai hari ini
                    <span v-if="dailyStatus.record" class="font-mono"> — {{ formatTime(dailyStatus.record.duration_seconds) }}</span>
                  </span>
                </div>
              </template>
              <!-- Active daily session -->
              <template v-else-if="dailyStatus?.session">
                <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">
                  Dilanjutkan · <span class="font-mono">{{ formatTime(dailyStatus.session.elapsed_seconds) }}</span>
                </p>
              </template>
              <!-- Not started -->
              <template v-else>
                <p class="text-sm font-semibold text-slate-400">Belum dimulai hari ini</p>
              </template>
            </div>
          </div>
          <button
            v-if="!dailyStatus?.completed_today"
            class="shrink-0 rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm font-bold text-indigo-700 transition hover:bg-indigo-100 active:scale-95 disabled:opacity-50 dark:border-indigo-700 dark:bg-indigo-900/20 dark:text-indigo-400"
            :disabled="dailyLoading"
            @click="startDailyChallenge"
          >
            {{ dailyLoading ? '...' : dailyStatus?.session ? 'Lanjutkan' : 'Mulai' }}
          </button>
          <div v-else class="shrink-0 text-2xl">🏅</div>
        </div>
      </div>

      <!-- Level selection -->
      <div class="grid gap-3 grid-cols-2 sm:grid-cols-4">
        <button
          v-for="lv in REGULAR_LEVELS"
          :key="lv"
          class="card flex flex-col gap-2 p-4 text-left transition hover:border-teal-300 hover:shadow-md"
          :class="level === lv ? 'border-teal-400 ring-1 ring-teal-300' : ''"
          @click="level = lv"
        >
          <span class="pill text-xs" :class="levelPillClass(lv)">{{ LEVEL_LABELS[lv] }}</span>
          <p class="text-xs font-medium text-slate-500 leading-relaxed">{{ LEVEL_DESC[lv] }}</p>
          <p v-if="totals[lv] > 0" class="text-xs font-bold text-slate-400">{{ totals[lv] }}× selesai</p>
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
        <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/70 px-5 py-3 dark:border-slate-700 dark:bg-slate-800/50">
          <p class="text-xs font-extrabold uppercase text-teal-700">Personal Records</p>
          <p v-if="totalCompleted > 0" class="text-xs font-bold text-slate-400">{{ totalCompleted }} total selesai</p>
        </div>
        <div class="divide-y divide-slate-100 dark:divide-slate-700">
          <div v-for="lv in ([...REGULAR_LEVELS, 'daily'] as string[])" :key="lv">
            <div v-if="records[lv]?.length" class="px-5 py-3">
              <div class="mb-2 flex items-center gap-2">
                <p class="text-xs font-extrabold uppercase text-slate-500">{{ LEVEL_LABELS[lv] }}</p>
                <span class="text-xs font-bold text-slate-400">{{ totals[lv] }}×</span>
              </div>
              <div class="space-y-1.5">
                <div
                  v-for="(rec, idx) in records[lv]"
                  :key="rec.id"
                  class="flex items-center gap-3 text-sm"
                >
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

    <!-- ── PLAYING ── -->
    <template v-else-if="gameState === 'playing'">
      <!-- Backdrop for game menu dropdown -->
      <div v-if="showGameMenu" class="fixed inset-0 z-40" @click="showGameMenu = false"></div>

      <!-- Game header bar -->
      <div class="flex items-center justify-between gap-2">
        <!-- Left: level + progress bar -->
        <div class="flex min-w-0 flex-1 items-center gap-2">
          <span class="pill shrink-0 text-xs" :class="levelPillClass(level)">{{ LEVEL_LABELS[level] }}</span>
          <div class="flex min-w-0 flex-1 items-center gap-1.5">
            <div class="h-1.5 min-w-0 flex-1 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
              <div
                class="h-full rounded-full bg-teal-500 transition-all duration-500"
                :style="{ width: progressPercent + '%' }"
              ></div>
            </div>
            <span class="shrink-0 text-xs font-bold tabular-nums text-slate-400">{{ progressPercent }}%</span>
          </div>
        </div>
        <!-- Right: mistakes + timer + controls -->
        <div class="flex shrink-0 items-center gap-2">
          <div class="flex items-center gap-0.5" title="Kesalahan">
            <span
              v-for="i in MAX_MISTAKES"
              :key="i"
              class="text-xs leading-none transition-colors"
              :class="i <= mistakes ? 'text-red-500' : 'text-slate-200 dark:text-slate-600'"
            >●</span>
          </div>
          <span v-if="saving" class="text-xs font-semibold text-slate-400">↑</span>
          <span class="font-mono text-lg font-black tabular-nums text-slate-800 dark:text-slate-100">{{ formatTime(elapsedSeconds) }}</span>
          <button
            class="grid h-8 w-8 place-items-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:border-teal-200 hover:text-teal-700 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300"
            :title="isPaused ? 'Lanjutkan (P)' : 'Pause (P)'"
            @click="togglePause"
          >
            <svg v-if="isPaused" class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z" /></svg>
            <svg v-else class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z" /></svg>
          </button>

          <!-- More options -->
          <div class="relative">
            <button
              class="grid h-8 w-8 place-items-center rounded-xl border border-slate-200 bg-white text-slate-500 transition hover:border-slate-300 hover:text-slate-700 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-400"
              :class="showGameMenu ? 'border-slate-300 bg-slate-50 dark:border-slate-500 dark:bg-slate-700' : ''"
              title="Menu lainnya"
              @click="showGameMenu = !showGameMenu"
            >
              <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><circle cx="5" cy="12" r="1.5" /><circle cx="12" cy="12" r="1.5" /><circle cx="19" cy="12" r="1.5" /></svg>
            </button>

            <transition
              enter-active-class="transition duration-100 ease-out"
              enter-from-class="opacity-0 scale-95"
              enter-to-class="opacity-100 scale-100"
              leave-active-class="transition duration-75 ease-in"
              leave-from-class="opacity-100 scale-100"
              leave-to-class="opacity-0 scale-95"
            >
              <div
                v-if="showGameMenu"
                class="absolute right-0 top-10 z-50 w-52 origin-top-right overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-800"
              >
                <button
                  class="flex w-full items-center gap-3 px-4 py-3 text-left text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700/50"
                  @click="backToMenu"
                >
                  <svg class="h-4 w-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z" /><polyline points="17 21 17 13 7 13 7 21" /><polyline points="7 3 7 8 15 8" /></svg>
                  Simpan &amp; keluar
                </button>
                <button
                  class="flex w-full items-center gap-3 px-4 py-3 text-left text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700/50"
                  @click="restartPuzzle"
                >
                  <svg class="h-4 w-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8" /><path d="M3 3v5h5" /></svg>
                  Mulai ulang puzzle
                </button>
                <div class="border-t border-slate-100 dark:border-slate-700"></div>
                <button
                  class="flex w-full items-center gap-3 px-4 py-3 text-left text-sm font-semibold text-red-600 transition hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20"
                  @click="exitWithoutSave"
                >
                  <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" /><polyline points="16 17 21 12 16 7" /><line x1="21" y1="12" x2="9" y2="12" /></svg>
                  Keluar tanpa simpan
                </button>
              </div>
            </transition>
          </div>
        </div>
      </div>

      <!-- Pause overlay -->
      <div v-if="isPaused" class="card flex flex-col items-center gap-5 py-14 text-center">
        <div class="rounded-2xl bg-slate-100 p-5 dark:bg-slate-700/50">
          <svg class="h-10 w-10 text-slate-500 dark:text-slate-400" fill="currentColor" viewBox="0 0 24 24"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z" /></svg>
        </div>
        <div>
          <p class="text-xl font-black">Dijeda</p>
          <p class="mt-1 text-sm font-medium text-slate-500">Puzzle tersembunyi untuk menjaga fairness.</p>
        </div>
        <button class="btn btn-primary px-8" @click="togglePause">Lanjutkan</button>
      </div>

      <template v-else>
        <!-- Sudoku Grid -->
        <div class="overflow-hidden rounded-2xl border-2 border-slate-700 shadow-md dark:border-slate-400">
          <div class="grid grid-cols-9">
            <template v-for="r in 9" :key="r">
              <div
                v-for="c in 9"
                :key="c"
                class="aspect-square"
                :class="cellClass(r - 1, c - 1)"
                @click="selectCell(r - 1, c - 1)"
              >
                <template v-if="cellValue(r - 1, c - 1) !== null">
                  <span class="text-sm leading-none sm:text-base" :class="numClass(r - 1, c - 1)">
                    {{ cellValue(r - 1, c - 1) }}
                  </span>
                </template>
                <template v-else-if="notes[r - 1]?.[c - 1]?.length">
                  <div class="grid h-full w-full grid-cols-3 p-px">
                    <span
                      v-for="n in 9"
                      :key="n"
                      class="flex items-center justify-center text-[7px] font-bold leading-none text-slate-400 sm:text-[8px]"
                    >{{ notes[r - 1][c - 1].includes(n) ? n : '' }}</span>
                  </div>
                </template>
              </div>
            </template>
          </div>
        </div>

        <!-- Action bar -->
        <div class="grid grid-cols-3 gap-2">
          <button
            class="flex items-center justify-center gap-1.5 rounded-xl border py-2.5 text-sm font-bold transition active:scale-95 disabled:cursor-not-allowed disabled:opacity-40"
            :class="history.length
              ? 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300'
              : 'border-slate-100 bg-white text-slate-400 dark:border-slate-700 dark:bg-slate-800'"
            :disabled="!history.length"
            title="Undo (Ctrl+Z)"
            @click="undo"
          >
            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="M3 7v6h6" /><path d="M3 13C5 7 9.5 3 15 3a9 9 0 1 1-9 9" /></svg>
            Undo
          </button>
          <button
            class="flex items-center justify-center gap-1.5 rounded-xl border py-2.5 text-sm font-bold transition active:scale-95 disabled:cursor-not-allowed disabled:opacity-40"
            :class="hintsUsed < MAX_HINTS
              ? 'border-purple-200 bg-purple-50 text-purple-700 hover:bg-purple-100 dark:border-purple-700 dark:bg-purple-900/20 dark:text-purple-400'
              : 'border-slate-100 bg-white text-slate-400 dark:border-slate-700 dark:bg-slate-800'"
            :disabled="hintsUsed >= MAX_HINTS"
            :title="`Hint (H) — ${MAX_HINTS - hintsUsed} tersisa`"
            @click="useHint"
          >
            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1 .2 2.2 1.5 3.5.7.7 1.3 1.5 1.5 2.5" /><path d="M9 18h6M10 22h4" /></svg>
            Hint <span class="text-xs opacity-70">{{ MAX_HINTS - hintsUsed }}</span>
          </button>
          <button
            class="flex items-center justify-center gap-1.5 rounded-xl border py-2.5 text-sm font-bold transition active:scale-95"
            :class="isNoteMode
              ? 'border-teal-300 bg-teal-50 text-teal-700 dark:border-teal-600 dark:bg-teal-900/30 dark:text-teal-300'
              : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300'"
            title="Catatan (N)"
            @click="isNoteMode = !isNoteMode"
          >
            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z" /></svg>
            Catatan
          </button>
        </div>

        <!-- Number Pad 3×3 -->
        <div class="grid grid-cols-3 gap-2">
          <button
            v-for="n in 9"
            :key="n"
            class="relative flex flex-col items-center justify-center rounded-xl border py-3 text-xl font-black transition active:scale-95"
            :class="[
              numberCounts[n] >= 9
                ? 'cursor-not-allowed border-slate-100 bg-slate-50 text-slate-300 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-600'
                : isNoteMode
                  ? 'border-teal-200 bg-teal-50/50 text-teal-700 hover:bg-teal-50 dark:border-teal-700/50 dark:bg-teal-900/10 dark:text-teal-400'
                  : selectedValue === n
                    ? 'border-teal-400 bg-teal-50 text-teal-700 dark:border-teal-600 dark:bg-teal-900/30 dark:text-teal-300'
                    : 'border-slate-200 bg-white text-slate-700 hover:border-teal-300 hover:bg-teal-50 hover:text-teal-700 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200',
            ]"
            :disabled="numberCounts[n] >= 9"
            @click="inputNumber(n)"
          >
            {{ n }}
            <span
              v-if="numberCounts[n] < 9"
              class="absolute bottom-1 right-1.5 text-[10px] font-bold leading-none"
              :class="numberCounts[n] >= 7 ? 'text-orange-400' : 'text-slate-300 dark:text-slate-600'"
            >{{ 9 - numberCounts[n] }}</span>
            <span v-else class="absolute bottom-1 right-1.5 text-[10px] leading-none text-slate-300 dark:text-slate-600">✓</span>
          </button>
        </div>

        <!-- Erase + shortcuts -->
        <div class="flex items-center gap-2">
          <button
            class="flex flex-1 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white py-2.5 text-sm font-bold text-slate-500 transition hover:border-red-200 hover:bg-red-50 hover:text-red-600 active:scale-95 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-400"
            title="Hapus (Backspace)"
            @click="inputNumber(null)"
          >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="M21 6H8L2 12l6 6h13a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2z" /><path d="m15 9-6 6M9 9l6 6" /></svg>
            Hapus
          </button>
          <p class="shrink-0 text-xs font-medium text-slate-400">← → ↑ ↓ · Tab: lompat · P: jeda</p>
        </div>
      </template>
    </template>

    <!-- ── FAILED ── -->
    <template v-else-if="gameState === 'failed'">
      <div class="card flex flex-col items-center gap-5 py-10 text-center">
        <div class="rounded-full bg-red-50 p-5 dark:bg-red-900/20">
          <svg class="h-10 w-10 text-red-500" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" /><path d="m15 9-6 6M9 9l6 6" /></svg>
        </div>
        <div>
          <h2 class="text-2xl font-black text-red-600">Game Over</h2>
          <p class="mt-1 text-sm font-medium text-slate-500">{{ MAX_MISTAKES }} kesalahan — puzzle gagal diselesaikan.</p>
        </div>
        <div class="grid w-full max-w-xs grid-cols-2 gap-3">
          <div class="rounded-xl bg-slate-50 p-3 text-center dark:bg-slate-800/50">
            <p class="text-xs font-extrabold uppercase text-slate-400">Waktu</p>
            <p class="mt-1 font-mono text-xl font-black text-slate-700 dark:text-slate-200">{{ formatTime(elapsedSeconds) }}</p>
          </div>
          <div class="rounded-xl bg-slate-50 p-3 text-center dark:bg-slate-800/50">
            <p class="text-xs font-extrabold uppercase text-slate-400">Level</p>
            <p class="mt-1 text-xl font-black text-slate-700 dark:text-slate-200">{{ LEVEL_LABELS[level] }}</p>
          </div>
        </div>
        <div class="flex gap-3">
          <button class="btn btn-primary" @click="startNewGame(level)">Coba lagi</button>
          <button class="btn btn-muted" @click="backToMenu">Pilih level</button>
        </div>
      </div>
    </template>

    <!-- ── COMPLETE ── -->
    <template v-else-if="gameState === 'complete'">
      <div class="card flex flex-col items-center gap-5 py-10 text-center">
        <div class="rounded-full bg-teal-50 p-5 dark:bg-teal-900/20">
          <svg class="h-10 w-10 text-teal-500" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5" /></svg>
        </div>
        <div>
          <h2 class="text-2xl font-black">
            {{ level === 'daily' ? 'Tantangan Selesai!' : 'Selesai!' }}
          </h2>
          <p class="mt-1 text-sm font-medium text-slate-500">Level {{ LEVEL_LABELS[level] }}</p>
        </div>

        <div>
          <span class="font-mono text-5xl font-black tabular-nums text-teal-600">{{ formatTime(elapsedSeconds) }}</span>
          <div class="mt-2 flex justify-center">
            <span v-if="completionRank === 1" class="pill border border-yellow-200 bg-yellow-50 text-yellow-700">Rekor baru!</span>
            <span v-else-if="completionRank" class="text-sm font-semibold text-slate-500">Rank #{{ completionRank }} personal</span>
          </div>
        </div>

        <!-- Stats -->
        <div class="grid w-full max-w-xs grid-cols-2 gap-3">
          <div class="rounded-xl bg-slate-50 p-3 text-center dark:bg-slate-800/50">
            <p class="text-xs font-extrabold uppercase text-slate-400">Kesalahan</p>
            <p class="mt-1 text-xl font-black" :class="gameStats.mistakes === 0 ? 'text-teal-600' : gameStats.mistakes < MAX_MISTAKES ? 'text-orange-500' : 'text-red-500'">
              {{ gameStats.mistakes }} / {{ MAX_MISTAKES }}
            </p>
          </div>
          <div class="rounded-xl bg-slate-50 p-3 text-center dark:bg-slate-800/50">
            <p class="text-xs font-extrabold uppercase text-slate-400">Hint</p>
            <p class="mt-1 text-xl font-black text-slate-700 dark:text-slate-200">{{ gameStats.hintsUsed }} / {{ MAX_HINTS }}</p>
          </div>
        </div>

        <!-- Top records -->
        <div v-if="records[level]?.length" class="w-full max-w-xs rounded-2xl border border-slate-100 bg-slate-50 p-4 text-left dark:border-slate-700 dark:bg-slate-800/50">
          <p class="mb-3 text-xs font-extrabold uppercase text-slate-500">Top Records — {{ LEVEL_LABELS[level] }}</p>
          <div class="space-y-2">
            <div
              v-for="(rec, idx) in records[level].slice(0, 5)"
              :key="rec.id"
              class="flex items-center gap-3 text-sm"
              :class="rec.id === completionRecord?.id ? 'font-black text-teal-700 dark:text-teal-400' : ''"
            >
              <span class="w-5 text-center font-black" :class="idx === 0 ? 'text-yellow-500' : 'text-slate-400'">#{{ idx + 1 }}</span>
              <span class="font-mono">{{ formatTime(rec.duration_seconds) }}</span>
              <span class="text-xs text-slate-400">{{ rec.completed_at?.slice(0, 10) }}</span>
              <span v-if="rec.id === completionRecord?.id" class="ml-auto text-xs font-bold text-teal-600 dark:text-teal-400">baru</span>
            </div>
          </div>
        </div>

        <div class="flex gap-3">
          <button v-if="level !== 'daily'" class="btn btn-primary" @click="startNewGame(level)">Main lagi</button>
          <button class="btn btn-muted" @click="backToMenu">Kembali ke menu</button>
        </div>
      </div>
    </template>
  </div>
</template>
