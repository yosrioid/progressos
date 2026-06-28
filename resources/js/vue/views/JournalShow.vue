<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { api, unwrap } from '../api';
import { confirmAction, toast } from '../feedback';
import PinGate from '../components/PinGate.vue';

const props = defineProps<{ id: string | 'new' }>();
const router = useRouter();

interface Journal {
  id: number;
  date: string;
  body: string;
  mood: string | null;
  tema: string | null;
  ai_content: string | null;
  ai_insight: string | null;
  ai_saran: string | null;
  analyzed_at: string | null;
}

const journal = ref<Journal | null>(null);
const loading = ref(true);
const analyzing = ref(false);
const saving = ref(false);
const deleting = ref(false);
const editing = ref(false);
const editingAnalysis = ref(false);

const form = ref({ body: '', date: new Date().toISOString().slice(0, 10) });
const editMood = ref('');
const editTema = ref('');
const editContent = ref('');

const isNew = props.id === 'new';

async function load() {
  if (isNew) { loading.value = false; return; }
  loading.value = true;
  try {
    const res = await api.get(`/api/v1/journals/${props.id}`).then(unwrap);
    journal.value = res.journal;
    form.value.body = res.journal.body;
    form.value.date = res.journal.date;
  } catch {
    toast({ tone: 'error', title: 'Gagal memuat', message: 'Jurnal tidak ditemukan.' });
    router.push('/journal');
  } finally {
    loading.value = false;
  }
}

async function save() {
  if (!form.value.body.trim()) return;
  saving.value = true;
  try {
    if (isNew) {
      const res = await api.post('/api/v1/journals', form.value).then(unwrap);
      journal.value = res.journal;
      router.replace(`/journal/${res.journal.id}`);
      toast({ tone: 'success', title: 'Jurnal disimpan', message: 'Klik Analisa AI untuk mendapat insight.' });
    } else {
      const res = await api.patch(`/api/v1/journals/${props.id}`, { body: form.value.body }).then(unwrap);
      journal.value = res.journal;
      editing.value = false;
      toast({ tone: 'success', title: 'Diperbarui' });
    }
  } catch (e: any) {
    toast({ tone: 'error', title: 'Gagal menyimpan', message: e?.response?.data?.message ?? 'Terjadi kesalahan.' });
  } finally {
    saving.value = false;
  }
}

async function analyze() {
  if (!journal.value) return;
  analyzing.value = true;
  try {
    const res = await api.post(`/api/v1/journals/${journal.value.id}/analyze`).then(unwrap);
    journal.value = res.journal;
    toast({ tone: 'success', title: 'Analisa selesai' });
  } catch (e: any) {
    toast({ tone: 'error', title: 'Analisa gagal', message: e?.response?.data?.message ?? 'Coba lagi nanti.' });
  } finally {
    analyzing.value = false;
  }
}

function startEditAnalysis() {
  if (!journal.value) return;
  editMood.value = journal.value.mood ?? '';
  editTema.value = journal.value.tema ?? '';
  editContent.value = journal.value.ai_content ?? '';
  editingAnalysis.value = true;
}

async function saveAnalysis() {
  if (!journal.value) return;
  saving.value = true;
  try {
    const res = await api.patch(`/api/v1/journals/${journal.value.id}`, {
      mood: editMood.value,
      tema: editTema.value,
      ai_content: editContent.value,
    }).then(unwrap);
    journal.value = res.journal;
    editingAnalysis.value = false;
    toast({ tone: 'success', title: 'Disimpan' });
  } catch (e: any) {
    toast({ tone: 'error', title: 'Gagal menyimpan', message: e?.response?.data?.message ?? 'Terjadi kesalahan.' });
  } finally {
    saving.value = false;
  }
}

