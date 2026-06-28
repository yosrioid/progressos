<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue';
import { api, unwrap } from '../api';
import { confirmAction, toast } from '../feedback';
import PinGate from '../components/PinGate.vue';
import { usePrivacyStore } from '../stores/privacy';

const privacy = usePrivacyStore();

interface BillPayment { id: number; actual_amount: string | null; notes: string | null; paid_at: string; }
interface LastPayment { month: string; actual_amount: string | null; }
interface BillItem {
  id: number; name: string; estimated_amount: string | null; due_day: number | null;
  category: string | null; notes: string | null; is_recurring: boolean;
  paid: boolean; skipped: boolean; payment: BillPayment | null; last_payment: LastPayment | null;
}
interface HistoryEntry { month: string; actual_amount: string | null; notes: string | null; paid_at: string; }

const bills = ref<BillItem[]>([]);
const loading = ref(true);
const budget = ref<number | null>(null);
const budgetInput = ref<string>('');
const editingBudget = ref(false);

// pay
const payingId = ref<number | null>(null);
const payAmount = ref('');
const payNotes = ref('');
const payAmountRef = ref<HTMLInputElement | null>(null);
const paying = ref(false);

// add form
const showAddForm = ref(false);
const addForm = ref({ name: '', estimated_amount: '', due_day: '', category: '', is_recurring: true });
const adding = ref(false);

// edit
const editingId = ref<number | null>(null);
const editForm = ref({ name: '', estimated_amount: '', due_day: '', category: '' });
const editSaving = ref(false);
const editNameRef = ref<HTMLInputElement | null>(null);

// history
const historyId = ref<number | null>(null);
const historyData = ref<{ bill_name: string; estimated_amount: string | null; history: HistoryEntry[]; average_actual: number | null } | null>(null);
const historyLoading = ref(false);

// annual
const annualData = ref<{ year: string; total_actual: number; annual_estimate: number; monthly_breakdown: Record<string, number>; paid_months: number } | null>(null);
const annualLoading = ref(false);
const showAnnual = ref(false);

// grouping
const groupByCategory = ref(false);

function pad2(n: number): string {
  return String(n).padStart(2, '0');
}
function monthValue(date = new Date()): string {
  return `${date.getFullYear()}-${pad2(date.getMonth() + 1)}`;
}
function parseMonth(m: string): { year: number; monthIndex: number } {
  const [year, monthNumber] = m.split('-').map(Number);
  return { year, monthIndex: monthNumber - 1 };
}
function makeMonth(year: number, monthIndex: number): string {
  return `${year}-${pad2(monthIndex + 1)}`;
}

const month = ref(monthValue());
const today = new Date();
const todayMonth = monthValue(today);
const todayDay = today.getDate();

function isOverdue(bill: BillItem): boolean {
  return !bill.paid && !bill.skipped && !!bill.due_day && month.value === todayMonth && bill.due_day < todayDay;
}

function monthLabel(m: string) {
  const { year, monthIndex } = parseMonth(m);
  return new Date(year, monthIndex, 2).toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
}
function shortMonthLabel(m: string) {
  const { year, monthIndex } = parseMonth(m);
  return new Date(year, monthIndex, 2).toLocaleDateString('id-ID', { month: 'short', year: '2-digit' });
}

function shiftMonth(delta: number) {
  if (loading.value) return;
  const { year, monthIndex } = parseMonth(month.value);
  const d = new Date(year, monthIndex + delta, 1);
  month.value = makeMonth(d.getFullYear(), d.getMonth());
  historyId.value = null;
  load();
}

function formatRp(val: string | number | null | undefined): string {
  if (val == null || val === '') return '—';
  const n = typeof val === 'string' ? parseFloat(val) : val;
  return isNaN(n) ? '—' : 'Rp ' + n.toLocaleString('id-ID');
}
function parseAmount(s: string): number | null {
  const n = parseInt(s.replace(/[^0-9]/g, ''), 10);
  return isNaN(n) ? null : n;
}
function formatRpInput(s: string): string {
  const n = parseInt(s.replace(/[^0-9]/g, ''), 10);
  return isNaN(n) ? '' : n.toLocaleString('id-ID');
}
function showRp(val: string | number | null | undefined): string {
  if (privacy.hideSensitive) return '•••••';
  return formatRp(val);
}

