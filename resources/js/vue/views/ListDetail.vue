<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api, unwrap } from '../api';
import { confirmAction, toast } from '../feedback';

const route = useRoute();
const router = useRouter();
const list = ref<any>(null);
const items = ref<any[]>([]);
const loading = ref(true);
const newContent = ref('');
const adding = ref(false);
const editingTitle = ref(false);
const titleDraft = ref('');
const inputRef = ref<HTMLInputElement | null>(null);
const titleInputRef = ref<HTMLInputElement | null>(null);
const expandedId = ref<number | null>(null);
const filter = ref<'all' | 'today' | 'priority'>('all');

async function load() {
  const data = await api.get(`/api/v1/lists/${route.params.id}`).then(unwrap);
  list.value = data.list;
  items.value = data.list.items ?? [];
  loading.value = false;
  await nextTick();
  inputRef.value?.focus();
}

async function addItem() {
  if (!newContent.value.trim() || adding.value) return;
  adding.value = true;
  try {
    const data = await api.post(`/api/v1/lists/${list.value.id}/items`, { content: newContent.value.trim() }).then(unwrap);
    items.value.push(data.item);
    newContent.value = '';
    await nextTick();
    inputRef.value?.focus();
  } finally {
    adding.value = false;
  }
}

async function toggleItem(item: any) {
  item.completed = !item.completed;
  if (item.completed) expandedId.value = null;
  await api.patch(`/api/v1/lists/${list.value.id}/items/${item.id}`, { completed: item.completed });
}

async function deleteItem(item: any) {
  items.value = items.value.filter((i) => i.id !== item.id);
  await api.delete(`/api/v1/lists/${list.value.id}/items/${item.id}`);
}

async function patchItem(item: any, patch: Record<string, any>) {
  Object.assign(item, patch);
  await api.patch(`/api/v1/lists/${list.value.id}/items/${item.id}`, patch);
}

function toggleExpand(item: any) {
  if (item.completed) return;
  expandedId.value = expandedId.value === item.id ? null : item.id;
}

async function startEditTitle() {
  titleDraft.value = list.value.title;
  editingTitle.value = true;
  await nextTick();
  titleInputRef.value?.focus();
  titleInputRef.value?.select();
}

async function saveTitle() {
  if (!titleDraft.value.trim()) { editingTitle.value = false; return; }
  const prev = list.value.title;
  list.value.title = titleDraft.value.trim();
  editingTitle.value = false;
  try {
    await api.patch(`/api/v1/lists/${list.value.id}`, { title: list.value.title });
  } catch {
    list.value.title = prev;
  }
}

async function deleteList() {
  const ok = await confirmAction({ title: 'Delete list', message: `Delete "${list.value.title}"?`, confirmLabel: 'Delete' });
  if (!ok) return;
  await api.delete(`/api/v1/lists/${list.value.id}`);
  toast({ tone: 'success', title: 'Deleted', message: list.value.title });
  router.push('/lists');
}

function todayStr(): string {
  return new Date().toISOString().slice(0, 10);
}

function formatDue(dateStr: string | null): string | null {
  if (!dateStr) return null;
  const today = new Date(); today.setHours(0, 0, 0, 0);
  const d = new Date(dateStr + 'T00:00:00');
  const diff = Math.round((d.getTime() - today.getTime()) / 86400000);
  if (diff < 0) return `${Math.abs(diff)}d terlambat`;
  if (diff === 0) return 'Hari ini';
  if (diff === 1) return 'Besok';
  if (diff < 7) return d.toLocaleDateString('id', { weekday: 'short' });
  return d.toLocaleDateString('id', { month: 'short', day: 'numeric' });
}

function dueClass(dateStr: string | null): string {
  if (!dateStr) return '';
  const today = new Date(); today.setHours(0, 0, 0, 0);
  const d = new Date(dateStr + 'T00:00:00');
  const diff = Math.round((d.getTime() - today.getTime()) / 86400000);
  if (diff < 0) return 'text-red-500 dark:text-red-400';
  if (diff === 0) return 'text-amber-500 dark:text-amber-400';
  return 'text-slate-400 dark:text-zinc-500';
}

function isToday(dateStr: string | null): boolean {
  return !!dateStr && dateStr.slice(0, 10) === todayStr();
}

const PRIORITY = [
  { value: 0, label: 'None', color: '' },
  { value: 1, label: 'P1', color: 'text-red-500' },
  { value: 2, label: 'P2', color: 'text-orange-400' },
  { value: 3, label: 'P3', color: 'text-blue-400' },
] as const;

function priorityInfo(p: number) {
  return PRIORITY.find((x) => x.value === p) ?? PRIORITY[0];
}

const pendingAll = computed(() => items.value.filter((i) => !i.completed));
const doneAll = computed(() => items.value.filter((i) => i.completed));
const progress = computed(() => items.value.length ? Math.round((doneAll.value.length / items.value.length) * 100) : 0);

const filteredPending = computed(() => {
  const p = pendingAll.value;
  if (filter.value === 'today') return p.filter((i) => isToday(i.due_date));
  if (filter.value === 'priority') return [...p.filter((i) => i.priority > 0)].sort((a, b) => a.priority - b.priority);
  return p;
});