async function destroy() {
  if (!journal.value) return;
  const ok = await confirmAction({ title: 'Hapus jurnal?', message: 'Jurnal ini akan dihapus permanen.', confirmLabel: 'Hapus' });
  if (!ok) return;
  deleting.value = true;
  try {
    await api.delete(`/api/v1/journals/${journal.value.id}`);
    toast({ tone: 'success', title: 'Jurnal dihapus' });
    router.push('/journal');
  } catch {
    toast({ tone: 'error', title: 'Gagal menghapus' });
    deleting.value = false;
  }
}

function formatDate(d: string) {
  return new Date(d).toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
}

onMounted(load);
</script>

<template>
  <PinGate>
  <div class="mx-auto max-w-3xl space-y-5">
    <!-- Header -->
    <div class="flex items-center justify-between gap-3">
      <div class="flex items-center gap-2 text-sm">
        <RouterLink to="/journal" class="font-semibold text-slate-400 hover:text-teal-600 dark:text-zinc-500 dark:hover:text-teal-400">← Journal</RouterLink>
        <span class="text-slate-300 dark:text-zinc-700">/</span>
        <span class="font-extrabold text-slate-700 dark:text-zinc-200">
          {{ isNew ? 'Tulis Jurnal' : (journal ? formatDate(journal.date) : '…') }}
        </span>
      </div>
      <button
        v-if="journal && !deleting"
        class="text-sm font-semibold text-slate-300 hover:text-red-400 dark:text-zinc-600 dark:hover:text-red-400 transition-colors"
        @click="destroy"
      >Hapus</button>
    </div>

    <!-- Skeleton -->
    <div v-if="loading" class="space-y-4">
      <div class="card p-5 animate-pulse h-48" />
      <div class="card p-5 animate-pulse h-32" />
    </div>

    <!-- New form -->
    <div v-else-if="isNew" class="card p-5 space-y-4">
      <div class="flex items-center gap-3">
        <div>
          <p class="text-xs font-semibold text-slate-500 dark:text-zinc-400 mb-1">Tanggal</p>
          <input v-model="form.date" type="date" class="field" />
        </div>
      </div>
      <div>
        <p class="text-xs font-semibold text-slate-500 dark:text-zinc-400 mb-1">Ceritakan harimu</p>
        <textarea
          v-model="form.body"
          rows="12"
          class="field resize-none"
          placeholder="Apa yang kamu rasakan, kerjakan, pikirkan hari ini..."
          autofocus
        />
      </div>
      <div class="flex justify-end gap-2 pt-1">
        <RouterLink to="/journal" class="btn">Batal</RouterLink>
        <button class="btn btn-primary" :disabled="saving || !form.body.trim()" @click="save">
          {{ saving ? 'Menyimpan...' : 'Simpan Jurnal' }}
        </button>
      </div>
    </div>

    <!-- Existing journal -->
    <template v-else-if="journal">
      <!-- Body -->
      <div class="card p-5 space-y-3">
        <div class="flex items-center justify-between">
          <p class="label">Isi Jurnal</p>
          <button
            v-if="!editing"
            class="text-xs font-semibold text-teal-600 hover:text-teal-700 dark:text-teal-500"
            @click="editing = true"
          >Edit</button>
        </div>
        <template v-if="editing">
          <textarea v-model="form.body" rows="12" class="field resize-none" />
          <div class="flex justify-end gap-2 pt-1">
            <button class="btn" @click="editing = false">Batal</button>
            <button class="btn btn-primary" :disabled="saving" @click="save">
              {{ saving ? 'Menyimpan...' : 'Simpan' }}
            </button>
          </div>
        </template>
        <p v-else class="whitespace-pre-wrap text-sm font-medium leading-relaxed text-slate-700 dark:text-zinc-300">{{ journal.body }}</p>
      </div>

      <!-- AI Analysis -->
      <div class="card p-5 space-y-4">
        <div class="flex items-center justify-between">
          <p class="label">Analisa AI</p>
          <div class="flex items-center gap-2">
            <button
              v-if="journal.analyzed_at && !editingAnalysis"
              class="text-xs font-semibold text-slate-400 hover:text-teal-600 dark:text-zinc-500 dark:hover:text-teal-400"
              @click="startEditAnalysis"
            >Edit hasil</button>
            <button class="btn btn-primary text-xs" :disabled="analyzing" @click="analyze">
              <span v-if="analyzing" class="flex items-center gap-1.5">
                <svg class="h-3 w-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                Menganalisa...
              </span>
              <span v-else>{{ journal.analyzed_at ? '↺ Analisa Ulang' : '✦ Analisa AI' }}</span>
            </button>
          </div>
        </div>

        <p v-if="!journal.analyzed_at" class="text-sm text-slate-400 dark:text-zinc-500">
          Tekan <span class="font-semibold">✦ Analisa AI</span> untuk mendapat insight dari tulisanmu. Butuh Groq API key yang sudah dikonfigurasi.
        </p>

        <!-- Edit mode -->
        <template v-else-if="editingAnalysis">
          <div>
            <p class="text-xs font-semibold text-slate-500 dark:text-zinc-400 mb-1">Mood</p>
            <input v-model="editMood" type="text" class="field" placeholder="Suasana hati..." />
          </div>
          <div>
            <p class="text-xs font-semibold text-slate-500 dark:text-zinc-400 mb-1">Tema <span class="font-normal">(pisah dengan koma)</span></p>
            <input v-model="editTema" type="text" class="field" placeholder="Pekerjaan, refleksi, ..." />
          </div>
          <div>
            <p class="text-xs font-semibold text-slate-500 dark:text-zinc-400 mb-1">Ringkasan</p>
            <textarea v-model="editContent" rows="4" class="field resize-none" />
          </div>
          <div class="flex justify-end gap-2 pt-1">
            <button class="btn" @click="editingAnalysis = false">Batal</button>
            <button class="btn btn-primary" :disabled="saving" @click="saveAnalysis">
              {{ saving ? 'Menyimpan...' : 'Simpan' }}
            </button>
          </div>
        </template>

        <!-- Display mode -->
        <template v-else>
          <div class="flex flex-wrap gap-2">
            <span
              v-if="journal.mood"
              class="inline-flex items-center gap-1.5 rounded-full bg-teal-50 px-3 py-1 text-sm font-semibold text-teal-700 dark:bg-teal-900/30 dark:text-teal-400"
            >{{ journal.mood }}</span>
            <span
              v-for="t in (journal.tema ?? '').split(',').filter(Boolean)"
              :key="t"
              class="pill"
            >{{ t.trim() }}</span>
          </div>

          <div v-if="journal.ai_content" class="rounded-xl bg-slate-50 px-4 py-3 dark:bg-zinc-800/50">
            <p class="text-xs font-extrabold uppercase tracking-wide text-slate-400 dark:text-zinc-500 mb-1.5">Ringkasan Hari</p>
            <p class="text-sm font-medium leading-relaxed text-slate-700 dark:text-zinc-300">{{ journal.ai_content }}</p>
          </div>

          <div v-if="journal.ai_insight" class="rounded-xl bg-amber-50 px-4 py-3 dark:bg-amber-900/10">
            <p class="text-xs font-extrabold uppercase tracking-wide text-amber-600 dark:text-amber-500 mb-1.5">💡 Insight</p>
            <p class="text-sm font-medium leading-relaxed text-slate-700 dark:text-zinc-300">{{ journal.ai_insight }}</p>
          </div>

          <div v-if="journal.ai_saran" class="rounded-xl bg-teal-50 px-4 py-3 dark:bg-teal-900/10">
            <p class="text-xs font-extrabold uppercase tracking-wide text-teal-600 dark:text-teal-500 mb-1.5">🎯 Saran</p>
            <p class="text-sm font-medium leading-relaxed text-slate-700 dark:text-zinc-300">{{ journal.ai_saran }}</p>
          </div>
        </template>
      </div>
    </template>
  </div>
  </PinGate>
</template>