const activeBills = computed(() => bills.value.filter((b) => !b.skipped));
const skippedBills = computed(() => bills.value.filter((b) => b.skipped));
const totalEstimated = computed(() => activeBills.value.reduce((s, b) => s + parseFloat(b.estimated_amount ?? '0'), 0));
const totalPaid = computed(() => activeBills.value.filter((b) => b.paid).reduce((s, b) => s + parseFloat(b.payment?.actual_amount ?? '0'), 0));
const totalUnpaid = computed(() => activeBills.value.filter((b) => !b.paid).reduce((s, b) => s + parseFloat(b.estimated_amount ?? '0'), 0));
const paidCount = computed(() => activeBills.value.filter((b) => b.paid).length);
const overdueCount = computed(() => activeBills.value.filter(isOverdue).length);

const groupedBills = computed(() => {
  if (!groupByCategory.value) return [{ category: null, bills: bills.value }];
  const groups: Record<string, BillItem[]> = {};
  bills.value.forEach((b) => {
    const key = b.category || 'Lainnya';
    if (!groups[key]) groups[key] = [];
    groups[key].push(b);
  });
  return Object.entries(groups).sort(([a], [b]) => a.localeCompare(b)).map(([category, items]) => ({ category, bills: items }));
});

async function load() {
  loading.value = true;
  try {
    const data = await api.get(`/api/v1/bills/month/${month.value}`).then(unwrap);
    bills.value = data.bills ?? [];
    budget.value = data.budget ?? null;
    budgetInput.value = budget.value ? budget.value.toLocaleString('id-ID') : '';
  } finally {
    loading.value = false;
  }
}

async function startPay(bill: BillItem) {
  if (bill.paid) {
    const ok = await confirmAction({ title: 'Batalkan pembayaran', message: `Batalkan pembayaran "${bill.name}"?`, confirmLabel: 'Batalkan' });
    if (!ok) return;
    await api.delete(`/api/v1/bills/${bill.id}/pay/${month.value}`);
    bill.paid = false;
    bill.payment = null;
    return;
  }
  payingId.value = bill.id;
  payAmount.value = bill.estimated_amount ? parseFloat(bill.estimated_amount).toLocaleString('id-ID') : '';
  payNotes.value = '';
  await nextTick();
  payAmountRef.value?.focus();
  payAmountRef.value?.select();
}

async function confirmPay(bill: BillItem) {
  if (paying.value) return;
  paying.value = true;
  try {
    const data = await api.post(`/api/v1/bills/${bill.id}/pay`, {
      month: month.value, actual_amount: parseAmount(payAmount.value), notes: payNotes.value || null,
    }).then(unwrap);
    bill.paid = true;
    bill.payment = data.payment;
    payingId.value = null;
  } finally {
    paying.value = false;
  }
}

async function toggleSkip(bill: BillItem) {
  if (bill.skipped) {
    await api.delete(`/api/v1/bills/${bill.id}/skip/${month.value}`);
    bill.skipped = false;
  } else {
    const ok = await confirmAction({ title: 'Lewati bulan ini', message: `"${bill.name}" tidak berlaku untuk ${monthLabel(month.value)}?`, confirmLabel: 'Lewati' });
    if (!ok) return;
    if (bill.paid) { await api.delete(`/api/v1/bills/${bill.id}/pay/${month.value}`); bill.paid = false; bill.payment = null; }
    await api.post(`/api/v1/bills/${bill.id}/skip`, { month: month.value });
    bill.skipped = true;
    payingId.value = null;
  }
}

async function toggleHistory(bill: BillItem) {
  if (historyId.value === bill.id) { historyId.value = null; return; }
  historyId.value = bill.id;
  historyData.value = null;
  historyLoading.value = true;
  try {
    historyData.value = (await api.get(`/api/v1/bills/${bill.id}/history`).then(unwrap));
  } finally {
    historyLoading.value = false;
  }
}

async function loadAnnual() {
  showAnnual.value = !showAnnual.value;
  if (!showAnnual.value || annualData.value?.year === month.value.slice(0, 4)) return;
  annualLoading.value = true;
  try {
    annualData.value = (await api.get(`/api/v1/bills/annual/${month.value.slice(0, 4)}`).then(unwrap));
  } finally {
    annualLoading.value = false;
  }
}

async function saveBudget() {
  const amount = parseAmount(budgetInput.value);
  await api.post('/api/v1/bills/set-budget', { month: month.value, amount });
  budget.value = amount;
  editingBudget.value = false;
  toast({ tone: 'success', title: 'Budget disimpan', message: showRp(amount) });
}

