<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { api, unwrap } from '../api';
import { toast } from '../feedback';

type Level = 'easy' | 'medium' | 'hard' | 'daily';
type GameState = 'menu' | 'preview' | 'playing' | 'complete';

const LEVEL_LABELS: Record<Level, string> = {
  easy: 'Easy', medium: 'Medium', hard: 'Hard', daily: 'Daily',
};
const LEVEL_DESC: Record<Level, string> = {
  easy:   '4×4 · 8 pairs · 3s preview',
  medium: '4×6 · 12 pairs · 2s preview',
  hard:   '6×6 · 18 pairs · 1.5s preview',
  daily:  '4×6 · Same puzzle every day',
};
const PREVIEW_MS: Record<Level, number> = {
  easy: 3000, medium: 2000, hard: 1500, daily: 2000,
};
const REGULAR_LEVELS: Level[] = ['easy', 'medium', 'hard'];

// 18 distinct emojis — maps card value 1..18 to emoji
const EMOJIS = ['🦊','🐼','🦁','🐸','🦋','🐙','🦄','🐨','🦀','🦩','🐺','🦭','🦒','🐲','🦉','🐬','🦈','🦖'];

// ── State ──────────────────────────────────────────────────────────────────────
const gameState   = ref<GameState>('menu');
const sessionId   = ref<number | null>(null);
const level       = ref<Level>('easy');
const cards       = ref<number[]>([]);
const gridRows    = ref(4);
const gridCols    = ref(4);
const totalPairs  = ref(8);

const flipped     = ref<number[]>([]);
const matched     = ref<Set<number>>(new Set());
const moves       = ref(0);
const mismatches  = ref(0);
const isPreviewing = ref(false);
const lockFlip    = ref(false);

const elapsedSeconds = ref(0);
const timerStarted   = ref(false);
const isPaused       = ref(false);
let   timerInterval: ReturnType<typeof setInterval> | null = null;
let   matchTimeout:  ReturnType<typeof setTimeout>  | null = null;
let   saveTimeout:   ReturnType<typeof setTimeout>  | null = null;

const completing       = ref(false);
const loading          = ref(false);
const saving           = ref(false);
const completionRecord = ref<any>(null);
const completionRank   = ref<number | null>(null);
const isBest           = ref(false);
const isPerfect        = ref(false);
const pendingResume    = ref<any>(null);
const dailyStatus      = ref<any>(null);
const activeTab        = ref<Level>('easy');
const showRecords      = ref(false);
const records          = ref<Record<string, any[]>>({ easy: [], medium: [], hard: [], daily: [] });
const totals           = ref<Record<string, number>>({ easy: 0, medium: 0, hard: 0, daily: 0 });

// ── Computed ───────────────────────────────────────────────────────────────────
const matchedCount = computed(() => matched.value.size / 2);
const allMatched   = computed(() => matched.value.size === cards.value.length);

function cardEmoji(value: number): string { return EMOJIS[(value - 1) % EMOJIS.length]; }
function isFlipped(i: number): boolean  { return flipped.value.includes(i) || matched.value.has(i) || isPreviewing.value; }
function isMatched(i: number): boolean  { return matched.value.has(i); }

// ── Timer ──────────────────────────────────────────────────────────────────────
function startTimer() {
  if (timerInterval || timerStarted.value) return;
  timerStarted.value = true;
  timerInterval = setInterval(() => { if (!isPaused.value) elapsedSeconds.value++; }, 1000);
}
function stopTimer() {
  if (timerInterval) { clearInterval(timerInterval); timerInterval = null; }
}
function formatTime(s: number) {
  const m = Math.floor(s / 60);
  return `${m}:${String(s % 60).padStart(2, '0')}`;
}

// ── Flip logic ─────────────────────────────────────────────────────────────────
function flipCard(i: number) {
  if (isPreviewing.value || lockFlip.value || isPaused.value) return;
  if (gameState.value !== 'playing') return;
  if (matched.value.has(i) || flipped.value.includes(i)) return;
  if (flipped.value.length >= 2) return;

  // Start timer on first flip
  if (!timerStarted.value) startTimer();

  flipped.value = [...flipped.value, i];

  if (flipped.value.length === 2) {
    const [a, b] = flipped.value;
    moves.value++;

    if (cards.value[a] === cards.value[b]) {
      // Match!
      matched.value = new Set([...matched.value, a, b]);
      flipped.value = [];
      scheduleSave();

      if (allMatched.value) {
        handleComplete();
      }
    } else {
      // No match — lock briefly, then flip back
      mismatches.value++;
      lockFlip.value = true;
      matchTimeout = setTimeout(() => {
        flipped.value = [];
        lockFlip.value = false;
      }, 900);
    }
  }
}

