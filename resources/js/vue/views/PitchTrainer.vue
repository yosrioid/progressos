<script setup lang="ts">
import { ref, computed, onUnmounted } from 'vue';
import { api } from '../api';

// ─── Audio ───────────────────────────────────────────────────────────────────

let audioCtx: AudioContext | null = null;

function getCtx(): AudioContext {
  if (!audioCtx || audioCtx.state === 'closed') audioCtx = new AudioContext();
  if (audioCtx.state === 'suspended') audioCtx.resume();
  return audioCtx;
}

const NOTE_FREQS: Record<string, number> = {
  C: 261.63, 'C#': 277.18, D: 293.66, 'D#': 311.13,
  E: 329.63, F: 349.23, 'F#': 369.99, G: 392.0,
  'G#': 415.3, A: 440.0, 'A#': 466.16, B: 493.88,
};

function playNote(note: string, durationSec = 0.65) {
  const ctx = getCtx();
  const freq = NOTE_FREQS[note];
  if (!freq) return;
  const now = ctx.currentTime;

  const osc1 = ctx.createOscillator();
  const g1 = ctx.createGain();
  osc1.type = 'triangle';
  osc1.frequency.value = freq;
  g1.gain.setValueAtTime(0, now);
  g1.gain.linearRampToValueAtTime(0.28, now + 0.008);
  g1.gain.exponentialRampToValueAtTime(0.001, now + durationSec);
  osc1.connect(g1);
  g1.connect(ctx.destination);
  osc1.start(now);
  osc1.stop(now + durationSec + 0.05);

  const osc2 = ctx.createOscillator();
  const g2 = ctx.createGain();
  osc2.type = 'sine';
  osc2.frequency.value = freq * 2;
  g2.gain.setValueAtTime(0, now);
  g2.gain.linearRampToValueAtTime(0.06, now + 0.008);
  g2.gain.exponentialRampToValueAtTime(0.001, now + durationSec * 0.6);
  osc2.connect(g2);
  g2.connect(ctx.destination);
  osc2.start(now);
  osc2.stop(now + durationSec);
}

// ─── Level configs ────────────────────────────────────────────────────────────

type Level = 'natural' | 'chromatic';

const LEVEL_CFG = {
  natural: {
    label: 'Natural',
    notes: ['C', 'D', 'E', 'F', 'G', 'A', 'B'] as string[],
    timeLimit: 60,
    desc: '7 natural notes · 60 seconds',
  },
  chromatic: {
    label: 'Chromatic',
    notes: ['C', 'C#', 'D', 'D#', 'E', 'F', 'F#', 'G', 'G#', 'A', 'A#', 'B'] as string[],
    timeLimit: 60,
    desc: '12 notes (including sharps) · 60 seconds',
  },
};

const BASE_PTS = 10;
const STREAK_BONUS = 2; // pts per streak level

// ─── State ────────────────────────────────────────────────────────────────────

type Phase = 'menu' | 'playing' | 'done' | 'saved';

const phase = ref<Phase>('menu');
const level = ref<Level>('natural');
const currentNote = ref('');
const choices = ref<string[]>([]);
const lastResult = ref<'correct' | 'wrong' | null>(null);
const selectedChoice = ref('');
const lastCorrectNote = ref('');
const score = ref(0);
const streak = ref(0);
const correct = ref(0);
const total = ref(0);
const timeLeft = ref(60);
const sessionId = ref<number | null>(null);
const saving = ref(false);
const savedRank = ref<number | null>(null);
const isBest = ref(false);
const records = ref<Record<Level, any[]>>({ natural: [], chromatic: [] });
const totals = ref<Record<Level, number>>({ natural: 0, chromatic: 0 });
const recordTab = ref<Level>('natural');
const loading = ref(false);
const error = ref('');

let timerHandle: ReturnType<typeof setInterval> | null = null;
let questionTimer: ReturnType<typeof setTimeout> | null = null;
let startTime = 0;
let gameActive = false;

const cfg = computed(() => LEVEL_CFG[level.value]);

const accuracyPct = computed(() =>
  total.value > 0 ? Math.round((correct.value / total.value) * 100) : 0
);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function shuffle<T>(arr: T[]): T[] {
  const a = [...arr];
  for (let i = a.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [a[i], a[j]] = [a[j], a[i]];
  }
  return a;
}

// ─── Game logic ───────────────────────────────────────────────────────────────