async function addBill() {
  if (!addForm.value.name.trim() || adding.value) return;
  adding.value = true;
  try {
    const payload: any = { name: addForm.value.name.trim(), is_recurring: addForm.value.is_recurring };
    if (!addForm.value.is_recurring) payload.month = month.value;
    if (addForm.value.estimated_amount) payload.estimated_amount = parseAmount(addForm.value.estimated_amount);
    if (addForm.value.due_day) payload.due_day = parseInt(addForm.value.due_day);
    if (addForm.value.category) payload.category = addForm.value.category.trim();
    await api.post('/api/v1/bills', payload).then(unwrap);
    addForm.value = { name: '', estimated_amount: '', due_day: '', category: '', is_recurring: true };
    showAddForm.value = false;
    await load();
  } finally {
    adding.value = false;
  }
}

async function startEdit(bill: BillItem) {
  editingId.value = bill.id;
  editForm.value = {
    name: bill.name,
    estimated_amount: bill.estimated_amount ? parseFloat(bill.estimated_amount).toLocaleString('id-ID') : '',
    due_day: bill.due_day ? String(bill.due_day) : '',
    category: bill.category ?? '',
  };
  await nextTick();
  editNameRef.value?.focus();
}

async function saveEdit(bill: BillItem) {
  if (editSaving.value) return;
  editSaving.value = true;
  try {
    await api.patch(`/api/v1/bills/${bill.id}`, {
      name: editForm.value.name.trim(),
      estimated_amount: parseAmount(editForm.value.estimated_amount),
      due_day: editForm.value.due_day ? parseInt(editForm.value.due_day) : null,
      category: editForm.value.category.trim() || null,
    });
    editingId.value = null;
    await load();
  } finally {
    editSaving.value = false;
  }
}

async function deleteBill(bill: BillItem) {
  const ok = await confirmAction({
    title: 'Hapus tagihan',
    message: bill.is_recurring ? `Hapus "${bill.name}"? Tidak akan muncul lagi di bulan mana pun.` : `Hapus "${bill.name}" dari bulan ini?`,
    confirmLabel: 'Hapus',
  });
  if (!ok) return;
  await api.delete(`/api/v1/bills/${bill.id}`);
  bills.value = bills.value.filter((b) => b.id !== bill.id);
  toast({ tone: 'success', title: 'Dihapus', message: bill.name });
}

function copySummary() {
  const lines = [`Tagihan ${monthLabel(month.value)}`];
  lines.push('─'.repeat(28));
  bills.value.filter((b) => !b.skipped).forEach((b) => {
    const icon = b.paid ? '✅' : '☐ ';
    const amt = b.paid && b.payment?.actual_amount ? formatRp(b.payment.actual_amount) : formatRp(b.estimated_amount) + (b.estimated_amount ? ' (est.)' : '');
    const due = b.due_day ? ` · tgl ${b.due_day}` : '';
    lines.push(`${icon} ${b.name}${due}  ${amt}`);
  });
  if (skippedBills.value.length) {
    lines.push('');
    skippedBills.value.forEach((b) => lines.push(`— ${b.name} (dilewati)`));
  }
  lines.push('─'.repeat(28));
  lines.push(`Sudah: ${formatRp(totalPaid.value)}  |  Sisa: ${formatRp(totalUnpaid.value)}  |  Total: ${formatRp(totalEstimated.value)}`);
  if (budget.value) lines.push(`Budget: ${formatRp(budget.value)}  →  ${formatRp(budget.value - totalPaid.value)} tersisa`);
  navigator.clipboard.writeText(lines.join('\n'));
  toast({ tone: 'success', title: 'Disalin', message: 'Ringkasan tagihan sudah di-copy ke clipboard.' });
}

const annualMax = computed(() => {
  if (!annualData.value) return 1;
  return Math.max(...Object.values(annualData.value.monthly_breakdown), 1);
});

onMounted(load);
</script>

