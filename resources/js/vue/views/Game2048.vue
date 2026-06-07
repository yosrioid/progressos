<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { api, unwrap } from '../api';
import { toast } from '../feedback';

type Level = 'classic' | 'large' | 'challenge' | 'daily';
type GameState = 'menu' | 'playing' | 'gameover';
type Board = number[][];

const LEVEL_LABELS: Record<Level, string> = {
  classic: 'Klasik', large: 'Luas', challenge: 'Tantangan', daily: 'Harian',
};
const LEVEL_DESC: Record<Level, string> = {
  classic:   '4×4 · Target 2048 · Standar',
  large:     '5×5 · Lebih luas · Skor lebih tinggi',
  challenge: '4×4 · Target 4096 · Lebih sulit',
  daily:     '4×4 · Puzzle sama setiap hari',
};
const REGULAR_LEVELS: Level[] = ['classic', 'large', 'challenge'];
const TILE_COLORS: Record<number, { bg: string; fg: string }> = {
  2:     { bg: '#eee4da', fg: '#776e65' },
  4:     { bg: '#ede0c8', fg: '#776e65' },
  8:     { bg: '#f2b179', fg: '#f9f6f2' },
  16:    { bg: '#f59563', fg: '#f9f6f2' },
  32:    { bg: '#f67c5f', fg: '#f9f6f2' },
  64:    { bg: '#f65e3b', fg: '#f9f6f2' },
  128:   { bg: '#edcf72', fg: '#f9f6f2' },
  256:   { bg: '#edcc61', fg: '#f9f6f2' },
  512:   { bg: '#edc850', fg: '#f9f6f2' },
  1024:  { bg: '#edc53f', fg: '#f9f6f2' },
  2048:  { bg: '#edc22e', fg: '#f9f6f2' },
  4096:  { bg: '#3c3a32', fg: '#f9f6f2' },
  8192:  { bg: '#3c3a32', fg: '#f9f6f2' },
};

// ── State ──────────────────────────────────────────────────────────────────────
const gameState   = ref<GameState>('menu');
const sessionId   = ref<number | null>(null);
const level       = ref<Level>('classic');
const board       = ref<Board>([]);
const score       = ref(0);
const size        = ref(4);
const winTarget   = ref(2048);
const moveCount   = ref(0);
const undoCount   = ref(0);
const MAX_UNDO    = 5;
const history     = ref<{ board: Board; score: number }[]>([]);
const wonShown    = ref(false);
const showWin     = ref(false);
const completing  = ref(false);
const loading     = ref(false);
const saving      = ref(false);
const isPaused    = ref(false);
const activeTab   = ref<Level>('classic');
const showRecords = ref(false);

const elapsedSeconds = ref(0);
let timerInterval: ReturnType<typeof setInterval> | null = null;
let saveTimeout: ReturnType<typeof setTimeout> | null = null;

const newTilePos   = ref<Set<number>>(new Set());
const mergedPos    = ref<Set<number>>(new Set());
const scoreFlash   = ref<{ amount: number; id: number } | null>(null);
let flashId = 0;

const completionRecord = ref<any>(null);
const completionRank   = ref<number | null>(null);
const isBest           = ref(false);
const pendingResume    = ref<any>(null);
const dailyStatus      = ref<any>(null);
const records          = ref<Record<string, any[]>>({ classic: [], large: [], challenge: [], daily: [] });
const totals           = ref<Record<string, number>>({ classic: 0, large: 0, challenge: 0, daily: 0 });
const bestScores       = ref<Record<string, number>>({ classic: 0, large: 0, challenge: 0, daily: 0 });

// ── Computed ───────────────────────────────────────────────────────────────────
const flatBoard = computed(() => board.value.flat());
const bestScore = computed(() => bestScores.value[level.value] ?? 0);
const maxTile   = computed(() => Math.max(0, ...flatBoard.value));

