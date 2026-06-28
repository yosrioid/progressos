<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { api, unwrap } from '../api';
import { toast } from '../feedback';

interface JournalItem {
  id: number;
  date: string;
  body: string;
  mood: string | null;
  tema: string | null;
  analyzed_at: string | null;
}

const journals = ref<JournalItem[]>([]);
const loading = ref(true);

async function load() {
  loading.value = true;
  try {
    const res = await api.get('/api/v1/journals').then(unwrap);
    journals.value = res.journals ?? [];
  } catch {
    toast({ tone: 'error', title: 'Gagal memuat', message: 'Tidak dapat memuat daftar jurnal.' });
  } finally {
    loading.value = false;
  }
}

function formatDate(d: string) {
  return new Date(d).toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
}

function preview(body: string) {
  return body.length > 120 ? body.slice(0, 120) + '…' : body;
}

onMounted(load);
</script>

<template>
  <div class="space-y-5">
    <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
      <div>
        <p class="text-sm font-extrabold text-teal-700">Journal</p>
        <h1 class="mt-1 text-3xl font-extrabold tracking-tight">Jurnal Harian</h1>
        <p class="mt-1 text-sm font-medium text-slate-500 dark:text-zinc-500">Tulis bebas, AI analisa mood, tema, insight, dan saran.</p>
      </div>
      <RouterLink to="/journal/new" class="btn btn-primary shrink-0">+ Tulis Hari Ini</RouterLink>
    </div>

    <!-- Loading skeleton -->
    <div v-if="loading" class="space-y-3">
      <div v-for="i in 4" :key="i" class="card animate-pulse h-24" />
    </div>

    <!-- Empty -->
    <div v-else-if="journals.length === 0" class="card flex flex-col items-center gap-3 py-14 text-center">
      <svg class="h-10 w-10 text-slate-300 dark:text-zinc-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.966 8.966 0 0 0-6 2.292m0-14.25v14.25" />
      </svg>
      <div>
        <p class="font-extrabold text-slate-700 dark:text-zinc-300">Belum ada jurnal</p>
        <p class="mt-1 text-sm text-slate-400 dark:text-zinc-500">Mulai tulis hari ini.</p>
      </div>
      <RouterLink to="/journal/new" class="btn btn-primary mt-1">Tulis Sekarang</RouterLink>
    </div>

    <!-- List -->
    <div v-else class="space-y-3">
      <RouterLink
        v-for="j in journals"
        :key="j.id"
        :to="`/journal/${j.id}`"
        class="card block transition-shadow hover:shadow-md"
      >
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0 flex-1">
            <p class="text-xs font-extrabold uppercase tracking-wide text-teal-600 dark:text-teal-500">
              {{ formatDate(j.date) }}
            </p>
            <p class="mt-1 text-sm font-medium text-slate-700 dark:text-zinc-300 leading-relaxed">
              {{ preview(j.body) }}
            </p>
          </div>
          <div class="shrink-0 text-right">
            <span
              v-if="j.mood"
              class="inline-block rounded-full bg-teal-50 px-2.5 py-0.5 text-xs font-semibold text-teal-700 dark:bg-teal-900/30 dark:text-teal-400"
            >{{ j.mood }}</span>
            <p v-if="!j.analyzed_at" class="mt-1 text-[10px] text-slate-300 dark:text-zinc-600">Belum dianalisa</p>
          </div>
        </div>
        <div v-if="j.tema" class="mt-2 flex flex-wrap gap-1.5">
          <span
            v-for="t in j.tema.split(',')"
            :key="t"
            class="pill"
          >{{ t.trim() }}</span>
        </div>
      </RouterLink>
    </div>
  </div>
</template>