<template>
  <PinGate>
  <div>
    <!-- Header -->
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <p class="text-xs font-extrabold uppercase text-teal-700 dark:text-teal-500">Keuangan</p>
        <h1 class="text-3xl font-extrabold tracking-tight">Tagihan Bulanan</h1>
        <p class="mt-1 text-sm font-medium text-slate-500 dark:text-zinc-500">Tagihan rutin otomatis muncul tiap bulan. Tambah tagihan khusus bulan ini jika perlu.</p>
      </div>
      <button class="btn btn-primary shrink-0" @click="showAddForm = !showAddForm">+ Tambah Tagihan</button>
    </div>

    <!-- Month navigator -->
    <div class="card mb-4 flex items-center gap-3 p-3">
      <button class="rounded-lg p-1.5 text-slate-400 transition-colors hover:text-slate-700 disabled:opacity-40 dark:hover:text-zinc-200" :disabled="loading" aria-label="Bulan sebelumnya" @click="shiftMonth(-1)">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6" /></svg>
      </button>
      <span class="flex-1 text-center text-sm font-extrabold capitalize text-slate-700 dark:text-zinc-200">{{ monthLabel(month) }}</span>
      <button class="rounded-lg p-1.5 text-slate-400 transition-colors hover:text-slate-700 disabled:opacity-40 dark:hover:text-zinc-200" :disabled="loading" aria-label="Bulan berikutnya" @click="shiftMonth(1)">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6" /></svg>
      </button>
      <!-- Tools -->
      <div class="ml-1 flex items-center gap-1 border-l border-slate-200 pl-3 dark:border-zinc-700">
        <button
          class="rounded-lg p-1.5 transition-colors"
          :class="groupByCategory ? 'text-teal-700 dark:text-teal-400' : 'text-slate-400 hover:text-slate-600 dark:text-zinc-600 dark:hover:text-zinc-400'"
          title="Group by kategori"
          aria-label="Group by kategori"
          @click="groupByCategory = !groupByCategory"
        >
          <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="M3 6h18M3 12h12M3 18h8"/></svg>
        </button>
        <button class="rounded-lg p-1.5 text-slate-400 transition-colors hover:text-slate-600 dark:text-zinc-600 dark:hover:text-zinc-400" title="Copy ringkasan" aria-label="Copy ringkasan" @click="copySummary">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="M8 16H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2M16 8h2a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-8a2 2 0 0 1-2-2v-2"/></svg>
        </button>
      </div>
    </div>

    <!-- Overdue alert -->
    <div v-if="overdueCount > 0 && !loading" class="mb-4 flex items-center gap-3 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 dark:border-red-800/40 dark:bg-red-900/10">
      <svg class="h-4 w-4 shrink-0 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="M12 9v4M12 17h.01M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/></svg>
      <p class="text-sm font-extrabold text-red-700 dark:text-red-400">{{ overdueCount }} tagihan melewati jatuh tempo dan belum dibayar</p>
    </div>

    <!-- Add bill form -->
    <form v-if="showAddForm" class="card mb-4 space-y-4 p-4" @submit.prevent="addBill">
      <div class="flex items-center justify-between">
        <p class="text-xs font-extrabold uppercase text-slate-500 dark:text-zinc-400">Tagihan baru</p>
        <div class="flex items-center gap-1 rounded-lg border border-slate-200 bg-slate-50 p-0.5 text-xs font-extrabold dark:border-zinc-700 dark:bg-zinc-800">
          <button type="button" class="rounded-md px-2.5 py-1 transition-colors" :class="addForm.is_recurring ? 'bg-white text-teal-700 shadow-sm dark:bg-zinc-700 dark:text-teal-300' : 'text-slate-400 dark:text-zinc-500'" @click="addForm.is_recurring = true">Setiap bulan</button>
          <button type="button" class="rounded-md px-2.5 py-1 transition-colors" :class="!addForm.is_recurring ? 'bg-white text-amber-700 shadow-sm dark:bg-zinc-700 dark:text-amber-300' : 'text-slate-400 dark:text-zinc-500'" @click="addForm.is_recurring = false">Bulan ini saja</button>
        </div>
      </div>
      <p v-if="!addForm.is_recurring" class="rounded-lg bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-700 dark:bg-amber-900/20 dark:text-amber-400">
        Hanya muncul di {{ monthLabel(month) }}, tidak akan muncul di bulan lain.
      </p>
      <div class="grid gap-3 sm:grid-cols-2">
        <div><label class="label mb-1">Nama tagihan *</label><input v-model="addForm.name" class="field" placeholder="Listrik rumah" required /></div>
        <div><label class="label mb-1">Estimasi (Rp)</label><input v-model="addForm.estimated_amount" class="field" placeholder="150.000" inputmode="numeric" @blur="addForm.estimated_amount = formatRpInput(addForm.estimated_amount)" /></div>
        <div><label class="label mb-1">Tanggal jatuh tempo</label><input v-model="addForm.due_day" class="field" type="number" min="1" max="31" placeholder="Tgl 1–31" /></div>
        <div><label class="label mb-1">Kategori</label><input v-model="addForm.category" class="field" placeholder="Utilitas, Langganan, dll" /></div>
      </div>
      <div class="flex gap-2">
        <button class="btn btn-primary" :disabled="adding" type="submit">{{ adding ? 'Menyimpan…' : 'Simpan' }}</button>
        <button class="btn btn-muted" type="button" @click="showAddForm = false">Batal</button>
      </div>
    </form>

    <!-- Skeleton -->
    <div v-if="loading" class="space-y-2">
      <div v-for="i in 5" :key="i" class="skeleton h-16 rounded-xl" />
    </div>

    <template v-else>
      <!-- Empty state -->
      <div v-if="!bills.length" class="card p-12 text-center">
        <div class="mx-auto mb-4 grid h-12 w-12 place-items-center rounded-xl bg-teal-50 dark:bg-teal-900/20">
          <svg class="h-6 w-6 text-teal-700 dark:text-teal-400" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2M9 12h6M9 16h4"/></svg>
        </div>
        <h2 class="text-base font-extrabold text-slate-900 dark:text-zinc-100">Belum ada tagihan</h2>
        <p class="mx-auto mt-2 max-w-xs text-sm text-slate-400 dark:text-zinc-500">Tambahkan tagihan rutin seperti listrik, internet, kiriman uang. Akan muncul otomatis tiap bulan.</p>
        <button class="btn btn-primary mt-4" @click="showAddForm = true">+ Tambah Tagihan Pertama</button>
      </div>

      <template v-else>
        <!-- Bill list (grouped or flat) -->
        <div class="mb-4 space-y-3">
          <div v-for="group in groupedBills" :key="group.category ?? 'all'">
            <p v-if="groupByCategory && group.category" class="mb-1.5 px-1 text-[11px] font-extrabold uppercase tracking-wider text-slate-400 dark:text-zinc-500">{{ group.category }}</p>
            <div class="card divide-y divide-slate-100 overflow-hidden p-0 dark:divide-zinc-800">
              <div v-for="bill in group.bills" :key="bill.id">
                <!-- Edit mode -->
                <form v-if="editingId === bill.id" class="flex flex-col gap-2 bg-slate-50/60 p-4 dark:bg-zinc-900/50" @submit.prevent="saveEdit(bill)">
                  <div class="grid gap-2 sm:grid-cols-2">
                    <input ref="editNameRef" v-model="editForm.name" class="field" placeholder="Nama" required />
                    <input v-model="editForm.estimated_amount" class="field" placeholder="Estimasi (Rp)" inputmode="numeric" @blur="editForm.estimated_amount = formatRpInput(editForm.estimated_amount)" />
                    <input v-model="editForm.due_day" class="field" type="number" min="1" max="31" placeholder="Tgl jatuh tempo" />
                    <input v-model="editForm.category" class="field" placeholder="Kategori" />
                  </div>
                  <div class="flex gap-2">
                    <button class="btn btn-primary text-xs" :disabled="editSaving" type="submit">{{ editSaving ? 'Menyimpan…' : 'Simpan' }}</button>
                    <button class="btn btn-muted text-xs" type="button" @click="editingId = null">Batal</button>
                  </div>
                </form>

                <!-- Normal row -->
                <div v-else :class="bill.skipped ? 'opacity-50' : ''">
                  <div class="flex items-center gap-3 px-4 py-3.5">
                    <!-- Checkbox -->
                    <button
                      class="shrink-0 rounded-lg p-0.5 transition-colors"
                      :disabled="bill.skipped"
                      :aria-label="bill.paid ? 'Batalkan pembayaran' : 'Tandai sudah bayar'"
                      @click="startPay(bill)"
                    >
                      <div
                        class="flex h-6 w-6 items-center justify-center rounded-md border-2 transition-all"
                        :class="bill.paid ? 'border-teal-500 bg-teal-500'
                          : bill.skipped ? 'border-slate-200 bg-slate-100 dark:border-zinc-700 dark:bg-zinc-800'
                          : isOverdue(bill) ? 'border-red-300 bg-white hover:border-red-400 dark:border-red-700 dark:bg-zinc-800'
                          : 'border-slate-300 bg-white hover:border-teal-400 dark:border-zinc-600 dark:bg-zinc-800 dark:hover:border-teal-500'"
                      >
                        <svg v-if="bill.paid" class="h-3.5 w-3.5 text-white" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5" /></svg>
                        <svg v-else-if="bill.skipped" class="h-3 w-3 text-slate-400" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12" /></svg>
                      </div>
                    </button>

                    <!-- Bill info -->
                    <div class="min-w-0 flex-1">
                      <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                        <!-- Clickable name → history -->
                        <button
                          class="text-sm font-extrabold transition-colors"
                          :class="bill.paid ? 'text-slate-400 line-through dark:text-zinc-500' : bill.skipped ? 'text-slate-400 line-through dark:text-zinc-600' : 'text-slate-900 hover:text-teal-700 dark:text-zinc-100 dark:hover:text-teal-400'"
                          :title="bill.is_recurring ? 'Lihat history pembayaran' : undefined"
                          @click="bill.is_recurring && toggleHistory(bill)"
                        >{{ bill.name }}</button>
                        <span v-if="bill.skipped" class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-extrabold text-slate-400 dark:bg-zinc-800 dark:text-zinc-500">Dilewati</span>
                        <span v-else-if="!bill.is_recurring" class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-extrabold text-amber-600 dark:bg-amber-900/30 dark:text-amber-400">Bulan ini</span>
                        <span v-if="isOverdue(bill)" class="rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-extrabold text-red-600 dark:bg-red-900/30 dark:text-red-400">Terlambat {{ todayDay - bill.due_day! }} hari</span>
                        <span v-if="bill.category && !bill.skipped" class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-extrabold text-slate-500 dark:bg-zinc-800 dark:text-zinc-400">{{ bill.category }}</span>
                        <span v-if="bill.due_day && !bill.skipped && !isOverdue(bill)" class="text-[11px] font-semibold text-slate-400 dark:text-zinc-500">tgl {{ bill.due_day }}</span>
                      </div>
                      <p v-if="!bill.skipped" class="mt-0.5 text-xs font-semibold text-slate-400 dark:text-zinc-500">
                        <template v-if="bill.paid && bill.payment">
                          <span class="font-extrabold text-teal-600 dark:text-teal-400">{{ showRp(bill.payment.actual_amount) }}</span>
                          <span v-if="bill.payment.actual_amount && bill.estimated_amount && parseFloat(bill.payment.actual_amount) !== parseFloat(bill.estimated_amount)" class="ml-1 text-slate-300 dark:text-zinc-600">(est. {{ showRp(bill.estimated_amount) }})</span>
                        </template>
                        <template v-else>
                          {{ showRp(bill.estimated_amount) }}
                          <span v-if="bill.last_payment?.actual_amount" class="ml-2 text-slate-300 dark:text-zinc-700">· bln lalu {{ showRp(bill.last_payment.actual_amount) }}</span>
                        </template>
                      </p>
                    </div>

                    <!-- Actions -->
                    <div class="flex shrink-0 items-center gap-0.5">
                      <button v-if="bill.is_recurring" class="rounded-lg p-1.5 transition-colors" :class="bill.skipped ? 'text-amber-500 hover:text-amber-600 dark:text-amber-400' : 'text-slate-300 hover:text-slate-500 dark:text-zinc-700 dark:hover:text-zinc-400'" :aria-label="bill.skipped ? 'Aktifkan kembali' : 'Lewati bulan ini'" :title="bill.skipped ? 'Aktifkan kembali' : 'Lewati bulan ini'" @click="toggleSkip(bill)">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="M13 9l3 3-3 3M6 9l3 3-3 3" /></svg>
                      </button>
                      <button v-if="!bill.skipped" class="btn-icon-edit" aria-label="Edit tagihan" @click="startEdit(bill)">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5Z"/></svg>
                      </button>
                      <button class="btn-icon-delete" aria-label="Hapus tagihan" @click="deleteBill(bill)">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0 1 16.138 21H7.862a2 2 0 0 1-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v3M4 7h16"/></svg>
                      </button>
                    </div>
                  </div>

                  <!-- Pay form -->
                  <div v-if="payingId === bill.id" class="border-t border-slate-100 bg-slate-50/60 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-900/50">
                    <p class="mb-2 text-xs font-extrabold text-slate-500 dark:text-zinc-400">Konfirmasi pembayaran — {{ bill.name }}</p>
                    <p v-if="bill.last_payment?.actual_amount" class="mb-2 text-xs text-slate-400 dark:text-zinc-500">
                      Bulan lalu: <span class="font-extrabold text-slate-600 dark:text-zinc-300">{{ showRp(bill.last_payment.actual_amount) }}</span>
                    </p>
                    <div class="flex items-end gap-2">
                      <div class="flex-1">
                        <label class="label mb-1">Nominal aktual (Rp)</label>
                        <input ref="payAmountRef" v-model="payAmount" class="field" inputmode="numeric" placeholder="0" @keydown.enter.prevent="confirmPay(bill)" @keydown.escape="payingId = null" @blur="payAmount = formatRpInput(payAmount)" />
                      </div>
                      <div class="flex-1">
                        <label class="label mb-1">Catatan (opsional)</label>
                        <input v-model="payNotes" class="field" placeholder="Transfer BCA, dll" @keydown.escape="payingId = null" />
                      </div>
                    </div>
                    <div class="mt-2 flex gap-2">
                      <button class="btn btn-primary text-xs" :disabled="paying" @click="confirmPay(bill)">{{ paying ? 'Menyimpan…' : '✓ Tandai Bayar' }}</button>
                      <button class="btn btn-muted text-xs" @click="payingId = null">Batal</button>
                    </div>
                  </div>

                  <!-- History panel -->
                  <div v-if="historyId === bill.id" class="border-t border-slate-100 bg-slate-50/40 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-900/30">
                    <div v-if="historyLoading" class="space-y-1.5 py-1">
                      <div v-for="i in 3" :key="i" class="skeleton h-6 rounded-lg" />
                    </div>
                    <template v-else-if="historyData">
                      <div class="mb-2 flex items-center justify-between">
                        <p class="text-xs font-extrabold text-slate-500 dark:text-zinc-400">History 12 bulan terakhir</p>
                        <span v-if="historyData.average_actual" class="text-xs font-extrabold text-teal-600 dark:text-teal-400">Rata-rata: {{ showRp(historyData.average_actual) }}</span>
                      </div>
                      <div v-if="!historyData.history.length" class="text-xs text-slate-400">Belum ada history pembayaran.</div>
                      <div v-else class="grid gap-1">
                        <div v-for="h in historyData.history" :key="h.month" class="flex items-center justify-between rounded-lg px-2 py-1.5 text-xs hover:bg-slate-100 dark:hover:bg-zinc-800">
                          <span class="font-semibold capitalize text-slate-500 dark:text-zinc-400">{{ shortMonthLabel(h.month) }}</span>
                          <div class="flex items-center gap-3">
                            <span v-if="h.notes" class="max-w-[120px] truncate text-slate-400 dark:text-zinc-600">{{ h.notes }}</span>
                            <span class="font-extrabold text-slate-700 dark:text-zinc-200">{{ showRp(h.actual_amount) }}</span>
                          </div>
                        </div>
                      </div>
                    </template>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Skipped note -->
        <p v-if="skippedBills.length" class="mb-4 text-xs font-semibold text-slate-400 dark:text-zinc-600">
          {{ skippedBills.length }} tagihan dilewati bulan ini — tidak dihitung dalam total.
        </p>

        <!-- Summary + Budget -->
        <div class="mb-4 grid gap-3 sm:grid-cols-2">
          <div class="card p-4">
            <div class="mb-3 flex items-center justify-between">
              <p class="text-xs font-extrabold uppercase text-slate-500 dark:text-zinc-400">Ringkasan {{ monthLabel(month) }}</p>
              <span class="rounded-full bg-teal-50 px-2 py-0.5 text-[10px] font-extrabold text-teal-700 dark:bg-teal-900/30 dark:text-teal-400">{{ paidCount }}/{{ activeBills.length }} lunas</span>
            </div>
            <div class="mb-3 h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-zinc-800">
              <div class="h-full rounded-full bg-teal-500 transition-all duration-500" :style="{ width: activeBills.length ? (paidCount / activeBills.length * 100) + '%' : '0%' }" />
            </div>
            <div class="space-y-1.5">
              <div class="flex justify-between text-sm"><span class="font-semibold text-slate-500 dark:text-zinc-400">Sudah dibayar</span><span class="font-extrabold text-teal-600 dark:text-teal-400">{{ showRp(totalPaid) }}</span></div>
              <div class="flex justify-between text-sm"><span class="font-semibold text-slate-500 dark:text-zinc-400">Belum dibayar</span><span class="font-extrabold" :class="totalUnpaid > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-400'">{{ showRp(totalUnpaid) }}</span></div>
              <div class="flex justify-between border-t border-slate-100 pt-1.5 text-sm dark:border-zinc-800"><span class="font-semibold text-slate-500 dark:text-zinc-400">Total estimasi</span><span class="font-extrabold text-slate-700 dark:text-zinc-200">{{ showRp(totalEstimated) }}</span></div>
            </div>
          </div>

          <div class="card p-4">
            <div class="mb-3 flex items-center justify-between">
              <p class="text-xs font-extrabold uppercase text-slate-500 dark:text-zinc-400">Budget / Pemasukan</p>
              <button class="text-xs font-extrabold text-teal-700 hover:underline dark:text-teal-400" @click="editingBudget = !editingBudget; budgetInput = budget ? budget.toLocaleString('id-ID') : ''">{{ editingBudget ? 'Batal' : 'Ubah' }}</button>
            </div>
            <form v-if="editingBudget" class="flex gap-2" @submit.prevent="saveBudget">
              <input v-model="budgetInput" class="field flex-1" placeholder="Rp 5.000.000" inputmode="numeric" autofocus @blur="budgetInput = formatRpInput(budgetInput)" />
              <button class="btn btn-primary px-3 text-xs" type="submit">Simpan</button>
            </form>
            <template v-else>
              <p class="text-2xl font-extrabold text-slate-900 dark:text-zinc-100">{{ budget ? showRp(budget) : '—' }}</p>
              <div v-if="budget" class="mt-3 space-y-1.5">
                <div class="flex justify-between text-sm"><span class="font-semibold text-slate-500 dark:text-zinc-400">Sisa setelah semua lunas</span><span class="font-extrabold" :class="(budget - totalEstimated) >= 0 ? 'text-teal-600 dark:text-teal-400' : 'text-red-600 dark:text-red-400'">{{ showRp(budget - totalEstimated) }}</span></div>
                <div class="flex justify-between text-sm"><span class="font-semibold text-slate-500 dark:text-zinc-400">Sisa sekarang</span><span class="font-extrabold" :class="(budget - totalPaid) >= 0 ? 'text-slate-700 dark:text-zinc-200' : 'text-red-600 dark:text-red-400'">{{ showRp(budget - totalPaid) }}</span></div>
              </div>
              <p v-else class="mt-1 text-sm text-slate-400 dark:text-zinc-500">Set pemasukan bulanan untuk lihat sisa budget.</p>
            </template>
          </div>
        </div>

        <!-- Annual summary toggle -->
        <button class="mb-3 flex w-full items-center justify-between rounded-2xl border border-slate-200 bg-slate-50/70 px-4 py-3 text-sm font-extrabold text-slate-600 transition hover:border-teal-200 hover:bg-teal-50/30 dark:border-zinc-800 dark:bg-zinc-900/40 dark:text-zinc-400 dark:hover:border-teal-700" @click="loadAnnual">
          <span>Ringkasan tahunan {{ month.slice(0, 4) }}</span>
          <svg class="h-4 w-4 transition-transform" :class="showAnnual ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6" /></svg>
        </button>

        <div v-if="showAnnual" class="card mb-4 p-4">
          <div v-if="annualLoading" class="space-y-2">
            <div v-for="i in 3" :key="i" class="skeleton h-8 rounded-xl" />
          </div>
          <template v-else-if="annualData">
            <div class="mb-4 grid gap-3 sm:grid-cols-3">
              <div class="rounded-xl bg-slate-50 p-3 dark:bg-zinc-800/60">
                <p class="label mb-1">Total aktual dibayar</p>
                <p class="text-xl font-extrabold text-teal-700 dark:text-teal-400">{{ showRp(annualData.total_actual) }}</p>
                <p class="mt-0.5 text-xs font-semibold text-slate-400">{{ annualData.paid_months }} bulan tercatat</p>
              </div>
              <div class="rounded-xl bg-slate-50 p-3 dark:bg-zinc-800/60">
                <p class="label mb-1">Estimasi setahun</p>
                <p class="text-xl font-extrabold text-slate-700 dark:text-zinc-200">{{ showRp(annualData.annual_estimate) }}</p>
                <p class="mt-0.5 text-xs font-semibold text-slate-400">dari tagihan recurring × 12</p>
              </div>
              <div class="rounded-xl bg-slate-50 p-3 dark:bg-zinc-800/60">
                <p class="label mb-1">Rata-rata per bulan</p>
                <p class="text-xl font-extrabold text-slate-700 dark:text-zinc-200">{{ annualData.paid_months ? showRp(Math.round(annualData.total_actual / annualData.paid_months)) : '—' }}</p>
                <p class="mt-0.5 text-xs font-semibold text-slate-400">actual yang tercatat</p>
              </div>
            </div>
            <!-- Monthly bar chart -->
            <div v-if="Object.keys(annualData.monthly_breakdown).length">
              <p class="label mb-3">Per bulan ({{ month.slice(0, 4) }})</p>
              <div class="flex h-28 items-end gap-1.5">
                <div
                  v-for="(amount, m) in annualData.monthly_breakdown"
                  :key="m"
                  class="flex flex-1 flex-col items-center gap-1"
                >
                  <span class="text-[10px] font-extrabold text-teal-700 dark:text-teal-400">{{ privacy.hideSensitive ? '•••' : formatRp(amount).replace('Rp ', '') }}</span>
                  <div class="w-full rounded-t-md bg-teal-500 transition-all" :style="{ height: Math.max(4, (amount / annualMax) * 72) + 'px' }" />
                  <span class="text-[10px] font-semibold capitalize text-slate-400 dark:text-zinc-500">{{ shortMonthLabel(m) }}</span>
                </div>
              </div>
            </div>
          </template>
        </div>
      </template>
    </template>
  </div>
  </PinGate>
</template>