// ── Timer ──────────────────────────────────────────────────────────────────────
function startTimer() {
  if (timerInterval) return;
  timerInterval = setInterval(() => {
    if (!isPaused.value) elapsedSeconds.value++;
  }, 1000);
}
function stopTimer() {
  if (timerInterval) { clearInterval(timerInterval); timerInterval = null; }
}
function formatTime(s: number) {
  const m = Math.floor(s / 60);
  return `${m}:${String(s % 60).padStart(2, '0')}`;
}

// ── Board helpers ──────────────────────────────────────────────────────────────
function cloneBoard(b: Board): Board { return b.map(r => [...r]); }

function moveLeft(b: Board): { board: Board; gained: number; moved: boolean; merged: number[] } {
  const s = b.length; let gained = 0; let moved = false;
  const merged: number[] = [];
  const nb: Board = b.map((row, r) => {
    const tiles = row.filter(v => v !== 0);
    const out: number[] = [];
    let i = 0;
    while (i < tiles.length) {
      if (i + 1 < tiles.length && tiles[i] === tiles[i + 1]) {
        const val = tiles[i] * 2;
        out.push(val);
        gained += val;
        merged.push(r * s + out.length - 1);
        i += 2;
      } else { out.push(tiles[i]); i++; }
    }
    while (out.length < s) out.push(0);
    return out;
  });
  for (let r = 0; r < s; r++)
    for (let c = 0; c < b[r].length; c++)
      if (nb[r][c] !== b[r][c]) { moved = true; break; }
  return { board: nb, gained, moved, merged };
}
function moveRight(b: Board) {
  const rev = b.map(r => [...r].reverse());
  const res = moveLeft(rev);
  const cols = b[0].length;
  return { ...res, board: res.board.map(r => [...r].reverse()), merged: res.merged.map(i => { const r = Math.floor(i / cols); const c = cols - 1 - (i % cols); return r * cols + c; }) };
}
function transpose(b: Board): Board {
  return Array.from({ length: b[0].length }, (_, c) => Array.from({ length: b.length }, (_, r) => b[r][c]));
}
function moveUp(b: Board) {
  const t = transpose(b); const res = moveLeft(t);
  const rows = b.length; const cols = b[0].length;
  return { ...res, board: transpose(res.board), merged: res.merged.map(i => { const r = Math.floor(i / rows); const c = i % rows; return c * cols + r; }) };
}
function moveDown(b: Board) {
  const t = transpose(b); const res = moveRight(t);
  const rows = b.length; const cols = b[0].length;
  return { ...res, board: transpose(res.board), merged: res.merged.map(i => { const r = Math.floor(i / rows); const c = i % rows; return c * cols + r; }) };
}

function addRandomTile(b: Board): number {
  const empty: number[] = [];
  for (let r = 0; r < b.length; r++)
    for (let c = 0; c < b[r].length; c++)
      if (b[r][c] === 0) empty.push(r * b[r].length + c);
  if (!empty.length) return -1;
  const idx = empty[Math.floor(Math.random() * empty.length)];
  const r = Math.floor(idx / b[0].length); const c = idx % b[0].length;
  b[r][c] = Math.random() < 0.9 ? 2 : 4;
  return idx;
}

function isGameOver(b: Board): boolean {
  for (const row of b) if (row.includes(0)) return false;
  const rows = b.length; const cols = b[0].length;
  for (let r = 0; r < rows; r++)
    for (let c = 0; c < cols; c++) {
      if (c + 1 < cols && b[r][c] === b[r][c + 1]) return false;
      if (r + 1 < rows && b[r][c] === b[r + 1][c]) return false;
    }
  return true;
}

