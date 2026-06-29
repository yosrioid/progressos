<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { api, unwrap } from '../api';
import { toast } from '../feedback';
import PinGate from '../components/PinGate.vue';

interface JournalItem {
  id: number;
  date: string;
  body: string;
  mood: string | null;
  tema: string | null;
  analyzed_at: string | null;
}

interface AiProfile {
  text?: string;
  updated_at?: string;
  entry_count?: number;
}

const journals = ref<JournalItem[]>([]);
const loading = ref(true);
const profile = ref<AiProfile>({});
const totalEntries = ref(0);
const profileExpanded = ref(false);

async function load() {
  loading.value = true;
  try {
    const [journalRes, profileRes] = await Promise.all([
      api.get('/api/v1/journals').then(unwrap),
      api.get('/api/v1/journals/profile').then(unwrap),
    ]);
    journals.value = journalRes.journals ?? [];
    profile.value = profileRes.profile ?? {};
    totalEntries.value = profileRes.total_entries ?? 0;
  } catch {
    toast({ tone: 'error', title: 'Gagal memuat', message: 'Tidak dapat memuat daftar jurnal.' });
  } finally {
    loading.value = false;
  }
}

function formatDate(d: string) {
  return new Date(d).toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
}

function formatShortDate(d: string) {
  return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
}

function preview(body: string) {
  return body.length > 140 ? body.slice(0, 140) + '…' : body;
}

onMounted(load);
</script>

<template>
  <PinGate>
  <div class="space-y-5">
    <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
      <div>
        <p class="text-sm font-extrabold text-teal-700">Journal</p>
        <h1 class="mt-1 text-3xl font-extrabold tracking-tight">Jurnal Harian</h1>
        <p class="mt-1 text-sm font-medium text-slate-500 dark:text-zinc-500">Tulis bebas, AI analisa mood, tema, insight, dan saran.</p>
      </div>
      <RouterLink to="/journal/new" class="btn btn-primary shrink-0">+ Tulis Hari Ini</RouterLink>
    </div>

    <!-- AI Memory card -->
    <div v-if="profile.text || totalEntries > 0" class="card p-4">
      <button class="flex w-full items-start gap-3 text-left" @click="profileExpanded = !profileExpanded">
        <div class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-teal-50 text-teal-700 dark:bg-teal-900/30 dark:text-teal-400">
          <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" viewBox="0 0 24 24"><path d="M9.663 17h4.673M12 3v1m6.364 1.636-.707.707M21 12h-1M4 12H3m3.343-5.657-.707-.707m2.828 9.9a5 5 0 1 1 7.072 0l-.548.547A3.374 3.374 0 0 0 14 18.469V19a2 2 0 1 1-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
        </div>
        <div class="min-w-0 flex-1">
          <p class="text-sm font-extrabold text-slate-800 dark:text-zinc-200">Apa yang AI ketahui tentang kamu</p>
          <p class="mt-0.5 text-xs text-slate-400 dark:text-zinc-500">
            <span v-if="profile.entry_count">Dibangun dari {{ profile.entry_count }} analisa</span>
            <span v-else-if="totalEntries > 0">{{ totalEntries }} jurnal tersimpan — analisa pertama untuk mulai membangun memori AI</span>
            <span v-else>Belum ada memori — analisa jurnal pertamamu untuk mulai</span>
            <span v-if="profile.updated_at"> · Diperbarui {{ formatShortDate(profile.updated_at) }}</span>
          </p>
        </div>
        <svg
          :class="['h-4 w-4 shrink-0 text-slate-400 transition-transform mt-0.5', profileExpanded ? 'rotate-180' : '']"
          fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"
        ><path d="m6 9 6 6 6-6"/></svg>
      </button>

      <div v-if="profileExpanded && profile.text" class="mt-4 border-t border-slate-100 pt-4 dark:border-zinc-800">
        <p class="whitespace-pre-wrap text-sm leading-relaxed text-slate-600 dark:text-zinc-400">{{ profile.text }}</p>
      </div>
      <div v-else-if="profileExpanded && !profile.text" class="mt-4 border-t border-slate-100 pt-4 dark:border-zinc-800">
        <p class="text-sm text-slate-400 dark:text-zinc-500">Belum ada profil. Klik "Analisa AI" di jurnal mana saja untuk mulai membangun memori.</p>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="space-y-3">
      <div v-for="i in 4" :key="i" class="card p-4 animate-pulse h-20" />
    </div>

    <!-- Empty -->
    <div v-else-if="journals.length === 0" class="card p-10 text-center">
      <svg class="mx-auto h-10 w-10 text-slate-300 dark:text-zinc-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.966 8.966 0 0 0-6 2.292m0-14.25v14.25" />
      </svg>
      <p class="mt-3 font-extrabold text-slate-700 dark:text-zinc-300">Belum ada jurnal</p>
      <p class="mt-1 text-sm text-slate-400 dark:text-zinc-500">Mulai tulis hari ini.</p>
      <RouterLink to="/journal/new" class="btn btn-primary mt-4 inline-block">Tulis Sekarang</RouterLink>
    </div>

    <!-- List -->
    <div v-else class="space-y-3">
      <RouterLink
        v-for="j in journals"
        :key="j.id"
        :to="`/journal/${j.id}`"
        class="card p-4 block hover:shadow-md transition-shadow"
      >
        <div class="flex items-start justify-between gap-4">
          <div class="min-w-0 flex-1">
            <p class="text-xs font-extrabold uppercase tracking-wide text-teal-600 dark:text-teal-500">
              {{ formatDate(j.date) }}
            </p>
            <p class="mt-1.5 text-sm font-medium leading-relaxed text-slate-600 dark:text-zinc-400">
              {{ preview(j.body) }}
            </p>
            <div v-if="j.tema" class="mt-2 flex flex-wrap gap-1.5">
              <span v-for="t in j.tema.split(',')" :key="t" class="pill">{{ t.trim() }}</span>
            </div>
          </div>
          <div class="shrink-0 text-right space-y-1">
            <span
              v-if="j.mood"
              class="inline-block rounded-full bg-teal-50 px-2.5 py-0.5 text-xs font-semibold text-teal-700 dark:bg-teal-900/30 dark:text-teal-400"
            >{{ j.mood }}</span>
            <p v-if="!j.analyzed_at" class="text-[11px] text-slate-300 dark:text-zinc-600">Belum dianalisa</p>
          </div>
        </div>
      </RouterLink>
    </div>
  </div>
  </PinGate>
</template>