function nextQuestion() {
  if (!gameActive) return;
  const pool = cfg.value.notes;
  const answer = pool[Math.floor(Math.random() * pool.length)];
  currentNote.value = answer;
  lastResult.value = null;
  selectedChoice.value = '';
  lastCorrectNote.value = '';

  const wrong = shuffle(pool.filter((n) => n !== answer)).slice(0, 3);
  choices.value = shuffle([answer, ...wrong]);

  playNote(answer);
}

async function startGame() {
  error.value = '';
  loading.value = true;
  try {
    const res = await api.post('/api/v1/games/pitch/sessions', { level: level.value });
    sessionId.value = res.data.data.session.id;
  } catch {
    error.value = 'Failed to start session.';
    loading.value = false;
    return;
  }
  loading.value = false;

  score.value = 0;
  streak.value = 0;
  correct.value = 0;
  total.value = 0;
  timeLeft.value = cfg.value.timeLimit;
  startTime = Date.now();
  gameActive = true;
  phase.value = 'playing';

  nextQuestion();

  timerHandle = setInterval(() => {
    timeLeft.value--;
    if (timeLeft.value <= 0) endGame();
  }, 1000);
}

function guess(note: string) {
  if (!gameActive || lastResult.value !== null) return;

  total.value++;
  selectedChoice.value = note;
  lastCorrectNote.value = currentNote.value;

  if (note === currentNote.value) {
    correct.value++;
    streak.value++;
    score.value += BASE_PTS + streak.value * STREAK_BONUS;
    lastResult.value = 'correct';
  } else {
    streak.value = 0;
    lastResult.value = 'wrong';
  }

  questionTimer = setTimeout(nextQuestion, 950);
}

function replayNote() {
  if (currentNote.value) playNote(currentNote.value);
}

async function endGame() {
  gameActive = false;
  if (timerHandle) { clearInterval(timerHandle); timerHandle = null; }
  if (questionTimer) { clearTimeout(questionTimer); questionTimer = null; }
  phase.value = 'done';

  if (score.value === 0) return;

  const elapsed = Math.max(1, Math.round((Date.now() - startTime) / 1000));
  saving.value = true;
  try {
    const res = await api.post(`/api/v1/games/pitch/sessions/${sessionId.value}/complete`, {
      elapsed_seconds: elapsed,
      score: score.value,
      correct: correct.value,
      total: total.value,
    });
    savedRank.value = res.data.data.rank ?? null;
    isBest.value = res.data.data.is_best ?? false;
    phase.value = 'saved';
    loadRecords();
  } catch {
    // ignore
  }
  saving.value = false;
}

function reset() {
  gameActive = false;
  if (timerHandle) { clearInterval(timerHandle); timerHandle = null; }
  if (questionTimer) { clearTimeout(questionTimer); questionTimer = null; }
  phase.value = 'menu';
}

async function loadRecords() {
  try {
    const res = await api.get('/api/v1/games/pitch/records');
    records.value = res.data.data.records;
    totals.value = res.data.data.totals;
  } catch {
    // ignore
  }
}

function choiceClass(ch: string) {
  if (lastResult.value === null) {
    return 'border-slate-200 dark:border-zinc-600 hover:border-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/20 hover:text-rose-600 dark:hover:text-rose-400 cursor-pointer';
  }
  if (ch === lastCorrectNote.value) {
    return 'border-green-500 bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-400 cursor-default';
  }
  if (ch === selectedChoice.value && lastResult.value === 'wrong') {
    return 'border-red-400 bg-red-50 text-red-600 dark:bg-red-900/20 dark:text-red-400 cursor-default';
  }
  return 'border-slate-100 dark:border-zinc-700 text-slate-300 dark:text-zinc-600 cursor-default';
}

onUnmounted(() => {
  gameActive = false;
  if (timerHandle) clearInterval(timerHandle);
  if (questionTimer) clearTimeout(questionTimer);
});

loadRecords();
</script>