function togglePause() {
  if (gameState.value !== 'playing') return;
  isPaused.value = !isPaused.value;
}

// ── API ────────────────────────────────────────────────────────────────────────
async function loadMenu() {
  loading.value = true;
  try {
    const [activeRes, dailyRes, recordsRes] = await Promise.all([
      api.get('/api/v1/games/memory/active').then(unwrap),
      api.get('/api/v1/games/memory/daily').then(unwrap),
      api.get('/api/v1/games/memory/records').then(unwrap),
    ]);
    // Always assign — clears stale pendingResume when server has no active session
    pendingResume.value = activeRes.session ?? null;
    dailyStatus.value = dailyRes;
    records.value = recordsRes.records;
    totals.value = recordsRes.totals;
  } catch { /* ignore */ } finally { loading.value = false; }
}

async function startGame(lv: Level) {
  if (loading.value) return;
  loading.value = true;
  try {
    const res = await api.post('/api/v1/games/memory/sessions', { level: lv }).then(unwrap);
    await initFromSession(res.session, true);
  } catch { toast({ tone: 'error', title: 'Failed to start game' }); } finally { loading.value = false; }
}

async function resumeGame(session: any) {
  await initFromSession(session, false);
  pendingResume.value = null;
}

async function initFromSession(session: any, isNew: boolean) {
  sessionId.value = session.id;
  level.value = session.level;
  cards.value = session.puzzle.cards;
  gridRows.value = session.puzzle.rows;
  gridCols.value = session.puzzle.cols;
  totalPairs.value = session.puzzle.pairs;
  elapsedSeconds.value = session.elapsed_seconds ?? 0;
  timerStarted.value = !isNew && (session.elapsed_seconds ?? 0) > 0;
  isPaused.value = false;
  completing.value = false;
  completionRecord.value = null;
  lockFlip.value = false;

  // Restore matched state from user_state
  const savedMatched: number[] = session.user_state?.matched ?? [];
  matched.value = new Set(savedMatched);
  flipped.value = [];
  moves.value = session.user_state?.moves ?? 0;
  mismatches.value = session.user_state?.mismatches ?? 0;

  gameState.value = 'playing';

  if (isNew) {
    // Preview: show all cards briefly
    isPreviewing.value = true;
    gameState.value = 'preview';
    await new Promise(r => setTimeout(r, 50)); // let Vue render
    gameState.value = 'playing';
    await new Promise(r => setTimeout(r, PREVIEW_MS[level.value]));
    isPreviewing.value = false;
  } else if (timerStarted.value) {
    timerInterval = setInterval(() => { if (!isPaused.value) elapsedSeconds.value++; }, 1000);
  }
}

function scheduleSave() {
  if (saveTimeout) clearTimeout(saveTimeout);
  saveTimeout = setTimeout(doSave, 2000);
}

async function doSave() {
  if (!sessionId.value || saving.value || gameState.value !== 'playing') return;
  saving.value = true;
  try {
    await api.patch(`/api/v1/games/memory/sessions/${sessionId.value}`, {
      user_state: { matched: [...matched.value], moves: moves.value, mismatches: mismatches.value },
      elapsed_seconds: elapsedSeconds.value,
      status: isPaused.value ? 'paused' : 'active',
    });
  } catch { /* ignore */ } finally { saving.value = false; }
}

async function handleComplete() {
  stopTimer();
  // Cancel any pending auto-save so it can't revert 'completed' status back to 'active'
  if (saveTimeout) { clearTimeout(saveTimeout); saveTimeout = null; }
  completing.value = true;
  if (!sessionId.value) { gameState.value = 'complete'; completing.value = false; return; }
  try {
    const res = await api.post(`/api/v1/games/memory/sessions/${sessionId.value}/complete`, {
      elapsed_seconds: Math.max(1, elapsedSeconds.value),
      moves: moves.value,
      mismatches: mismatches.value,
    }).then(unwrap);
    completionRecord.value = res.record;
    completionRank.value = res.rank;
    isBest.value = res.is_best;
    isPerfect.value = res.perfect;
    records.value = (await api.get('/api/v1/games/memory/records').then(unwrap)).records;
  } catch {
    toast({ tone: 'error', title: 'Failed to save result' });
  }
  gameState.value = 'complete';
  completing.value = false;
}

