<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { api, unwrap } from '../api';
import { confirmAction, toast } from '../feedback';

const router = useRouter();
const docs = ref<any[]>([]);
const categories = ref<string[]>([]);
const loading = ref(true);
const search = ref('');
const filterCat = ref('');
const page = ref(1);
const meta = ref<any>(null);

async function load() {
  loading.value = true;
  try {
    const params: any = { page: page.value, per_page: 20 };
    if (search.value) params.search = search.value;
    if (filterCat.value) params.category = filterCat.value;
    const data = await api.get('/api/v1/docs', { params }).then(unwrap);
    docs.value = data.docs?.data || [];
    meta.value = data.docs;
  } catch (e: any) {
    toast({ tone: 'error', title: 'Gagal memuat docs', message: e?.response?.data?.message ?? 'Terjadi kesalahan.' });
  } finally {
    loading.value = false;
  }
}

async function loadCategories() {
  try {
    const data = await api.get('/api/v1/docs/categories').then(unwrap);
    categories.value = data.categories;
  } catch {
    // non-critical
  }
}

async function remove(doc: any) {
  const ok = await confirmAction({ title: 'Delete doc', message: `Delete "${doc.title}"? This also removes all attached files.`, confirmLabel: 'Delete' });
  if (!ok) return;
  try {
    await api.delete(`/api/v1/docs/${doc.id}`);
    toast({ tone: 'success', title: 'Deleted', message: `"${doc.title}" was deleted.` });
    load();
  } catch (e: any) {
    toast({ tone: 'error', title: 'Gagal menghapus', message: e?.response?.data?.message ?? 'Terjadi kesalahan.' });
  }
}

function onSearch() { page.value = 1; load(); }
function formatSize(bytes: number) {
  if (bytes < 1024) return bytes + ' B';
  if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
  return (bytes / 1048576).toFixed(1) + ' MB';
}
function formatDate(iso: string) {
  return new Date(iso).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
}

onMounted(() => { load(); loadCategories(); });
</script>

<template>
  <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
    <div>
      <p class="text-sm font-extrabold uppercase text-teal-700 dark:text-teal-500">Knowledge</p>
      <h1 class="mt-1 text-3xl font-extrabold tracking-tight">Docs</h1>
      <p class="mt-1 text-sm font-medium text-slate-500 dark:text-zinc-500">Save links, notes, and files for quick reference.</p>
    </div>
    <button class="btn btn-primary" @click="router.push('/docs/create')">
      <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg>
      New Doc
    </button>
  </div>

  <div class="mb-5 flex flex-wrap items-center gap-2">
    <input v-model="search" class="field h-9 flex-1 min-w-48" placeholder="Search docs…" @keydown.enter="onSearch" />
    <select v-model="filterCat" class="field h-9 w-44" @change="onSearch">
      <option value="">All categories</option>
      <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
    </select>
    <button class="btn btn-muted h-9 px-3" @click="onSearch">Search</button>
  </div>

  <div v-if="loading" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
    <div v-for="i in 6" :key="i" class="skeleton h-24 rounded-xl" />
  </div>

  <div v-else-if="!docs.length" class="card p-10 text-center">
    <div class="mx-auto mb-4 grid h-12 w-12 place-items-center rounded-xl bg-teal-50 dark:bg-teal-900/20">
      <svg class="h-6 w-6 text-teal-700 dark:text-teal-400" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/></svg>
    </div>
    <h2 class="text-base font-extrabold text-slate-900 dark:text-zinc-100">Belum ada doc</h2>
    <p class="mx-auto mt-2 max-w-md text-sm text-slate-400 dark:text-zinc-500">Simpan referensi, catatan, dan file penting ke sini.</p>
    <button class="btn btn-primary mt-4" @click="router.push('/docs/create')">Buat doc pertama</button>
  </div>

  <div v-else class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
    <div
      v-for="doc in docs"
      :key="doc.id"
      class="card group cursor-pointer p-4 hover:border-teal-200 hover:shadow-sm"
      @click="router.push(`/docs/${doc.id}`)"
    >
      <div class="flex items-start justify-between gap-2">
        <div class="min-w-0 flex-1">
          <p v-if="doc.category" class="mb-1 text-[11px] font-extrabold uppercase text-teal-700 dark:text-teal-500">{{ doc.category }}</p>
          <p class="truncate font-extrabold text-slate-900 dark:text-zinc-100">{{ doc.title }}</p>
        </div>
        <button
          class="shrink-0 rounded-lg p-1 text-slate-300 hover:bg-red-50 hover:text-red-600 dark:text-zinc-700 dark:hover:bg-red-900/20 dark:hover:text-red-400 transition-colors"
          aria-label="Hapus doc"
          @click.stop="remove(doc)"
        >
          <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" viewBox="0 0 24 24"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6" /></svg>
        </button>
      </div>
      <div class="mt-3 flex flex-wrap items-center gap-3 text-xs font-medium text-slate-400 dark:text-zinc-600">
        <span v-if="doc.reference_urls?.length">{{ doc.reference_urls.length }} link{{ doc.reference_urls.length > 1 ? 's' : '' }}</span>
        <span v-if="doc.files?.length">{{ doc.files.length }} file{{ doc.files.length > 1 ? 's' : '' }}</span>
        <span class="ml-auto">{{ formatDate(doc.created_at) }}</span>
      </div>
    </div>
  </div>

  <div v-if="meta && meta.last_page > 1" class="mt-6 flex items-center justify-between gap-3">
    <p class="text-sm text-slate-500 dark:text-zinc-500">Halaman {{ meta.current_page }} dari {{ meta.last_page }}</p>
    <div class="flex gap-2">
      <button class="btn btn-muted" :disabled="meta.current_page <= 1" @click="page = meta.current_page - 1; load()">← Prev</button>
      <button class="btn btn-muted" :disabled="meta.current_page >= meta.last_page" @click="page = meta.current_page + 1; load()">Next →</button>
    </div>
  </div>
</template>
