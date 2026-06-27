<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { api, unwrap } from '../api';
import { confirmAction, toast } from '../feedback';

const router = useRouter();
const lists = ref<any[]>([]);
const loading = ref(true);
const creating = ref(false);
const newTitle = ref('');
const showForm = ref(false);
const formInput = ref<HTMLInputElement | null>(null);

async function load() {
  lists.value = (await api.get('/api/v1/lists').then(unwrap)).lists ?? [];
  loading.value = false;
}

async function openForm() {
  showForm.value = true;
  await new Promise((r) => setTimeout(r, 50));
  formInput.value?.focus();
}

async function create() {
  if (!newTitle.value.trim() || creating.value) return;
  creating.value = true;
  try {
    const data = await api.post('/api/v1/lists', { title: newTitle.value.trim() }).then(unwrap);
    newTitle.value = '';
    showForm.value = false;
    router.push(`/lists/${data.list.id}`);
  } finally {
    creating.value = false;
  }
}

async function remove(list: any) {
  const ok = await confirmAction({ title: 'Delete list', message: `Delete "${list.title}"?`, confirmLabel: 'Delete' });
  if (!ok) return;
  await api.delete(`/api/v1/lists/${list.id}`);
  lists.value = lists.value.filter((l) => l.id !== list.id);
  toast({ tone: 'success', title: 'Deleted', message: list.title });
}

const progress = (list: any) => list.items_count ? Math.round((list.completed_count / list.items_count) * 100) : 0;

onMounted(load);
</script>

<template>
  <div>
    <!-- Header -->
    <div class="mb-6 flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-extrabold tracking-tight">Lists</h1>
        <p class="mt-0.5 text-sm text-slate-400 dark:text-zinc-500">{{ lists.length }} list{{ lists.length !== 1 ? 's' : '' }}</p>
      </div>
      <button class="btn btn-primary" @click="openForm">+ New List</button>
    </div>

    <!-- New list inline form -->
    <form v-if="showForm" class="card mb-4 flex items-center gap-2 p-3" @submit.prevent="create">
      <svg class="h-4 w-4 shrink-0 text-slate-300 dark:text-zinc-700" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" viewBox="0 0 24 24"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" /></svg>
      <input
        ref="formInput"
        v-model="newTitle"
        class="flex-1 bg-transparent text-sm font-semibold text-slate-700 placeholder-slate-300 outline-none dark:text-zinc-300 dark:placeholder-zinc-600"
        placeholder="Nama list baru…"
        required
        @keydown.escape="showForm = false; newTitle = ''"
      />
      <button class="btn btn-primary px-3 py-1.5 text-xs" :disabled="creating" type="submit">Create</button>
      <button class="btn btn-muted px-3 py-1.5 text-xs" type="button" @click="showForm = false; newTitle = ''">Cancel</button>
    </form>

    <div v-if="loading" class="card p-8 text-center text-sm text-slate-400">Loading…</div>

    <div v-else-if="lists.length === 0 && !showForm" class="card p-12 text-center">
      <svg class="mx-auto mb-3 h-10 w-10 text-slate-200 dark:text-zinc-700" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" viewBox="0 0 24 24"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" /></svg>
      <p class="text-sm font-semibold text-slate-400">Belum ada list.</p>
      <button class="btn btn-primary mt-4" @click="openForm">Buat list pertama</button>
    </div>

    <div v-else class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
      <div
        v-for="list in lists"
        :key="list.id"
        class="card group relative cursor-pointer p-4 hover:border-teal-300 hover:shadow-sm dark:hover:border-teal-700"
        @click="router.push(`/lists/${list.id}`)"
      >
        <div class="flex items-start justify-between gap-2">
          <h2 class="text-sm font-extrabold leading-snug">{{ list.title }}</h2>
          <div class="flex shrink-0 items-center gap-1">
            <span
              v-if="list.due_today_count > 0"
              class="rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-extrabold text-amber-600 dark:bg-amber-900/30 dark:text-amber-400"
            >{{ list.due_today_count }} hari ini</span>
            <button
              class="rounded-lg p-1 text-slate-300 hover:text-red-400 dark:text-zinc-700 dark:hover:text-red-400 transition-colors"
              aria-label="Hapus list"
              @click.stop="remove(list)"
            >
              <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12" /></svg>
            </button>
          </div>
        </div>

        <div class="mt-3 space-y-1.5">
          <div class="h-1 overflow-hidden rounded-full bg-slate-100 dark:bg-zinc-800">
            <div
              class="h-full rounded-full bg-teal-500 transition-all duration-500"
              :style="{ width: progress(list) + '%' }"
            />
          </div>
          <p class="text-xs font-semibold text-slate-400 dark:text-zinc-500">
            <span class="text-teal-600 dark:text-teal-400">{{ list.completed_count }}</span>/{{ list.items_count }} selesai
          </p>
        </div>
      </div>
    </div>
  </div>
</template>