function tileStyle(v: number): Record<string, string> {
  if (!v) return { background: 'rgba(205,193,180,0.35)', color: 'transparent' };
  const c = TILE_COLORS[v] ?? { bg: '#3c3a32', fg: '#f9f6f2' };
  return { background: c.bg, color: c.fg };
}
function tileFontClass(v: number): string {
  if (!v) return '';
  if (v < 100)   return 'text-3xl';
  if (v < 1000)  return 'text-2xl';
  if (v < 10000) return 'text-xl';
  return 'text-base';
}

// ── Move ───────────────────────────────────────────────────────────────────────
function move(dir: 'left' | 'right' | 'up' | 'down') {
  if (gameState.value !== 'playing' || isPaused.value || showWin.value) return;

  const fns = { left: moveLeft, right: moveRight, up: moveUp, down: moveDown };
  const res = fns[dir](board.value);
  if (!res.moved) return;

  // Push undo history (limit 5)
  if (history.value.length >= MAX_UNDO) history.value.shift();
  history.value.push({ board: cloneBoard(board.value), score: score.value });

  board.value = res.board;
  score.value += res.gained;
  moveCount.value++;

  // Score flash
  if (res.gained > 0) {
    scoreFlash.value = { amount: res.gained, id: ++flashId };
    setTimeout(() => { if (scoreFlash.value?.id === flashId) scoreFlash.value = null; }, 700);
  }

  // Merge animation
  mergedPos.value = new Set(res.merged);
  setTimeout(() => mergedPos.value.clear(), 120);

  // Add new tile
  const newIdx = addRandomTile(board.value);
  if (newIdx >= 0) {
    newTilePos.value = new Set([newIdx]);
    setTimeout(() => newTilePos.value.clear(), 200);
  }

  // Update best score
  if (score.value > (bestScores.value[level.value] ?? 0)) {
    bestScores.value[level.value] = score.value;
  }

  // Check win (first time reaching target)
  if (!wonShown.value && flatBoard.value.some(v => v >= winTarget.value)) {
    wonShown.value = true;
    showWin.value = true;
    stopTimer();
    return;
  }

  // Auto-save every 5 moves
  if (moveCount.value % 5 === 0) scheduleSave();

  // Check game over
  if (isGameOver(board.value)) {
    handleGameOver();
  }
}

function undo() {
  if (!history.value.length || gameState.value !== 'playing') return;
  const prev = history.value.pop()!;
  board.value = prev.board;
  score.value = prev.score;
  undoCount.value++;
  showWin.value = false;
  if (isPaused.value) { isPaused.value = false; startTimer(); }
}

function continueAfterWin() {
  showWin.value = false;
  if (!timerInterval) startTimer();
}

// ── Touch swipe ────────────────────────────────────────────────────────────────
let touchX = 0; let touchY = 0;
function onTouchStart(e: TouchEvent) { touchX = e.touches[0].clientX; touchY = e.touches[0].clientY; }
function onTouchEnd(e: TouchEvent) {
  const dx = e.changedTouches[0].clientX - touchX;
  const dy = e.changedTouches[0].clientY - touchY;
  if (Math.max(Math.abs(dx), Math.abs(dy)) < 24) return;
  if (Math.abs(dx) > Math.abs(dy)) move(dx > 0 ? 'right' : 'left');
  else move(dy > 0 ? 'down' : 'up');
}

// ── Keyboard ───────────────────────────────────────────────────────────────────
function onKey(e: KeyboardEvent) {
  if (gameState.value !== 'playing') return;
  const map: Record<string, 'left' | 'right' | 'up' | 'down'> = {
    ArrowLeft: 'left', ArrowRight: 'right', ArrowUp: 'up', ArrowDown: 'down',
    a: 'left', d: 'right', w: 'up', s: 'down',
  };
  if (map[e.key]) { e.preventDefault(); move(map[e.key]); }
  if (e.key === 'z' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); undo(); }
  if (e.key === 'p') togglePause();
}