onMounted(load);
</script>

<template>
  <div v-if="loading" class="mx-auto max-w-xl space-y-3">
    <div class="skeleton h-8 w-32 rounded-xl" />
    <div class="skeleton h-10 rounded-2xl" />
    <div v-for="i in 5" :key="i" class="skeleton h-12 rounded-xl" />
  </div>
  <div v-else-if="list" class="mx-auto max-w-xl">

    <!-- Back + header -->
    <div class="mb-5">
      <button
        class="mb-3 flex items-center gap-1.5 text-xs font-semibold text-slate-400 hover:text-slate-700 dark:hover:text-zinc-200 transition-colors"
        @click="router.push('/lists')"
      >
        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" viewBox="0 0 24 24"><path d="M19 12H5M12 5l-7 7 7 7" /></svg>
        All lists
      </button>
      <div class="flex items-center justify-between gap-3">
        <input
          v-if="editingTitle"
          ref="titleInputRef"
          v-model="titleDraft"
          class="field flex-1 text-xl font-extrabold"
          @blur="saveTitle"
          @keydown.enter.prevent="saveTitle"
          @keydown.escape="editingTitle = false"
        />
        <h1
          v-else
          class="cursor-pointer text-xl font-extrabold tracking-tight hover:text-teal-700 dark:hover:text-teal-400 transition-colors"
          @click="startEditTitle"
        >{{ list.title }}</h1>
        <button
          class="shrink-0 rounded-lg p-1.5 text-slate-300 hover:bg-red-50 hover:text-red-500 dark:text-zinc-600 dark:hover:bg-red-900/20 dark:hover:text-red-400 transition-colors"
          aria-label="Hapus list"
          @click="deleteList"
        >
          <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="M3 6h18M19 6l-1 14H6L5 6M10 11v6M14 11v6M8 6V4h8v2" /></svg>
        </button>
      </div>

      <!-- Progress bar -->
      <div v-if="items.length > 0" class="mt-3 flex items-center gap-3">
        <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-100 dark:bg-zinc-800">
          <div class="h-full rounded-full bg-teal-500 transition-all duration-500" :style="{ width: progress + '%' }" />
        </div>
        <span class="shrink-0 text-xs font-bold tabular-nums text-slate-400 dark:text-zinc-500">
          {{ doneAll.length }}/{{ items.length }}
        </span>
      </div>
    </div>

    <!-- Filter tabs -->
    <div class="mb-3 flex gap-1">
      <button
        v-for="tab in ([{ key: 'all', label: 'Semua' }, { key: 'today', label: 'Hari ini' }, { key: 'priority', label: 'Prioritas' }] as const)"
        :key="tab.key"
        class="rounded-md px-3 py-1 text-xs font-semibold transition-colors"
        :class="filter === tab.key
          ? 'bg-teal-600 text-white'
          : 'bg-slate-100 text-slate-500 hover:bg-slate-200 dark:bg-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-700'"
        @click="filter = tab.key"
      >{{ tab.label }}</button>
    </div>

    <!-- Main card -->
    <div class="card overflow-hidden">

      <!-- Empty states -->
      <div v-if="items.length === 0" class="px-5 py-8 text-center text-sm text-slate-400 dark:text-zinc-600">
        Belum ada item. Tambah di bawah.
      </div>
      <div v-else-if="filteredPending.length === 0 && doneAll.length === items.length && filter === 'all'" class="px-5 py-4 text-center text-sm font-semibold text-teal-600 dark:text-teal-400">
        Semua selesai!
      </div>
      <div v-else-if="filteredPending.length === 0 && filter !== 'all'" class="px-5 py-4 text-center text-sm text-slate-400 dark:text-zinc-600">
        Tidak ada item untuk filter ini.
      </div>

      <!-- Pending items -->
      <div v-for="item in filteredPending" :key="item.id" class="group border-b border-slate-100 dark:border-zinc-800 last:border-b-0">

        <!-- Row -->
        <div
          class="flex cursor-pointer items-start gap-3 px-4 py-3 hover:bg-slate-50 dark:hover:bg-zinc-800/40 transition-colors"
          @click="toggleExpand(item)"
        >
          <!-- Checkbox -->
          <button
            class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-md border-2 border-slate-300 hover:border-teal-500 dark:border-zinc-600 dark:hover:border-teal-500 transition-colors"
            @click.stop="toggleItem(item)"
          />

          <!-- Content + meta -->
          <div class="min-w-0 flex-1">
            <span class="text-sm font-medium leading-snug">{{ item.content }}</span>
            <div v-if="item.due_date || item.priority > 0 || item.notes" class="mt-1 flex flex-wrap items-center gap-2">
              <span
                v-if="item.priority > 0"
                class="flex items-center gap-0.5 text-[10px] font-extrabold uppercase tracking-wide"
                :class="priorityInfo(item.priority).color"
              >
                <svg class="h-2.5 w-2.5" viewBox="0 0 24 24" fill="currentColor"><path d="M4 4h16v10l-8 6-8-6V4z"/></svg>
                {{ priorityInfo(item.priority).label }}
              </span>
              <span
                v-if="item.due_date"
                class="flex items-center gap-0.5 text-[10px] font-semibold"
                :class="dueClass(item.due_date)"
              >
                <svg class="h-2.5 w-2.5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" /><path d="M16 2v4M8 2v4M3 10h18" /></svg>
                {{ formatDue(item.due_date) }}
              </span>
              <span v-if="item.notes" class="text-[10px] text-slate-300 dark:text-zinc-600">···</span>
            </div>
          </div>

          <!-- Delete + chevron -->
          <div class="flex shrink-0 items-center gap-1">
            <button
              class="rounded p-1 text-slate-300 hover:text-red-400 dark:text-zinc-700 dark:hover:text-red-400 transition-colors"
              aria-label="Hapus item"
              @click.stop="deleteItem(item)"
            >
              <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12" /></svg>
            </button>
            <svg
              class="h-3.5 w-3.5 text-slate-300 dark:text-zinc-600 transition-transform duration-150"
              :class="expandedId === item.id ? 'rotate-180' : ''"
              fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" viewBox="0 0 24 24"
            ><path d="M6 9l6 6 6-6" /></svg>
          </div>
        </div>

        <!-- Expanded detail panel -->
        <div v-if="expandedId === item.id" class="border-t border-slate-100 bg-slate-50 px-4 pb-4 pt-3 dark:border-zinc-800 dark:bg-zinc-900/60">
          <!-- Edit content inline -->
          <input
            class="field mb-3 w-full text-sm font-medium"
            :value="item.content"
            placeholder="Konten"
            @change="patchItem(item, { content: ($event.target as HTMLInputElement).value.trim() || item.content })"
          />

          <div class="mb-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
            <!-- Priority -->
            <div>
              <label class="mb-1 block text-[10px] font-extrabold uppercase tracking-wide text-slate-400">Prioritas</label>
              <select
                class="field w-full text-xs"
                :value="item.priority ?? 0"
                @change="patchItem(item, { priority: Number(($event.target as HTMLSelectElement).value) })"
              >
                <option v-for="p in PRIORITY" :key="p.value" :value="p.value">
                  {{ p.value === 0 ? '— Tidak ada' : p.label }}
                </option>
              </select>
            </div>
            <!-- Due date -->
            <div>
              <label class="mb-1 block text-[10px] font-extrabold uppercase tracking-wide text-slate-400">Due date</label>
              <input
                type="date"
                class="field w-full text-xs"
                :value="item.due_date ?? ''"
                @change="patchItem(item, { due_date: ($event.target as HTMLInputElement).value || null })"
              />
            </div>
          </div>

          <!-- Notes -->
          <div>
            <label class="mb-1 block text-[10px] font-extrabold uppercase tracking-wide text-slate-400">Catatan</label>
            <textarea
              class="field w-full resize-none text-sm"
              rows="3"
              :value="item.notes ?? ''"
              placeholder="Tambah catatan…"
              @change="patchItem(item, { notes: ($event.target as HTMLTextAreaElement).value || null })"
            />
          </div>
        </div>
      </div>

      <!-- Add item input -->
      <form class="flex items-center gap-2 border-t border-slate-100 px-4 py-2.5 dark:border-zinc-800" @submit.prevent="addItem">
        <svg class="h-4 w-4 shrink-0 text-slate-300 dark:text-zinc-700" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg>
        <input
          ref="inputRef"
          v-model="newContent"
          class="flex-1 bg-transparent py-1 text-sm font-medium text-slate-700 placeholder-slate-300 outline-none dark:text-zinc-300 dark:placeholder-zinc-600"
          placeholder="Tambah item…"
          :disabled="adding"
          @keydown.enter.prevent="addItem"
        />
      </form>

      <!-- Done section -->
      <template v-if="doneAll.length > 0 && filter === 'all'">
        <div class="border-t border-slate-100 px-4 py-2 dark:border-zinc-800">
          <p class="text-[10px] font-extrabold uppercase tracking-widest text-slate-300 dark:text-zinc-700">
            Selesai · {{ doneAll.length }}
          </p>
        </div>
        <div
          v-for="item in doneAll"
          :key="item.id"
          class="group flex items-center gap-3 border-t border-slate-50 px-4 py-2.5 dark:border-zinc-800/60 hover:bg-slate-50 dark:hover:bg-zinc-800/30 transition-colors"
        >
          <button
            class="flex h-5 w-5 shrink-0 items-center justify-center rounded-md border-2 border-teal-500 bg-teal-500 text-white hover:bg-teal-600 transition-colors"
            @click="toggleItem(item)"
          >
            <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5" /></svg>
          </button>
          <span class="flex-1 text-sm text-slate-300 line-through dark:text-zinc-600">{{ item.content }}</span>
          <button
            class="shrink-0 rounded p-1 text-slate-300 hover:text-red-400 dark:text-zinc-700 dark:hover:text-red-400 transition-colors"
            aria-label="Hapus item"
            @click="deleteItem(item)"
          >
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12" /></svg>
          </button>
        </div>
      </template>
    </div>
  </div>
</template>
