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
  const params: any = { page: page.value, per_page: 20 };
  if (search.value) params.search = search.value;
  if (filterCat.value) params.category = filterCat.value;
  const data = await api.get('/api/v1/docs', { params }).then(unwrap);
  docs.value = data.docs?.data || [];
  meta.value = data.docs;
  loading.value = false;
}

async function loadCategories() {
  const data = await api.get('/api/v1/docs/categories').then(unwrap);
  categories.value = data.categories;
}

async function remove(doc: any) {
  const ok = await confirmAction({ title: 'Delete doc', message: `Delete "${doc.title}"? This also removes all attached files.`, confirmLabel: 'Delete' });
  if (!ok) return;
  await api.delete(`/api/v1/docs/${doc.id}`);
  toast({ tone: 'success', title: 'Deleted', message: `"${doc.title}" was deleted.` });
  load();
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

  <div v-if="loading" class="py-12 text-center text-sm font-medium text-slate-400 dark:text-zinc-600">Loading…</div>

  <div v-else-if="!docs.length" class="py-12 text-center">
    <p class="font-extrabold text-slate-600 dark:text-zinc-400">No docs yet.</p>
    <p class="mt-1 text-sm text-slate-400 dark:text-zinc-600">Create your first doc to get started.</p>
  </div>

  <div v-else class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
    <div
      v-for="doc in docs"
      :key="doc.id"
      class="card group cursor-pointer p-4 transition hover:shadow-md"
      @click="router.push(`/docs/${doc.id}`)"
    >
      <div class="flex items-start justify-between gap-2">
        <div class="min-w-0 flex-1">
          <p v-if="doc.category" class="mb-1 text-[11px] font-extrabold uppercase text-teal-700 dark:text-teal-500">{{ doc.category }}</p>
          <p class="truncate font-extrabold text-slate-900 dark:text-zinc-100">{{ doc.title }}</p>
        </div>
        <button
          class="shrink-0 rounded-lg p-1 text-slate-400 opacity-0 transition hover:bg-red-50 hover:text-red-600 group-hover:opacity-100 dark:hover:bg-red-900/20 dark:hover:text-red-400"
          title="Delete"
          @click.stop="remove(doc)"
        >
          <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" viewBox="0 0 24 24"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6" /></svg>
        </button>
      </div>
      <div class="mt-3 flex flex-wrap items-center gap-3 text-xs font-medium text-slate-400 dark:text-zinc-600">
        <span v-if="doc.reference_urls?.length">{{ doc.reference_urls.length }} link{{ doc.reference_urls.length > 1 ? 's' : '' }}</span>
        <span v-if="doc.files?.length">{{ doc.files.length }} file{{ doc.files.length > 1 ? 's' : '' }}</span>
        <span class="ml-auto">{{ formatDate(doc.created_at) }}</span>
      </div>
    </div>
  </div>

  <div v-if="meta && meta.last_page > 1" class="mt-6 flex justify-center gap-2">
    <button v-for="p in meta.last_page" :key="p" class="btn btn-muted px-3 py-1.5 text-sm" :class="p === page ? 'bg-teal-700 text-white' : ''" @click="page = p; load()">{{ p }}</button>
  </div>
</template>