// ── API ────────────────────────────────────────────────────────────────────────
async function loadMenu() {
  loading.value = true;
  try {
    const [activeRes, dailyRes, recordsRes] = await Promise.all([
      api.get('/api/v1/games/2048/active').then(unwrap),
      api.get('/api/v1/games/2048/daily').then(unwrap),
      api.get('/api/v1/games/2048/records').then(unwrap),
    ]);
    // Always assign so a completed/gameover return clears any stale pendingResume.
    // For in-progress returns, goToMenu() already set pendingResume from client state;
    // the server response (same session) simply overwrites with authoritative data.
    pendingResume.value = activeRes.session ?? null;
    dailyStatus.value = dailyRes;
    records.value = recordsRes.records;
    totals.value = recordsRes.totals;
    // Compute best scores from records
    for (const lvl of ['classic', 'large', 'challenge', 'daily'] as Level[]) {
      const best = (recordsRes.records[lvl] ?? []).reduce((max: number, r: any) => Math.max(max, r.metadata?.score ?? 0), 0);
      bestScores.value[lvl] = best;
    }
  } catch { /* ignore */ } finally { loading.value = false; }
}

async function startGame(lv: Level) {
  if (loading.value) return;
  loading.value = true;
  try {
    const res = await api.post('/api/v1/games/2048/sessions', { level: lv }).then(unwrap);
    initFromSession(res.session);
  } catch { toast({ tone: 'error', title: 'Gagal memulai game' }); } finally { loading.value = false; }
}

async function resumeGame(session: any) {
  initFromSession(session);
  pendingResume.value = null;
}

function initFromSession(session: any) {
  sessionId.value = session.id;
  level.value = session.level;
  size.value = session.puzzle.size;
  winTarget.value = session.puzzle.win_target;
  board.value = session.user_state?.board ?? session.puzzle.board;
  score.value = session.user_state?.score ?? 0;
  elapsedSeconds.value = session.elapsed_seconds ?? 0;
  history.value = [];
  undoCount.value = 0;
  moveCount.value = 0;
  wonShown.value = flatBoard.value.some(v => v >= winTarget.value);
  showWin.value = false;
  completing.value = false;
  completionRecord.value = null;
  gameState.value = 'playing';
  isPaused.value = false;
  startTimer();
}

function scheduleSave() {
  if (saveTimeout) clearTimeout(saveTimeout);
  saveTimeout = setTimeout(doSave, 1500);
}

async function doSave() {
  if (!sessionId.value || saving.value || gameState.value !== 'playing') return;
  saving.value = true;
  try {
    await api.patch(`/api/v1/games/2048/sessions/${sessionId.value}`, {
      user_state: { board: board.value, score: score.value },
      elapsed_seconds: elapsedSeconds.value,
      status: isPaused.value ? 'paused' : 'active',
    });
  } catch { /* ignore */ } finally { saving.value = false; }
}

async function handleGameOver() {
  stopTimer();
  // Cancel any pending auto-save so it can't write 'active' status after completion
  if (saveTimeout) { clearTimeout(saveTimeout); saveTimeout = null; }
  completing.value = true;
  if (!sessionId.value) { gameState.value = 'gameover'; completing.value = false; return; }
  try {
    const res = await api.post(`/api/v1/games/2048/sessions/${sessionId.value}/complete`, {
      board: board.value,
      score: score.value,
      max_tile: maxTile.value,
      elapsed_seconds: elapsedSeconds.value,
    }).then(unwrap);
    completionRecord.value = res.record;
    completionRank.value = res.rank;
    isBest.value = res.is_best;
    records.value = (await api.get('/api/v1/games/2048/records').then(unwrap)).records;
  } catch { /* ignore */ }
  gameState.value = 'gameover';
  completing.value = false;
}

function togglePause() {
  if (gameState.value !== 'playing') return;
  isPaused.value = !isPaused.value;
}