<template>
  <div class="space-y-6">
    <div>
      <p class="text-sm font-extrabold text-rose-600 dark:text-rose-400">Games</p>
      <h1 class="mt-1 text-3xl font-extrabold tracking-tight">Pitch Trainer</h1>
      <p class="mt-1 text-sm font-medium text-slate-500 dark:text-zinc-400">Hear a note, guess its name. Train your ear.</p>
    </div>

    <!-- ── Menu ─────────────────────────────────────────────────────────────── -->
    <div v-if="phase === 'menu'" class="space-y-6">
      <div class="card p-6 space-y-5">
        <h2 class="text-lg font-bold">Choose Level</h2>
        <div class="grid gap-3 sm:grid-cols-2">
          <button
            v-for="(c, lv) in LEVEL_CFG" :key="lv"
            @click="level = lv as Level"
            class="rounded-xl border-2 p-4 text-left transition"
            :class="level === lv
              ? 'border-rose-500 bg-rose-50 dark:bg-rose-950/30'
              : 'border-transparent bg-slate-50 dark:bg-zinc-800/50 hover:border-slate-300 dark:hover:border-zinc-600'">
            <div class="font-bold">{{ c.label }}</div>
            <div class="text-xs text-slate-500 dark:text-zinc-400 mt-0.5">{{ c.desc }}</div>
          </button>
        </div>
        <p class="text-xs text-slate-400 dark:text-zinc-500">Score: +10 per correct, +2 per streak level (streak 5 = +10 bonus)</p>
        <div v-if="error" class="text-sm text-red-500">{{ error }}</div>
        <button @click="startGame" :disabled="loading"
          class="rounded-xl bg-rose-600 px-6 py-2.5 text-sm font-bold text-white hover:bg-rose-700 disabled:opacity-50 transition">
          {{ loading ? 'Starting…' : 'Start' }}
        </button>
      </div>

      <!-- Records -->
      <div class="card p-6 space-y-4">
        <h2 class="text-lg font-bold">Personal Records</h2>
        <div class="flex gap-2">
          <button
            v-for="lv in (['natural', 'chromatic'] as Level[])" :key="lv"
            @click="recordTab = lv"
            class="rounded-lg px-3 py-1.5 text-xs font-bold transition"
            :class="recordTab === lv ? 'bg-rose-600 text-white' : 'bg-slate-100 dark:bg-zinc-700 text-slate-600 dark:text-zinc-300 hover:bg-slate-200 dark:hover:bg-zinc-600'">
            {{ LEVEL_CFG[lv].label }}
          </button>
        </div>
        <p class="text-xs text-slate-400">{{ totals[recordTab] }} games</p>
        <div v-if="!records[recordTab]?.length" class="text-sm text-slate-400 py-2">No records yet for this level.</div>
        <table v-else class="w-full text-sm">
          <thead>
            <tr class="text-xs text-slate-400 border-b dark:border-zinc-700">
              <th class="text-left py-1.5 font-semibold">#</th>
              <th class="text-left py-1.5 font-semibold">Score</th>
              <th class="text-left py-1.5 font-semibold">Accuracy</th>
              <th class="text-left py-1.5 font-semibold">Answers</th>
              <th class="text-left py-1.5 font-semibold">Date</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(r, i) in records[recordTab]" :key="r.id" class="border-b border-slate-50 dark:border-zinc-800/60">
              <td class="py-2 text-slate-400">{{ i + 1 }}</td>
              <td class="py-2 font-extrabold text-rose-600 dark:text-rose-400">{{ r.metadata?.score ?? 0 }}</td>
              <td class="py-2">
                <span class="text-xs font-bold px-1.5 py-0.5 rounded"
                  :class="(r.metadata?.accuracy ?? 0) >= 80
                    ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                    : (r.metadata?.accuracy ?? 0) >= 60
                      ? 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400'
                      : 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400'">
                  {{ r.metadata?.accuracy ?? 0 }}%
                </span>
              </td>
              <td class="py-2 text-slate-500 dark:text-zinc-400">{{ r.metadata?.correct ?? 0 }}/{{ r.metadata?.total ?? 0 }}</td>
              <td class="py-2 text-slate-400 text-xs">{{ r.completed_at?.slice(0, 10) ?? '-' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ── Game area ──────────────────────────────────────────────────────────── -->
    <div v-else class="space-y-4">
      <!-- Status bar -->
      <div class="card flex items-center justify-between gap-4 px-5 py-3">
        <div class="flex items-center gap-6">
          <div>
            <div class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Skor</div>
            <div class="text-2xl font-extrabold text-rose-600 dark:text-rose-400 leading-none mt-0.5">{{ score }}</div>
          </div>
          <div>
            <div class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Streak</div>
            <div class="text-2xl font-extrabold leading-none mt-0.5" :class="streak > 0 ? 'text-yellow-500' : 'text-slate-300 dark:text-zinc-600'">
              {{ streak }}
            </div>
          </div>
          <div>
            <div class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Akurasi</div>
            <div class="font-bold">{{ accuracyPct }}%</div>
          </div>
        </div>

        <div class="flex items-center gap-4">
          <!-- Circular timer -->
          <div class="relative flex items-center justify-center" style="width:52px;height:52px">
            <svg class="absolute inset-0" viewBox="0 0 52 52" style="transform:rotate(-90deg)">
              <circle cx="26" cy="26" r="22" fill="none" stroke="currentColor" stroke-width="3"
                class="text-slate-100 dark:text-zinc-700"/>
              <circle cx="26" cy="26" r="22" fill="none" stroke="currentColor" stroke-width="3"
                class="transition-all duration-1000"
                :class="timeLeft <= 10 ? 'text-red-500' : 'text-rose-500'"
                :stroke-dasharray="`${(timeLeft / cfg.timeLimit) * 138.2} 138.2`"/>
            </svg>
            <span class="text-sm font-extrabold" :class="timeLeft <= 10 ? 'text-red-500' : ''">{{ timeLeft }}</span>
          </div>
          <button @click="reset" class="text-xs font-semibold text-slate-400 hover:text-slate-600 dark:hover:text-zinc-300 transition">
            ✕
          </button>
        </div>
      </div>

      <!-- Question + choices -->
      <div class="card p-6 sm:p-8 space-y-6">
        <!-- Note icon + replay -->
        <div class="flex flex-col items-center gap-3">
          <div class="flex h-20 w-20 items-center justify-center rounded-full bg-rose-50 dark:bg-rose-950/30 text-5xl">🎵</div>
          <button @click="replayNote"
            class="rounded-full border border-slate-200 dark:border-zinc-600 px-4 py-1.5 text-xs font-semibold hover:bg-slate-50 dark:hover:bg-zinc-700 transition">
            🔉 Putar ulang
          </button>
        </div>

        <!-- Feedback label -->
        <div class="text-center h-6">
          <span v-if="lastResult === 'correct'" class="text-green-600 dark:text-green-400 font-bold">
            ✓ Correct! &nbsp;<span class="text-lg">{{ lastCorrectNote }}</span>
            <span v-if="streak > 1" class="ml-2 text-xs font-semibold text-yellow-500">🔥 Streak {{ streak }}</span>
          </span>
          <span v-else-if="lastResult === 'wrong'" class="text-red-500 font-bold">
            ✗ Wrong — that was <span class="text-lg">{{ lastCorrectNote }}</span>
          </span>
          <span v-else class="text-slate-400 text-sm">What note was just played?</span>
        </div>

        <!-- 4 choice buttons -->
        <div v-if="phase === 'playing'" class="grid grid-cols-2 gap-3 sm:grid-cols-4">
          <button
            v-for="ch in choices" :key="ch"
            @click="guess(ch)"
            :disabled="lastResult !== null"
            class="rounded-2xl border-2 py-5 text-xl font-extrabold transition select-none"
            :class="choiceClass(ch)">
            {{ ch }}
          </button>
        </div>

        <!-- Done / saved result -->
        <div v-if="phase === 'done' || phase === 'saved'" class="text-center space-y-3 pt-2">
          <div class="text-4xl font-extrabold">{{ score }} points</div>
          <div class="text-slate-500 dark:text-zinc-400">{{ correct }} correct of {{ total }} · {{ accuracyPct }}% accuracy</div>
          <div v-if="saving" class="text-sm text-slate-400">Saving…</div>
          <div v-else-if="phase === 'saved'" class="text-sm">
            <span v-if="isBest" class="font-bold text-yellow-500">🏆 New record!</span>
            <span v-else class="text-slate-400">Rank #{{ savedRank }}</span>
          </div>
          <div class="flex justify-center gap-3 pt-2">
            <button @click="startGame" :disabled="loading"
              class="rounded-xl bg-rose-600 px-5 py-2 text-sm font-bold text-white hover:bg-rose-700 disabled:opacity-50 transition">
              Play Again
            </button>
            <button @click="reset"
              class="rounded-xl bg-slate-100 dark:bg-zinc-700 px-5 py-2 text-sm font-bold hover:bg-slate-200 dark:hover:bg-zinc-600 transition">
              Menu
            </button>
          </div>
        </div>
      </div>

      <!-- Live score bonus hint -->
      <p v-if="phase === 'playing' && streak > 0" class="text-center text-xs text-slate-400">
        Streak {{ streak }} → +{{ BASE_PTS + streak * STREAK_BONUS }} pts per correct answer
      </p>
    </div>
  </div>
</template>
