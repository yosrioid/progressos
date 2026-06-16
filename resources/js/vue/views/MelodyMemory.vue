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

function playNote(note: string, durationSec = 0.5) {
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

function sleep(ms: number) {
  return new Promise<void>((r) => setTimeout(r, ms));
}

// ─── Level configs ────────────────────────────────────────────────────────────

type Level = 'easy' | 'medium' | 'hard';

const LEVEL_CFG = {
  easy: {
    label: 'Mudah',
    notes: ['C', 'D', 'E', 'G', 'A'] as string[],
    startLen: 3,
    noteDur: 600,
    noteGap: 420,
    desc: '5 pentatonic notes — C D E G A',
  },
  medium: {
    label: 'Medium',
    notes: ['C', 'D', 'E', 'F', 'G', 'A', 'B'] as string[],
    startLen: 3,
    noteDur: 500,
    noteGap: 350,
    desc: '7 diatonic notes — C to B',
  },
  hard: {
    label: 'Hard',
    notes: ['C', 'D', 'E', 'F', 'G', 'A', 'B'] as string[],
    startLen: 4,
    noteDur: 340,
    noteGap: 230,
    desc: '7 notes, faster & longer sequences',
  },
};

// ─── State ────────────────────────────────────────────────────────────────────

type Phase = 'menu' | 'countdown' | 'playing_seq' | 'player_turn' | 'game_over' | 'saved';

const phase = ref<Phase>('menu');
const level = ref<Level>('easy');
const sequence = ref<string[]>([]);
const playerInput = ref<string[]>([]);
const maxStreak = ref(0);
const highlightNote = ref<string | null>(null);
const errorNote = ref<string | null>(null);
const correctNote = ref<string | null>(null);
const sessionId = ref<number | null>(null);
const elapsedSeconds = ref(0);
const saving = ref(false);
const savedRank = ref<number | null>(null);
const isBest = ref(false);
const records = ref<Record<Level, any[]>>({ easy: [], medium: [], hard: [] });
const totals = ref<Record<Level, number>>({ easy: 0, medium: 0, hard: 0 });
const recordTab = ref<Level>('easy');
const loading = ref(false);
const error = ref('');
const countdownVal = ref(3);

let startTime = 0;
let stopLoop = false;

const cfg = computed(() => LEVEL_CFG[level.value]);

// ─── Piano key layout ─────────────────────────────────────────────────────────

// White key width = 44px, 7 keys → 308px total
const WHITE_KEYS = ['C', 'D', 'E', 'F', 'G', 'A', 'B'];

// Black keys: left offset = right edge of preceding white key minus half black key width (28/2=14)
const BLACK_KEYS = [
  { note: 'C#', left: 30 },  // after C (44 - 14)
  { note: 'D#', left: 74 },  // after D (88 - 14)
  { note: 'F#', left: 162 }, // after F (176 - 14)
  { note: 'G#', left: 206 }, // after G (220 - 14)
  { note: 'A#', left: 250 }, // after A (264 - 14)
];

function isEnabled(note: string) {
  return cfg.value.notes.includes(note);
}

function whiteKeyBg(note: string) {
  if (errorNote.value === note) return 'bg-red-300';
  if (correctNote.value === note) return 'bg-green-300';
  if (highlightNote.value === note) return 'bg-blue-200';
  if (!isEnabled(note)) return 'bg-slate-100 dark:bg-zinc-300 opacity-40 cursor-not-allowed';
  return 'bg-white dark:bg-zinc-100 hover:bg-purple-50 cursor-pointer';
}

function blackKeyBg(note: string) {
  if (errorNote.value === note) return 'bg-red-500';
  if (correctNote.value === note) return 'bg-green-500';
  if (highlightNote.value === note) return 'bg-blue-500';
  return 'bg-zinc-800 dark:bg-zinc-950 opacity-50 cursor-not-allowed';
}

// ─── Game logic ───────────────────────────────────────────────────────────────

async function startGame() {
  error.value = '';
  loading.value = true;
  try {
    const res = await api.post('/api/v1/games/melody/sessions', { level: level.value });
    sessionId.value = res.data.data.session.id;
  } catch {
    error.value = 'Failed to start session.';
    loading.value = false;
    return;
  }
  loading.value = false;

  sequence.value = [];
  playerInput.value = [];
  maxStreak.value = 0;
  elapsedSeconds.value = 0;
  startTime = Date.now();
  stopLoop = false;
  phase.value = 'countdown';

  for (let i = 3; i >= 1; i--) {
    countdownVal.value = i;
    await sleep(700);
  }
  addAndPlay();
}

async function addAndPlay() {
  if (stopLoop) return;

  const pool = cfg.value.notes;
  sequence.value = [...sequence.value, pool[Math.floor(Math.random() * pool.length)]];
  playerInput.value = [];

  phase.value = 'playing_seq';

  for (const note of sequence.value) {
    if (stopLoop) return;
    highlightNote.value = note;
    playNote(note, cfg.value.noteDur / 1000);
    await sleep(cfg.value.noteDur);
    highlightNote.value = null;
    await sleep(cfg.value.noteGap);
  }

  if (!stopLoop) phase.value = 'player_turn';
}

async function pressKey(note: string) {
  if (phase.value !== 'player_turn') return;
  if (!isEnabled(note)) return;

  const expected = sequence.value[playerInput.value.length];
  playNote(note, 0.22);

  if (note !== expected) {
    errorNote.value = note;
    phase.value = 'playing_seq'; // block input
    await sleep(600);
    errorNote.value = null;
    doGameOver();
    return;
  }

  correctNote.value = note;
  playerInput.value = [...playerInput.value, note];
  await sleep(180);
  correctNote.value = null;

  if (playerInput.value.length === sequence.value.length) {
    maxStreak.value = Math.max(maxStreak.value, sequence.value.length);
    phase.value = 'playing_seq';
    await sleep(480);
    addAndPlay();
  }
}

async function doGameOver() {
  stopLoop = true;
  elapsedSeconds.value = Math.round((Date.now() - startTime) / 1000);
  phase.value = 'game_over';

  if (maxStreak.value < 1) return;
  saving.value = true;
  try {
    const res = await api.post(`/api/v1/games/melody/sessions/${sessionId.value}/complete`, {
      elapsed_seconds: Math.max(1, elapsedSeconds.value),
      streak: maxStreak.value,
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

async function loadRecords() {
  try {
    const res = await api.get('/api/v1/games/melody/records');
    records.value = res.data.data.records;
    totals.value = res.data.data.totals;
  } catch {
    // ignore
  }
}

function formatTime(s: number) {
  const m = Math.floor(s / 60);
  return m > 0 ? `${m}m ${s % 60}s` : `${s}s`;
}

function reset() {
  stopLoop = true;
  phase.value = 'menu';
  sequence.value = [];
  playerInput.value = [];
  highlightNote.value = null;
  errorNote.value = null;
  correctNote.value = null;
}

onUnmounted(() => { stopLoop = true; });

loadRecords();
</script>

<template>
  <div class="space-y-6">
    <div>
      <p class="text-sm font-extrabold text-purple-700 dark:text-purple-400">Games</p>
      <h1 class="mt-1 text-3xl font-extrabold tracking-tight">Melody Memory</h1>
      <p class="mt-1 text-sm font-medium text-slate-500 dark:text-zinc-400">Hear a note sequence, repeat it on the piano.</p>
    </div>

    <!-- ── Menu ─────────────────────────────────────────────────────────────── -->
    <div v-if="phase === 'menu'" class="space-y-6">
      <div class="card p-6 space-y-5">
        <h2 class="text-lg font-bold">Choose Level</h2>
        <div class="grid gap-3 sm:grid-cols-3">
          <button
            v-for="(c, lv) in LEVEL_CFG" :key="lv"
            @click="level = lv as Level"
            class="rounded-xl border-2 p-4 text-left transition"
            :class="level === lv
              ? 'border-purple-500 bg-purple-50 dark:bg-purple-950/30'
              : 'border-transparent bg-slate-50 dark:bg-zinc-800/50 hover:border-slate-300 dark:hover:border-zinc-600'">
            <div class="font-bold">{{ c.label }}</div>
            <div class="text-xs text-slate-500 dark:text-zinc-400 mt-0.5">{{ c.desc }}</div>
          </button>
        </div>
        <p class="text-xs text-slate-400 dark:text-zinc-500">Sequence grows by 1 note each round. One mistake = game over.</p>
        <div v-if="error" class="text-sm text-red-500">{{ error }}</div>
        <button @click="startGame" :disabled="loading"
          class="rounded-xl bg-purple-600 px-6 py-2.5 text-sm font-bold text-white hover:bg-purple-700 disabled:opacity-50 transition">
          {{ loading ? 'Starting…' : 'Start' }}
        </button>
      </div>

      <!-- Records -->
      <div class="card p-6 space-y-4">
        <h2 class="text-lg font-bold">Personal Records</h2>
        <div class="flex gap-2">
          <button
            v-for="lv in (['easy', 'medium', 'hard'] as Level[])" :key="lv"
            @click="recordTab = lv"
            class="rounded-lg px-3 py-1.5 text-xs font-bold transition"
            :class="recordTab === lv ? 'bg-purple-600 text-white' : 'bg-slate-100 dark:bg-zinc-700 text-slate-600 dark:text-zinc-300 hover:bg-slate-200 dark:hover:bg-zinc-600'">
            {{ LEVEL_CFG[lv].label }}
          </button>
        </div>
        <p class="text-xs text-slate-400">{{ totals[recordTab] }} games</p>
        <div v-if="!records[recordTab]?.length" class="text-sm text-slate-400 py-2">No records yet for this level.</div>
        <table v-else class="w-full text-sm">
          <thead>
            <tr class="text-xs text-slate-400 border-b dark:border-zinc-700">
              <th class="text-left py-1.5 font-semibold">#</th>
              <th class="text-left py-1.5 font-semibold">Streak</th>
              <th class="text-left py-1.5 font-semibold">Time</th>
              <th class="text-left py-1.5 font-semibold">Date</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(r, i) in records[recordTab]" :key="r.id" class="border-b border-slate-50 dark:border-zinc-800/60">
              <td class="py-2 text-slate-400">{{ i + 1 }}</td>
              <td class="py-2 font-extrabold text-purple-600 dark:text-purple-400">{{ r.metadata?.streak ?? '-' }} notes</td>
              <td class="py-2 text-slate-500 dark:text-zinc-400">{{ formatTime(r.duration_seconds) }}</td>
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
        <div class="flex items-center gap-5">
          <div>
            <div class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Level</div>
            <div class="font-bold">{{ cfg.label }}</div>
          </div>
          <div>
            <div class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Round</div>
            <div class="font-bold text-purple-600 dark:text-purple-400">{{ sequence.length }} notes</div>
          </div>
          <div>
            <div class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Best</div>
            <div class="font-bold">{{ maxStreak }}</div>
          </div>
        </div>
        <button @click="reset" class="text-xs font-semibold text-slate-400 hover:text-slate-600 dark:hover:text-zinc-300 transition">
          ✕ Keluar
        </button>
      </div>

      <!-- Countdown -->
      <div v-if="phase === 'countdown'" class="card flex h-52 items-center justify-center">
        <div class="text-8xl font-extrabold text-purple-500" style="line-height:1">{{ countdownVal }}</div>
      </div>

      <!-- Piano area -->
      <div v-else class="card p-6 space-y-5">
        <!-- Phase label -->
        <div class="text-center text-sm font-semibold h-5">
          <span v-if="phase === 'playing_seq'" class="text-blue-500 dark:text-blue-400">🎵 Dengarkan…</span>
          <span v-else-if="phase === 'player_turn'" class="text-green-600 dark:text-green-400">
            Your turn! ({{ playerInput.length }} / {{ sequence.length }})
          </span>
          <span v-else-if="phase === 'game_over' || phase === 'saved'" class="text-red-500">
            ❌ Wrong! Note #{{ playerInput.length + 1 }}
          </span>
        </div>

        <!-- Sequence progress dots -->
        <div v-if="phase === 'player_turn'" class="flex justify-center gap-1.5 flex-wrap">
          <span
            v-for="(_, i) in sequence" :key="i"
            class="inline-block h-2.5 w-2.5 rounded-full transition-colors duration-200"
            :class="i < playerInput.length
              ? 'bg-green-500'
              : i === playerInput.length
                ? 'bg-purple-400 animate-pulse'
                : 'bg-slate-200 dark:bg-zinc-600'" />
        </div>

        <!-- Piano keyboard -->
        <div class="flex justify-center overflow-x-auto py-2">
          <div class="relative" style="width:308px;height:120px">
            <!-- White keys -->
            <div
              v-for="(note, idx) in WHITE_KEYS" :key="note"
              @click="pressKey(note)"
              class="absolute flex items-end justify-center pb-1.5 border border-slate-300 dark:border-zinc-400 rounded-b-lg select-none transition-colors duration-75"
              :class="whiteKeyBg(note)"
              :style="{ left: `${idx * 44}px`, top: 0, width: '44px', height: '120px' }">
              <span class="text-[10px] font-bold text-slate-400 dark:text-zinc-500 pointer-events-none">{{ note }}</span>
            </div>

            <!-- Black keys (always visual, non-interactive in this game) -->
            <div
              v-for="bk in BLACK_KEYS" :key="bk.note"
              class="absolute rounded-b select-none"
              :class="blackKeyBg(bk.note)"
              :style="{ left: `${bk.left}px`, top: 0, width: '28px', height: '76px', zIndex: 2 }" />
          </div>
        </div>

        <!-- Sequence hint: show names of notes player already got right -->
        <div v-if="phase === 'player_turn' && sequence.length > 0" class="flex justify-center gap-1 flex-wrap">
          <span
            v-for="(n, i) in sequence" :key="i"
            class="inline-block px-1.5 py-0.5 rounded text-[11px] font-bold"
            :class="i < playerInput.length
              ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
              : 'bg-slate-100 text-slate-400 dark:bg-zinc-700 dark:text-zinc-500'">
            {{ i < playerInput.length ? n : '?' }}
          </span>
        </div>
      </div>

      <!-- Result card -->
      <div v-if="phase === 'game_over' || phase === 'saved'" class="card p-6 text-center space-y-3">
        <div class="text-5xl">🎵</div>
        <div class="text-2xl font-extrabold">Streak: {{ maxStreak }} notes</div>
        <div class="text-sm text-slate-500 dark:text-zinc-400">Time: {{ formatTime(elapsedSeconds) }}</div>
        <div v-if="saving" class="text-sm text-slate-400">Saving…</div>
        <div v-else-if="phase === 'saved'" class="text-sm">
          <span v-if="isBest" class="font-bold text-yellow-500">🏆 New record!</span>
          <span v-else class="text-slate-400">Rank #{{ savedRank }}</span>
        </div>
        <div class="flex justify-center gap-3 pt-2">
          <button @click="startGame" :disabled="loading"
            class="rounded-xl bg-purple-600 px-5 py-2 text-sm font-bold text-white hover:bg-purple-700 disabled:opacity-50 transition">
            Play Again
          </button>
          <button @click="reset"
            class="rounded-xl bg-slate-100 dark:bg-zinc-700 px-5 py-2 text-sm font-bold hover:bg-slate-200 dark:hover:bg-zinc-600 transition">
            Menu
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