function goToMenu() {
  const wasPlaying = gameState.value === 'playing';
  if (sessionId.value && wasPlaying) doSave();
  stopTimer();
  gameState.value = 'menu';
  // Only offer resume if game was still in progress — not when won or game over
  pendingResume.value = (sessionId.value && wasPlaying) ? {
    id: sessionId.value,
    level: level.value,
    puzzle: { size: size.value, win_target: winTarget.value, board: board.value },
    user_state: { board: board.value, score: score.value },
    elapsed_seconds: elapsedSeconds.value,
  } : null;
  loadMenu();
}

// ── Format helpers ─────────────────────────────────────────────────────────────
function fmtScore(n: number) { return n >= 1000 ? `${(n / 1000).toFixed(1)}k` : String(n); }
function levelColor(lv: string): string {
  return { classic: 'teal', large: 'blue', challenge: 'red', daily: 'purple' }[lv] ?? 'teal';
}
function tileColor(v: number): string {
  const s = TILE_COLORS[v] ?? { bg: '#3c3a32' };
  return s.bg;
}

onMounted(() => {
  loadMenu();
  window.addEventListener('keydown', onKey);
});
onUnmounted(() => {
  stopTimer();
  if (saveTimeout) clearTimeout(saveTimeout);
  window.removeEventListener('keydown', onKey);
  if (sessionId.value && gameState.value === 'playing') doSave();
});
</script>