function goToMenu() {
  if (sessionId.value && gameState.value === 'playing') doSave();
  stopTimer();
  if (matchTimeout) clearTimeout(matchTimeout);
  gameState.value = 'menu';
  loadMenu();
}

function levelColor(lv: string): string {
  return { easy: 'teal', medium: 'blue', hard: 'red', daily: 'purple' }[lv] ?? 'teal';
}

onMounted(() => { loadMenu(); });
onUnmounted(() => {
  stopTimer();
  if (matchTimeout) clearTimeout(matchTimeout);
  if (saveTimeout) clearTimeout(saveTimeout);
  if (sessionId.value && gameState.value === 'playing') doSave();
});
</script>

<template>
  <div class="space-y-6">
    <!-- ── Menu ──────────────────────────────────────────────────────────── -->
    <template v-if="gameState === 'menu'">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-sm font-extrabold text-indigo-600 dark:text-indigo-400">Games</p>
          <h1 class="mt-1 text-3xl font-extrabold tracking-tight">Memory Match</h1>
          <p class="mt-1 text-sm font-medium text-slate-500 dark:text-zinc-400">Flip cards, find matching pairs, train your memory.</p>
        </div>
        <button class="btn btn-muted text-xs" @click="showRecords = !showRecords">
          {{ showRecords ? 'Close Records' : 'Records' }}
        </button>
      </div>

      <!-- Records panel -->
      <div v-if="showRecords" class="card p-5 space-y-4">
        <div class="flex gap-2 flex-wrap">
          <button v-for="lv in ['easy','medium','hard','daily']" :key="lv"
            class="px-3 py-1 rounded-lg text-xs font-bold transition"
            :class="activeTab === lv ? 'bg-indigo-500 text-white' : 'bg-slate-100 dark:bg-zinc-700 text-slate-600 dark:text-zinc-300'"
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
                <th class="pb-2">Time</th>
                <th class="pb-2">Moves</th>
                <th class="pb-2">Mismatches</th>
                <th class="pb-2 hidden sm:table-cell">Date</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(r, i) in records[activeTab]" :key="r.id" class="border-b border-slate-50 dark:border-zinc-800">
                <td class="py-2 text-slate-400 dark:text-zinc-500 font-bold">{{ i + 1 }}</td>
                <td class="py-2 font-extrabold text-indigo-600 dark:text-indigo-400 font-mono">{{ formatTime(r.duration_seconds) }}</td>
                <td class="py-2">{{ r.metadata?.moves ?? '—' }}</td>
                <td class="py-2">
                  <span v-if="r.metadata?.perfect" class="text-xs font-bold text-yellow-500">⭐ Perfect</span>
                  <span v-else>{{ r.metadata?.mismatches ?? '—' }}</span>
                </td>
                <td class="py-2 text-xs text-slate-400 dark:text-zinc-500 hidden sm:table-cell">{{ r.completed_at?.slice(0, 10) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-else class="text-sm text-slate-400 dark:text-zinc-500 text-center py-4">No records yet for this level.</p>
      </div>

      <!-- Resume -->
      <div v-if="pendingResume && !showRecords" class="card border-indigo-200 dark:border-indigo-800/40 p-4 flex items-center justify-between gap-4">
        <div>
          <p class="text-sm font-extrabold text-indigo-600 dark:text-indigo-400">Game in progress</p>
          <p class="text-xs text-slate-500 dark:text-zinc-400 mt-0.5">
            {{ LEVEL_LABELS[pendingResume.level as Level] }} · {{ pendingResume.user_state?.matched?.length / 2 ?? 0 }} pairs found · {{ formatTime(pendingResume.elapsed_seconds) }}
          </p>
        </div>
        <button class="btn btn-primary shrink-0" @click="resumeGame(pendingResume)">Resume</button>
      </div>

      <!-- Daily status -->
      <div v-if="dailyStatus && !showRecords" class="card p-4 flex items-center gap-4">
        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl text-xl"
          :class="dailyStatus.completed_today ? 'bg-green-50 dark:bg-green-900/20' : 'bg-indigo-50 dark:bg-indigo-900/20'">
          {{ dailyStatus.completed_today ? '✅' : '🃏' }}
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-extrabold">Daily</p>
          <p class="text-xs text-slate-500 dark:text-zinc-400 mt-0.5">
            <template v-if="dailyStatus.completed_today">
              Done · {{ formatTime(dailyStatus.record?.duration_seconds ?? 0) }} · {{ dailyStatus.record?.metadata?.moves ?? 0 }} moves
              <span v-if="dailyStatus.record?.metadata?.perfect" class="ml-1 text-yellow-500 font-bold">⭐ Perfect</span>
            </template>
            <template v-else-if="dailyStatus.session">Session in progress</template>
            <template v-else>Not played today</template>
          </p>
        </div>
        <button v-if="!dailyStatus.completed_today" class="btn btn-muted shrink-0 text-xs"
          @click="dailyStatus?.session ? resumeGame(dailyStatus.session) : startGame('daily')">
          {{ dailyStatus.session ? 'Resume' : 'Play' }}
        </button>
      </div>

      <!-- Level cards -->
      <div v-if="!showRecords" class="grid gap-4 sm:grid-cols-3">
        <button v-for="lv in REGULAR_LEVELS" :key="lv"
          class="card group flex flex-col items-start gap-3 p-5 transition text-left hover:shadow-md"
          :class="{
            'hover:border-teal-300 dark:hover:border-teal-700': lv === 'easy',
            'hover:border-blue-300 dark:hover:border-blue-700': lv === 'medium',
            'hover:border-red-300 dark:hover:border-red-700': lv === 'hard',
          }"
          :disabled="loading"
          @click="startGame(lv)">
          <div class="flex h-10 w-10 items-center justify-center rounded-xl text-2xl"
            :class="{
              'bg-teal-50 dark:bg-teal-900/20': lv === 'easy',
              'bg-blue-50 dark:bg-blue-900/20': lv === 'medium',
              'bg-red-50 dark:bg-red-900/20': lv === 'hard',
            }">
            {{ lv === 'easy' ? '🐸' : lv === 'medium' ? '🦋' : '🦉' }}
          </div>
          <div>
            <p class="font-extrabold">{{ LEVEL_LABELS[lv] }}</p>
            <p class="text-xs text-slate-500 dark:text-zinc-400 mt-0.5">{{ LEVEL_DESC[lv] }}</p>
          </div>
        </button>
      </div>
    </template>

    <!-- ── Playing ────────────────────────────────────────────────────────── -->
    <template v-if="gameState === 'playing' || gameState === 'preview'">
      <!-- Top bar -->
      <div class="flex items-center gap-3">
        <button class="btn btn-muted text-xs px-2 py-1" @click="goToMenu">← Menu</button>
        <p class="font-extrabold text-sm">{{ LEVEL_LABELS[level] }}</p>
        <span v-if="isPreviewing" class="text-xs font-bold text-indigo-500 animate-pulse ml-1">Memorize…</span>
        <span v-if="saving" class="text-xs text-slate-400 dark:text-zinc-500 ml-auto">Saving…</span>
        <button v-if="!isPreviewing" class="ml-auto btn btn-muted text-xs px-2 py-1" @click="togglePause">
          {{ isPaused ? '▶ Resume' : '⏸ Pause' }}
        </button>
      </div>

      <!-- Stats bar -->
      <div class="flex gap-3">
        <div class="flex-1 card p-3 text-center">
          <p class="text-xs font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wide">Pairs</p>
          <p class="text-2xl font-extrabold text-indigo-600 dark:text-indigo-400">{{ matchedCount }}<span class="text-base text-slate-400">/{{ totalPairs }}</span></p>
        </div>
        <div class="flex-1 card p-3 text-center">
          <p class="text-xs font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wide">Moves</p>
          <p class="text-2xl font-extrabold">{{ moves }}</p>
        </div>
        <div class="flex-1 card p-3 text-center">
          <p class="text-xs font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wide">Time</p>
          <p class="text-2xl font-extrabold font-mono">{{ formatTime(elapsedSeconds) }}</p>
        </div>
        <div class="card p-3 text-center">
          <p class="text-xs font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wide">Mismatches</p>
          <p class="text-2xl font-extrabold" :class="mismatches > 0 ? 'text-red-500' : 'text-slate-400 dark:text-zinc-600'">{{ mismatches }}</p>
        </div>
      </div>

      <!-- Board -->
      <div class="flex justify-center">
        <div class="relative">
          <div
            class="grid gap-2"
            :style="{
              gridTemplateColumns: `repeat(${gridCols}, minmax(0, 1fr))`,
              width: gridCols <= 4 ? 'min(320px, 95vw)' : gridCols <= 6 ? 'min(420px, 95vw)' : 'min(480px, 95vw)',
            }">
            <div
              v-for="(val, i) in cards" :key="i"
              class="card-wrapper aspect-square cursor-pointer select-none"
              :class="{ 'flipped': isFlipped(i), 'matched': isMatched(i) }"
              @click="flipCard(i)">
              <div class="card-inner w-full h-full">
                <!-- Card back (shown by default) -->
                <div class="card-face card-back-face flex items-center justify-center rounded-xl w-full h-full bg-indigo-500 dark:bg-indigo-600 shadow-sm">
                  <span class="text-white font-extrabold text-xl">?</span>
                </div>
                <!-- Card front (shown when flipped) -->
                <div class="card-face card-front-face flex items-center justify-center rounded-xl w-full h-full shadow-sm"
                  :class="isMatched(i) ? 'bg-teal-50 dark:bg-teal-900/30 ring-2 ring-teal-400' : 'bg-white dark:bg-zinc-800'">
                  <span
                    :class="gridCols <= 4 ? 'text-4xl' : gridCols <= 6 ? 'text-3xl' : 'text-2xl'">
                    {{ cardEmoji(val) }}
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- Pause overlay -->
          <div v-if="isPaused" class="absolute inset-0 rounded-2xl flex items-center justify-center bg-white/90 dark:bg-zinc-900/90">
            <div class="text-center">
              <p class="text-4xl">⏸</p>
              <p class="text-lg font-extrabold mt-2">Paused</p>
              <button class="btn btn-primary mt-4" @click="togglePause">Resume</button>
            </div>
          </div>
        </div>
      </div>

      <p v-if="!isPreviewing" class="text-xs text-center text-slate-400 dark:text-zinc-500">
        Click a card to flip it · Find all {{ totalPairs }} pairs
      </p>
    </template>

    <!-- ── Complete ───────────────────────────────────────────────────────── -->
    <template v-if="gameState === 'complete'">
      <div class="flex items-center gap-3">
        <button class="btn btn-muted text-xs px-2 py-1" @click="goToMenu">← Menu</button>
        <p class="font-extrabold text-sm">{{ LEVEL_LABELS[level] }}</p>
      </div>

      <div class="card p-8 text-center space-y-5">
        <div>
          <p class="text-5xl">{{ isPerfect ? '🌟' : '🎉' }}</p>
          <p class="text-2xl font-extrabold mt-3">Done!</p>
          <p v-if="isPerfect" class="text-sm font-bold text-yellow-500 mt-1">⭐ Perfect — No mismatches!</p>
        </div>

        <div class="flex justify-center gap-6">
          <div>
            <p class="text-xs text-slate-400 dark:text-zinc-500 font-bold uppercase">Time</p>
            <p class="text-3xl font-extrabold text-indigo-600 dark:text-indigo-400 font-mono">{{ formatTime(elapsedSeconds) }}</p>
          </div>
          <div>
            <p class="text-xs text-slate-400 dark:text-zinc-500 font-bold uppercase">Moves</p>
            <p class="text-3xl font-extrabold">{{ moves }}</p>
          </div>
          <div>
            <p class="text-xs text-slate-400 dark:text-zinc-500 font-bold uppercase">Mismatches</p>
            <p class="text-3xl font-extrabold" :class="mismatches > 0 ? 'text-red-500' : 'text-teal-500'">{{ mismatches }}</p>
          </div>
        </div>

        <div v-if="completionRecord" class="text-sm text-slate-500 dark:text-zinc-400">
          <span v-if="isBest" class="font-extrabold text-indigo-500">🏆 New best time!</span>
          <span v-else>Record #{{ completionRank }}</span>
        </div>

        <div class="flex gap-3 justify-center pt-2">
          <button class="btn btn-primary" @click="startGame(level)">Play Again</button>
          <button class="btn btn-muted" @click="goToMenu">Change Level</button>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.card-wrapper { perspective: 700px; }
.card-inner {
  position: relative;
  transform-style: preserve-3d;
  transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1);
}
.card-wrapper.flipped .card-inner { transform: rotateY(180deg); }
.card-face {
  position: absolute;
  inset: 0;
  backface-visibility: hidden;
  -webkit-backface-visibility: hidden;
}
.card-back-face  { transform: rotateY(0deg); }
.card-front-face { transform: rotateY(180deg); }
.card-wrapper.matched .card-inner { transform: rotateY(180deg); }
</style>
