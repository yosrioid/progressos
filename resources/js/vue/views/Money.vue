<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { api, unwrap } from '../api';
import { toast } from '../feedback';

interface MonthSummary {
  month: string; income: number; expense: number; net: number; total_count: number;
}
interface Subcategory { subcategory: string | null; total: number; count: number; }
interface Category { category: string; type: string; total: number; count: number; subcategories: Subcategory[]; }
interface Transaction {
  id: number; transacted_at: string; date: string; account: string;
  category: string; subcategory: string | null; description: string | null;
  amount: number; type: string; currency: string;
}
interface MonthData {
  month: string;
  summary: { income: number; expense: number; net: number; total_count: number; expense_count: number; income_count: number; transfer_count: number };
  categories: Category[];
  transactions: Transaction[];
}

const months = ref<MonthSummary[]>([]);
const monthData = ref<MonthData | null>(null);
const selectedMonth = ref('');
const loadingMonths = ref(true);
const loadingData = ref(false);
const filterType = ref<string>('all');
const filterCategory = ref<string>('');
const expandedCats = ref<Set<string>>(new Set());

// Import
const importing = ref(false);
const importResult = ref<{ imported: number; duplicates: number; skipped: number } | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);
const dragOver = ref(false);

function formatRp(val: number | null | undefined): string {
  if (val == null) return '—';
  return 'Rp ' + Math.round(val).toLocaleString('id-ID');
}
function monthLabel(m: string) {
  return new Date(m + '-02').toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
}
function shortMonth(m: string) {
  return new Date(m + '-02').toLocaleDateString('id-ID', { month: 'short', year: '2-digit' });
}
function dateLabel(iso: string) {
  return new Date(iso).toLocaleDateString('id-ID', { weekday: 'short', day: 'numeric', month: 'short' });
}
function typeLabel(t: string): string {
  return { income: 'Pemasukan', expense: 'Pengeluaran', transfer_in: 'Transfer Masuk', transfer_out: 'Transfer Keluar' }[t] ?? t;
}
function typeColor(t: string): string {
  return { income: 'text-teal-600 dark:text-teal-400', expense: 'text-red-600 dark:text-red-400', transfer_in: 'text-sky-600 dark:text-sky-400', transfer_out: 'text-amber-600 dark:text-amber-400' }[t] ?? '';
}
function typeBg(t: string): string {
  return { income: 'bg-teal-50 dark:bg-teal-900/20', expense: 'bg-red-50 dark:bg-red-900/10', transfer_in: 'bg-sky-50 dark:bg-sky-900/10', transfer_out: 'bg-amber-50 dark:bg-amber-900/10' }[t] ?? 'bg-slate-50';
}
function typePill(t: string): string {
  return { income: 'bg-teal-100 text-teal-700 dark:bg-teal-900/40 dark:text-teal-300', expense: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300', transfer_in: 'bg-sky-100 text-sky-700', transfer_out: 'bg-amber-100 text-amber-700' }[t] ?? '';
}

async function loadMonths() {
  loadingMonths.value = true;
  try {
    const data = await api.get('/api/v1/money/months').then(unwrap);
    months.value = data.months ?? [];
    if (!selectedMonth.value && months.value.length) {
      selectedMonth.value = months.value[0].month;
    }
  } finally {
    loadingMonths.value = false;
  }
}

async function loadMonth(m: string) {
  if (loadingData.value) return;
  selectedMonth.value = m;
  filterType.value = 'all';
  filterCategory.value = '';
  loadingData.value = true;
  try {
    monthData.value = await api.get(`/api/v1/money/month/${m}`).then(unwrap);
  } finally {
    loadingData.value = false;
  }
}

watch(selectedMonth, (m) => { if (m) loadMonth(m); });

const filteredTransactions = computed(() => {
  if (!monthData.value) return [];
  return monthData.value.transactions.filter((t) => {
    if (filterType.value !== 'all' && t.type !== filterType.value) return false;
    if (filterCategory.value && t.category !== filterCategory.value) return false;
    return true;
  });
});

const groupedTransactions = computed(() => {
  const groups: Record<string, Transaction[]> = {};
  filteredTransactions.value.forEach((t) => {
    if (!groups[t.date]) groups[t.date] = [];
    groups[t.date].push(t);
  });
  return Object.entries(groups).sort(([a], [b]) => b.localeCompare(a)).map(([date, txns]) => ({ date, txns }));
});

const expenseCategories = computed(() => monthData.value?.categories.filter((c) => c.type === 'expense') ?? []);
const maxCatAmount = computed(() => Math.max(...expenseCategories.value.map((c) => c.total), 1));

function toggleCat(cat: string) {
  if (expandedCats.value.has(cat)) expandedCats.value.delete(cat);
  else expandedCats.value.add(cat);
}

async function handleFile(file: File) {
  if (!file.name.endsWith('.xlsx')) {
    toast({ tone: 'error', title: 'Format salah', message: 'Hanya file .xlsx yang didukung.' });
    return;
  }
  importing.value = true;
  importResult.value = null;
  try {
    const form = new FormData();
    form.append('file', file);
    const result = await api.post('/api/v1/money/import', form).then(unwrap);
    importResult.value = result;
    if (result.imported > 0) {
      toast({ tone: 'success', title: 'Import selesai', message: `${result.imported} transaksi ditambahkan.` });
      await loadMonths();
    } else {
      toast({ tone: 'success', title: 'Tidak ada data baru', message: `Semua ${result.duplicates} baris sudah ada.` });
    }
  } finally {
    importing.value = false;
  }
}

function onFileChange(e: Event) {
  const f = (e.target as HTMLInputElement).files?.[0];
  if (f) handleFile(f);
}
function onDrop(e: DragEvent) {
  dragOver.value = false;
  const f = e.dataTransfer?.files?.[0];
  if (f) handleFile(f);
}

onMounted(loadMonths);
</script>

<template>
  <div>
    <!-- Header -->
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <p class="text-xs font-extrabold uppercase text-teal-700 dark:text-teal-500">Keuangan</p>
        <h1 class="text-3xl font-extrabold tracking-tight">Transaksi</h1>
        <p class="mt-1 text-sm font-medium text-slate-500 dark:text-zinc-500">Import dari Money Manager export (.xlsx). Data disimpan, tidak ada duplikat.</p>
      </div>
      <!-- Import drop zone -->
      <div
        class="shrink-0"
        @dragover.prevent="dragOver = true"
        @dragleave="dragOver = false"
        @drop.prevent="onDrop"
      >
        <button
          class="btn shrink-0 transition-all"
          :class="dragOver ? 'btn-primary ring-2 ring-teal-400' : 'btn-muted'"
          :disabled="importing"
          @click="fileInput?.click()"
        >
          <svg class="mr-1.5 inline h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg>
          {{ importing ? 'Mengimpor…' : dragOver ? 'Lepas file di sini' : 'Import .xlsx' }}
        </button>
        <input ref="fileInput" type="file" accept=".xlsx" class="sr-only" @change="onFileChange" />
      </div>
    </div>

    <!-- Import result banner -->
    <div v-if="importResult" class="mb-4 flex items-center justify-between rounded-2xl border border-teal-200 bg-teal-50 px-4 py-3 dark:border-teal-800/40 dark:bg-teal-900/10">
      <p class="text-sm font-extrabold text-teal-700 dark:text-teal-400">
        Import: <span class="text-teal-800 dark:text-teal-300">{{ importResult.imported }} ditambah</span>
        <span v-if="importResult.duplicates" class="ml-2 text-slate-500 dark:text-zinc-400">· {{ importResult.duplicates }} sudah ada</span>
        <span v-if="importResult.skipped" class="ml-2 text-slate-400 dark:text-zinc-500">· {{ importResult.skipped }} dilewati</span>
      </p>
      <button class="text-teal-600 hover:text-teal-800" @click="importResult = null">✕</button>
    </div>

    <!-- Skeleton: loading months -->
    <div v-if="loadingMonths" class="mb-4 space-y-3">
      <div class="skeleton h-12 rounded-2xl" />
      <div class="skeleton h-48 rounded-2xl" />
    </div>

    <template v-else-if="!months.length">
      <!-- Empty state -->
      <div class="card p-14 text-center">
        <div class="mx-auto mb-4 grid h-14 w-14 place-items-center rounded-2xl bg-teal-50 dark:bg-teal-900/20">
          <svg class="h-7 w-7 text-teal-700 dark:text-teal-400" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2m0 0v20M2 12h20"/></svg>
        </div>
        <h2 class="text-base font-extrabold text-slate-900 dark:text-zinc-100">Belum ada transaksi</h2>
        <p class="mx-auto mt-2 max-w-xs text-sm text-slate-400 dark:text-zinc-500">Export dari Money Manager kamu, lalu import file .xlsx-nya ke sini.</p>
        <button class="btn btn-primary mt-4" @click="fileInput?.click()">Import sekarang</button>
      </div>
    </template>

    <template v-else>
      <!-- Month tabs -->
      <div class="mb-4 overflow-x-auto">
        <div class="flex min-w-max gap-2 pb-1">
          <button
            v-for="m in months"
            :key="m.month"
            class="flex shrink-0 flex-col items-center rounded-2xl border px-4 py-2.5 transition-all"
            :class="selectedMonth === m.month
              ? 'border-teal-400 bg-teal-50 shadow-sm dark:border-teal-600 dark:bg-teal-900/20'
              : 'border-slate-200 bg-white hover:border-teal-200 hover:bg-teal-50/30 dark:border-zinc-700 dark:bg-zinc-900/40'"
            @click="loadMonth(m.month)"
          >
            <span class="text-xs font-extrabold capitalize" :class="selectedMonth === m.month ? 'text-teal-700 dark:text-teal-300' : 'text-slate-500 dark:text-zinc-400'">{{ shortMonth(m.month) }}</span>
            <span class="mt-0.5 text-[10px] font-semibold text-slate-400 dark:text-zinc-500">{{ m.total_count }} trx</span>
            <span class="mt-1 text-[11px] font-extrabold" :class="m.net >= 0 ? 'text-teal-600 dark:text-teal-400' : 'text-red-500 dark:text-red-400'">
              {{ m.net >= 0 ? '+' : '' }}{{ Math.round(m.net / 1000) }}k
            </span>
          </button>
        </div>
      </div>

      <!-- Month data skeleton -->
      <div v-if="loadingData" class="space-y-3">
        <div class="skeleton h-28 rounded-2xl" />
        <div class="skeleton h-48 rounded-2xl" />
        <div v-for="i in 5" :key="i" class="skeleton h-16 rounded-xl" />
      </div>

      <template v-else-if="monthData">
        <!-- Summary cards -->
        <div class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-4">
          <div class="rounded-2xl border border-teal-100 bg-teal-50/80 p-4 dark:border-teal-800/30 dark:bg-teal-900/10">
            <p class="label mb-1">Pemasukan</p>
            <p class="text-xl font-extrabold text-teal-800 dark:text-teal-300">{{ formatRp(monthData.summary.income) }}</p>
            <p class="mt-0.5 text-[11px] font-semibold text-teal-600 dark:text-teal-500">{{ monthData.summary.income_count }} transaksi</p>
          </div>
          <div class="rounded-2xl border border-red-100 bg-red-50/80 p-4 dark:border-red-800/30 dark:bg-red-900/10">
            <p class="label mb-1">Pengeluaran</p>
            <p class="text-xl font-extrabold text-red-700 dark:text-red-400">{{ formatRp(monthData.summary.expense) }}</p>
            <p class="mt-0.5 text-[11px] font-semibold text-red-500 dark:text-red-500">{{ monthData.summary.expense_count }} transaksi</p>
          </div>
          <div class="rounded-2xl border p-4" :class="monthData.summary.net >= 0 ? 'border-teal-200 bg-teal-50 dark:border-teal-700/40 dark:bg-teal-900/15' : 'border-red-200 bg-red-50 dark:border-red-700/40 dark:bg-red-900/10'">
            <p class="label mb-1">Selisih (Net)</p>
            <p class="text-xl font-extrabold" :class="monthData.summary.net >= 0 ? 'text-teal-800 dark:text-teal-300' : 'text-red-700 dark:text-red-400'">
              {{ monthData.summary.net >= 0 ? '+' : '' }}{{ formatRp(monthData.summary.net) }}
            </p>
            <p class="mt-0.5 text-[11px] font-semibold text-slate-400 dark:text-zinc-500">pemasukan − pengeluaran</p>
          </div>
          <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-4 dark:border-zinc-700 dark:bg-zinc-800/40">
            <p class="label mb-1">Transfer</p>
            <p class="text-xl font-extrabold text-slate-700 dark:text-zinc-200">{{ monthData.summary.transfer_count }}</p>
            <p class="mt-0.5 text-[11px] font-semibold text-slate-400 dark:text-zinc-500">antar akun</p>
          </div>
        </div>

        <!-- Main layout: category breakdown + transaction list -->
        <div class="grid gap-4 xl:grid-cols-[280px_1fr]">
          <!-- Category breakdown -->
          <div class="space-y-2">
            <p class="px-1 text-xs font-extrabold uppercase tracking-wider text-slate-400 dark:text-zinc-500">Per kategori</p>
            <div class="card p-3">
              <!-- All filter -->
              <button
                class="mb-2 flex w-full items-center justify-between rounded-xl px-3 py-2 text-sm font-extrabold transition"
                :class="filterCategory === '' && filterType === 'all' ? 'bg-teal-50 text-teal-700 dark:bg-teal-900/20 dark:text-teal-300' : 'hover:bg-slate-50 dark:hover:bg-zinc-800'"
                @click="filterCategory = ''; filterType = 'all'"
              >
                <span>Semua</span>
                <span class="text-xs font-semibold text-slate-400">{{ monthData.summary.total_count }}</span>
              </button>
              <!-- Income filter -->
              <button
                class="mb-1 flex w-full items-center justify-between rounded-xl px-3 py-2 text-sm transition"
                :class="filterType === 'income' ? 'bg-teal-50 font-extrabold text-teal-700 dark:bg-teal-900/20 dark:text-teal-300' : 'font-semibold hover:bg-slate-50 dark:hover:bg-zinc-800'"
                @click="filterCategory = ''; filterType = filterType === 'income' ? 'all' : 'income'"
              >
                <span>💰 Pemasukan</span>
                <span class="font-extrabold text-teal-600 dark:text-teal-400">{{ formatRp(monthData.summary.income) }}</span>
              </button>
              <!-- Expense categories -->
              <div class="mt-1 space-y-0.5">
                <div v-for="cat in expenseCategories" :key="cat.category">
                  <button
                    class="flex w-full items-center gap-2 rounded-xl px-3 py-2 text-sm transition"
                    :class="filterCategory === cat.category ? 'bg-red-50 font-extrabold text-red-700 dark:bg-red-900/15 dark:text-red-400' : 'font-semibold text-slate-700 hover:bg-slate-50 dark:text-zinc-300 dark:hover:bg-zinc-800'"
                    @click="filterCategory = filterCategory === cat.category ? '' : cat.category; filterType = 'expense'"
                  >
                    <div class="h-1.5 rounded-full bg-red-400 dark:bg-red-500" :style="{ width: `${Math.max(4, (cat.total / maxCatAmount) * 28)}px` }" />
                    <span class="min-w-0 flex-1 truncate text-left">{{ cat.category }}</span>
                    <span class="shrink-0 text-xs font-extrabold text-red-600 dark:text-red-400">{{ formatRp(cat.total) }}</span>
                  </button>
                  <!-- Subcategories when expanded -->
                  <div v-if="filterCategory === cat.category" class="ml-6 mt-0.5 space-y-0.5">
                    <button
                      v-for="sub in cat.subcategories"
                      :key="sub.subcategory ?? 'other'"
                      class="flex w-full items-center justify-between rounded-lg px-2 py-1.5 text-xs font-semibold text-slate-500 transition hover:bg-slate-50 dark:text-zinc-400 dark:hover:bg-zinc-800"
                    >
                      <span class="truncate">{{ sub.subcategory || 'Lainnya' }}</span>
                      <span class="ml-2 font-extrabold text-red-500 dark:text-red-400">{{ formatRp(sub.total) }}</span>
                    </button>
                  </div>
                </div>
              </div>
              <!-- Transfer filter -->
              <button
                class="mt-1 flex w-full items-center justify-between rounded-xl px-3 py-2 text-sm transition"
                :class="filterType === 'transfer_out' ? 'bg-amber-50 font-extrabold text-amber-700 dark:bg-amber-900/15' : 'font-semibold text-slate-500 hover:bg-slate-50 dark:text-zinc-500 dark:hover:bg-zinc-800'"
                @click="filterCategory = ''; filterType = filterType === 'transfer_out' ? 'all' : 'transfer_out'"
              >
                <span>↔ Transfer</span>
                <span class="text-xs font-semibold text-slate-400">{{ monthData.summary.transfer_count }}</span>
              </button>
            </div>
          </div>

          <!-- Transactions list -->
          <div class="min-w-0">
            <div class="mb-2 flex items-center justify-between px-1">
              <p class="text-xs font-extrabold uppercase tracking-wider text-slate-400 dark:text-zinc-500">
                {{ filterType === 'all' ? 'Semua transaksi' : typeLabel(filterType) }}
                <span v-if="filterCategory" class="ml-1 text-red-500">— {{ filterCategory }}</span>
              </p>
              <span class="text-[11px] font-extrabold text-slate-400 dark:text-zinc-600">{{ filteredTransactions.length }} trx</span>
            </div>

            <div v-if="!filteredTransactions.length" class="card p-8 text-center text-sm font-semibold text-slate-400">
              Tidak ada transaksi untuk filter ini.
            </div>

            <div v-else class="space-y-4">
              <div v-for="group in groupedTransactions" :key="group.date">
                <!-- Date header -->
                <div class="flex items-center gap-3">
                  <p class="text-xs font-extrabold text-slate-500 capitalize dark:text-zinc-400">{{ dateLabel(group.txns[0].transacted_at) }}</p>
                  <div class="flex-1 border-t border-slate-100 dark:border-zinc-800" />
                  <span class="text-[11px] font-semibold text-slate-400 dark:text-zinc-600">
                    <span v-if="group.txns.filter(t => t.type === 'expense').length">
                      −{{ formatRp(group.txns.filter(t => t.type === 'expense').reduce((s, t) => s + t.amount, 0)) }}
                    </span>
                  </span>
                </div>

                <!-- Transactions for date -->
                <div class="card divide-y divide-slate-100 overflow-hidden p-0 dark:divide-zinc-800">
                  <div
                    v-for="t in group.txns"
                    :key="t.id"
                    class="flex items-center gap-3 px-4 py-3 transition hover:bg-slate-50/60 dark:hover:bg-zinc-800/40"
                  >
                    <!-- Type icon -->
                    <div class="grid h-8 w-8 shrink-0 place-items-center rounded-xl" :class="typeBg(t.type)">
                      <svg v-if="t.type === 'income'" class="h-4 w-4 text-teal-600" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
                      <svg v-else-if="t.type === 'expense'" class="h-4 w-4 text-red-500" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="M12 5v14M5 12l7 7 7-7"/></svg>
                      <svg v-else class="h-4 w-4 text-amber-500" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="M8 7h12m0 0l-4-4m4 4l-4 4M16 17H4m0 0l4 4M4 17l4-4"/></svg>
                    </div>

                    <!-- Info -->
                    <div class="min-w-0 flex-1">
                      <div class="flex flex-wrap items-center gap-x-2">
                        <span class="text-sm font-extrabold text-slate-800 dark:text-zinc-100">{{ t.category }}</span>
                        <span v-if="t.subcategory" class="text-sm font-semibold text-slate-500 dark:text-zinc-400">{{ t.subcategory }}</span>
                      </div>
                      <p class="mt-0.5 text-xs font-semibold text-slate-400 dark:text-zinc-500">
                        <span>{{ t.account }}</span>
                        <span v-if="t.description" class="ml-2 text-slate-300 dark:text-zinc-700">· {{ t.description }}</span>
                      </p>
                    </div>

                    <!-- Amount + type -->
                    <div class="shrink-0 text-right">
                      <p class="text-sm font-extrabold" :class="typeColor(t.type)">
                        {{ t.type === 'income' ? '+' : t.type === 'expense' ? '−' : '' }}{{ formatRp(t.amount) }}
                      </p>
                      <span class="rounded-full px-2 py-0.5 text-[10px] font-extrabold" :class="typePill(t.type)">{{ typeLabel(t.type) }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </template>
    </template>
  </div>
</template>