<template>
  <div class="space-y-6">
    <!-- ── Menu ──────────────────────────────────────────────────────────── -->
    <template v-if="gameState === 'menu'">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-sm font-extrabold text-amber-600 dark:text-amber-400">Games</p>
          <h1 class="mt-1 text-3xl font-extrabold tracking-tight">2048</h1>
          <p class="mt-1 text-sm font-medium text-slate-500 dark:text-zinc-400">Geser tile, gabungkan angka, capai target.</p>
        </div>
        <button class="btn btn-muted text-xs" @click="showRecords = !showRecords">
          {{ showRecords ? 'Tutup Rekord' : 'Rekord' }}
        </button>
      </div>

      <!-- Records panel -->
      <div v-if="showRecords" class="card p-5 space-y-4">
        <div class="flex gap-2 flex-wrap">
          <button v-for="lv in ['classic','large','challenge','daily']" :key="lv"
            class="px-3 py-1 rounded-lg text-xs font-bold transition"
            :class="activeTab === lv ? 'bg-amber-500 text-white' : 'bg-slate-100 dark:bg-zinc-700 text-slate-600 dark:text-zinc-300'"
            @click="activeTab = lv as Level">
            {{ LEVEL_LABELS[lv as Level] }}
            <span class="ml-1 opacity-60">({{ totals[lv] }})</span>
          </button>
        </div>
        <div v-if="records[activeTab]?.length">
          <table class="w-full text-sm">
            <thead>
              <tr class="text-left text-xs font-bold text-slate-400 dark:text-zinc-500 border-b border-slate-100 dark:border-zinc-700">
                <th class="pb-2">#</th>
                <th class="pb-2">Skor</th>
                <th class="pb-2">Tile Max</th>
                <th class="pb-2">Waktu</th>
                <th class="pb-2 hidden sm:table-cell">Tanggal</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(r, i) in records[activeTab]" :key="r.id" class="border-b border-slate-50 dark:border-zinc-800">
                <td class="py-2 text-slate-400 dark:text-zinc-500 font-bold">{{ i + 1 }}</td>
                <td class="py-2 font-extrabold text-amber-600 dark:text-amber-400">{{ r.metadata?.score?.toLocaleString() ?? 0 }}</td>
                <td class="py-2">
                  <span class="inline-block px-2 py-0.5 rounded text-xs font-extrabold text-white" :style="{ background: tileColor(r.metadata?.max_tile ?? 0) }">
                    {{ r.metadata?.max_tile ?? '—' }}
                  </span>
                </td>
                <td class="py-2 font-mono text-xs">{{ formatTime(r.duration_seconds) }}</td>
                <td class="py-2 text-xs text-slate-400 dark:text-zinc-500 hidden sm:table-cell">{{ r.completed_at?.slice(0, 10) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-else class="text-sm text-slate-400 dark:text-zinc-500 text-center py-4">Belum ada rekord untuk level ini.</p>
      </div>

      <!-- Resume -->
      <div v-if="pendingResume && !showRecords" class="card border-amber-200 dark:border-amber-800/40 p-4 flex items-center justify-between gap-4">
        <div>
          <p class="text-sm font-extrabold text-amber-600 dark:text-amber-400">Game tertunda</p>
          <p class="text-xs text-slate-500 dark:text-zinc-400 mt-0.5">
            {{ LEVEL_LABELS[pendingResume.level as Level] }} · Skor {{ pendingResume.user_state?.score?.toLocaleString() ?? 0 }} · {{ formatTime(pendingResume.elapsed_seconds) }}
          </p>
        </div>
        <button class="btn btn-primary shrink-0" @click="resumeGame(pendingResume)">Lanjutkan</button>
      </div>

      <!-- Daily status -->
      <div v-if="dailyStatus && !showRecords" class="card p-4 flex items-center gap-4">
        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl text-xl"
          :class="dailyStatus.completed_today ? 'bg-green-50 dark:bg-green-900/20' : 'bg-amber-50 dark:bg-amber-900/20'">
          {{ dailyStatus.completed_today ? '✅' : '🎯' }}
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-extrabold">Harian</p>
          <p class="text-xs text-slate-500 dark:text-zinc-400 mt-0.5">
            <template v-if="dailyStatus.completed_today">
              Selesai · Skor {{ dailyStatus.record?.metadata?.score?.toLocaleString() ?? 0 }} · Tile {{ dailyStatus.record?.metadata?.max_tile ?? '—' }}
            </template>
            <template v-else-if="dailyStatus.session">Ada sesi tertunda</template>
            <template v-else>Belum dimainkan hari ini</template>
          </p>
        </div>
        <button v-if="!dailyStatus.completed_today" class="btn btn-muted shrink-0 text-xs"
          @click="dailyStatus?.session ? resumeGame(dailyStatus.session) : startGame('daily')">
          {{ dailyStatus.session ? 'Lanjutkan' : 'Main' }}
        </button>
      </div>

      <!-- Level cards -->
      <div v-if="!showRecords" class="grid gap-4 sm:grid-cols-2">
        <button v-for="lv in REGULAR_LEVELS" :key="lv"
          class="card group flex flex-col items-start gap-3 p-5 transition text-left hover:shadow-md"
          :class="{
            'hover:border-teal-300 dark:hover:border-teal-700': lv === 'classic',
            'hover:border-blue-300 dark:hover:border-blue-700': lv === 'large',
            'hover:border-red-300 dark:hover:border-red-700': lv === 'challenge',
          }"
          :disabled="loading"
          @click="startGame(lv)">
          <div class="flex h-10 w-10 items-center justify-center rounded-xl text-2xl font-extrabold"
            :style="{ background: TILE_COLORS[lv === 'classic' ? 1024 : lv === 'large' ? 2048 : 4096]?.bg ?? '#edc22e', color: '#f9f6f2' }">
            {{ lv === 'classic' ? '2K' : lv === 'large' ? '5×' : '4K' }}
          </div>
          <div>
            <p class="font-extrabold">{{ LEVEL_LABELS[lv] }}</p>
            <p class="text-xs text-slate-500 dark:text-zinc-400 mt-0.5">{{ LEVEL_DESC[lv] }}</p>
            <p v-if="bestScores[lv]" class="text-xs font-bold text-amber-600 dark:text-amber-400 mt-1">
              Best: {{ bestScores[lv].toLocaleString() }}
            </p>
          </div>
        </button>
      </div>
    </template>

    <!-- ── Playing ────────────────────────────────────────────────────────── -->
    <template v-if="gameState === 'playing'">
      <!-- Top bar -->
      <div class="flex items-center gap-3">
        <button class="btn btn-muted text-xs px-2 py-1" @click="goToMenu">← Menu</button>
        <p class="font-extrabold text-sm">{{ LEVEL_LABELS[level] }}</p>
        <span v-if="saving" class="text-xs text-slate-400 dark:text-zinc-500 ml-auto">Menyimpan…</span>
        <div class="ml-auto flex gap-2">
          <button class="btn btn-muted text-xs px-2 py-1" :disabled="!history.length" @click="undo">
            ↩ Undo <span class="opacity-60">({{ history.length }}/{{ MAX_UNDO }})</span>
          </button>
          <button class="btn btn-muted text-xs px-2 py-1" @click="togglePause">
            {{ isPaused ? '▶ Lanjut' : '⏸ Jeda' }}
          </button>
        </div>
      </div>

      <!-- Score bar -->
      <div class="flex gap-3 items-stretch">
        <div class="flex-1 rounded-2xl p-3 text-center" style="background:#bbada0">
          <p class="text-xs font-bold text-white/70 uppercase tracking-wide">Skor</p>
          <div class="relative">
            <p class="text-2xl font-extrabold text-white">{{ score.toLocaleString() }}</p>
            <Transition name="score-pop">
              <span v-if="scoreFlash" :key="scoreFlash.id" class="score-flash absolute -top-1 left-1/2 -translate-x-1/2 text-sm font-extrabold text-white pointer-events-none">
                +{{ scoreFlash.amount }}
              </span>
            </Transition>
          </div>
        </div>
        <div class="rounded-2xl p-3 text-center min-w-[80px]" style="background:#bbada0">
          <p class="text-xs font-bold text-white/70 uppercase tracking-wide">Best</p>
          <p class="text-xl font-extrabold text-white">{{ fmtScore(Math.max(score, bestScore)) }}</p>
        </div>
        <div class="rounded-2xl p-3 text-center min-w-[72px]" style="background:#bbada0">
          <p class="text-xs font-bold text-white/70 uppercase tracking-wide">Waktu</p>
          <p class="text-xl font-extrabold text-white font-mono">{{ formatTime(elapsedSeconds) }}</p>
        </div>
      </div>

      <!-- Board -->
      <div class="flex justify-center" @touchstart.prevent="onTouchStart" @touchend.prevent="onTouchEnd">
        <div class="relative">
          <div
            class="grid gap-2 rounded-2xl p-3 select-none"
            style="background:#bbada0"
            :style="{
              gridTemplateColumns: `repeat(${size}, minmax(0, 1fr))`,
              width: size === 5 ? 'min(420px, 95vw)' : 'min(360px, 95vw)',
            }">
            <div
              v-for="(val, i) in flatBoard" :key="i"
              class="aspect-square flex items-center justify-center rounded-xl font-extrabold select-none transition-colors duration-100"
              :class="[tileFontClass(val), newTilePos.has(i) ? 'tile-new' : '', mergedPos.has(i) ? 'tile-merge' : '']"
              :style="tileStyle(val)">
              {{ val || '' }}
            </div>
          </div>

          <!-- Pause overlay -->
          <div v-if="isPaused" class="absolute inset-0 rounded-2xl flex items-center justify-center" style="background:rgba(187,173,160,0.9)">
            <div class="text-center">
              <p class="text-4xl font-extrabold text-white">⏸</p>
              <p class="text-lg font-extrabold text-white mt-2">Dijeda</p>
              <button class="btn btn-primary mt-4" @click="togglePause">Lanjutkan</button>
            </div>
          </div>

          <!-- Win overlay -->
          <div v-if="showWin" class="absolute inset-0 rounded-2xl flex items-center justify-center" style="background:rgba(237,194,46,0.95)">
            <div class="text-center p-6">
              <p class="text-5xl font-extrabold text-white">🎉</p>
              <p class="text-2xl font-extrabold text-white mt-2">
                {{ winTarget.toLocaleString() }}!
              </p>
              <p class="text-sm text-white/80 mt-1">Skor {{ score.toLocaleString() }}</p>
              <div class="flex gap-3 mt-5 justify-center">
                <button class="btn font-extrabold text-amber-700 bg-white hover:bg-yellow-50" @click="continueAfterWin">
                  Lanjutkan ›
                </button>
                <button class="btn font-bold text-white/80 border border-white/40 hover:bg-white/10" @click="startGame(level)">
                  Game Baru
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <p class="text-xs text-center text-slate-400 dark:text-zinc-500">
        Arrow keys / WASD untuk geser · Ctrl+Z untuk undo · Target: <strong>{{ winTarget.toLocaleString() }}</strong>
      </p>
    </template>

    <!-- ── Game Over ───────────────────────────────────────────────────────── -->
    <template v-if="gameState === 'gameover'">
      <div class="flex items-center gap-3">
        <button class="btn btn-muted text-xs px-2 py-1" @click="goToMenu">← Menu</button>
        <p class="font-extrabold text-sm">{{ LEVEL_LABELS[level] }}</p>
      </div>

      <div class="card p-8 text-center space-y-4">
        <p class="text-5xl">😤</p>
        <div>
          <p class="text-2xl font-extrabold">Game Over</p>
          <p class="text-sm text-slate-500 dark:text-zinc-400 mt-1">Tidak ada gerakan tersisa</p>
        </div>

        <div class="flex justify-center gap-6">
          <div>
            <p class="text-xs text-slate-400 dark:text-zinc-500 font-bold uppercase">Skor</p>
            <p class="text-3xl font-extrabold text-amber-600 dark:text-amber-400">{{ score.toLocaleString() }}</p>
          </div>
          <div>
            <p class="text-xs text-slate-400 dark:text-zinc-500 font-bold uppercase">Tile Max</p>
            <div class="flex items-center justify-center mt-0.5">
              <span class="text-2xl font-extrabold px-3 py-1 rounded-xl text-white" :style="{ background: tileColor(maxTile) }">
                {{ maxTile }}
              </span>
            </div>
          </div>
          <div>
            <p class="text-xs text-slate-400 dark:text-zinc-500 font-bold uppercase">Waktu</p>
            <p class="text-3xl font-extrabold font-mono">{{ formatTime(elapsedSeconds) }}</p>
          </div>
        </div>

        <div v-if="completionRecord" class="text-sm text-slate-500 dark:text-zinc-400">
          <span v-if="isBest" class="font-extrabold text-amber-500">🏆 Skor tertinggi baru!</span>
          <span v-else>Rekord ke-{{ completionRank }} dari skor terbaik</span>
        </div>

        <div class="flex gap-3 justify-center pt-2">
          <button class="btn btn-primary" @click="startGame(level)">Main Lagi</button>
          <button class="btn btn-muted" @click="goToMenu">Ganti Level</button>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.tile-new { animation: tile-pop 0.15s ease-out; }
.tile-merge { animation: tile-merge 0.12s ease-out; }
@keyframes tile-pop {
  0%   { transform: scale(0); }
  60%  { transform: scale(1.12); }
  100% { transform: scale(1); }
}
@keyframes tile-merge {
  0%   { transform: scale(1); }
  50%  { transform: scale(1.14); }
  100% { transform: scale(1); }
}
.score-pop-enter-active { animation: score-up 0.7s ease-out forwards; }
@keyframes score-up {
  0%   { opacity: 1; transform: translateX(-50%) translateY(0); }
  100% { opacity: 0; transform: translateX(-50%) translateY(-24px); }
}
.score-flash { white-space: nowrap; }
</style>
