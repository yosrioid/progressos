<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue';
import { api, unwrap } from '../api';
import { confirmAction, toast } from '../feedback';

interface BillPayment {
  id: number;
  actual_amount: string | null;
  notes: string | null;
  paid_at: string;
}

interface BillItem {
  id: number;
  name: string;
  estimated_amount: string | null;
  due_day: number | null;
  category: string | null;
  notes: string | null;
  is_recurring: boolean;
  paid: boolean;
  skipped: boolean;
  payment: BillPayment | null;
}

const bills = ref<BillItem[]>([]);
const loading = ref(true);
const month = ref(new Date().toISOString().slice(0, 7));
const budget = ref<number | null>(null);
const budgetInput = ref<string>('');
const editingBudget = ref(false);

const payingId = ref<number | null>(null);
const payAmount = ref<string>('');
const payNotes = ref<string>('');
const payAmountRef = ref<HTMLInputElement | null>(null);
const paying = ref(false);

const showAddForm = ref(false);
const addForm = ref({ name: '', estimated_amount: '', due_day: '', category: '', is_recurring: true });
const adding = ref(false);

const editingId = ref<number | null>(null);
const editForm = ref({ name: '', estimated_amount: '', due_day: '', category: '' });
const editSaving = ref(false);
const editNameRef = ref<HTMLInputElement | null>(null);

function monthLabel(m: string) {
  return new Date(m + '-02').toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
}

function shiftMonth(delta: number) {
  const [y, mo] = month.value.split('-').map(Number);
  const d = new Date(y, mo - 1 + delta, 1);
  month.value = d.toISOString().slice(0, 7);
  load();
}

function formatRp(val: string | number | null | undefined): string {
  if (val == null || val === '') return '—';
  const n = typeof val === 'string' ? parseFloat(val) : val;
  if (isNaN(n)) return '—';
  return 'Rp ' + n.toLocaleString('id-ID');
}

function parseAmount(s: string): number | null {
  const clean = s.replace(/[^0-9.]/g, '');
  const n = parseFloat(clean);
  return isNaN(n) ? null : n;
}

const activeBills = computed(() => bills.value.filter((b) => !b.skipped));
const skippedBills = computed(() => bills.value.filter((b) => b.skipped));

const totalEstimated = computed(() =>
  activeBills.value.reduce((s, b) => s + (b.estimated_amount ? parseFloat(b.estimated_amount) : 0), 0),
);
const totalPaid = computed(() =>
  activeBills.value.filter((b) => b.paid).reduce((s, b) => s + (b.payment?.actual_amount ? parseFloat(b.payment.actual_amount) : 0), 0),
);
const totalUnpaid = computed(() =>
  activeBills.value.filter((b) => !b.paid).reduce((s, b) => s + (b.estimated_amount ? parseFloat(b.estimated_amount) : 0), 0),
);
const paidCount = computed(() => activeBills.value.filter((b) => b.paid).length);

async function load() {
  loading.value = true;
  try {
    const data = await api.get(`/api/v1/bills/month/${month.value}`).then(unwrap);
    bills.value = data.bills ?? [];
    budget.value = data.budget ?? null;
    budgetInput.value = budget.value ? String(budget.value) : '';
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

function cancelPay() {
  payingId.value = null;
}

async function confirmPay(bill: BillItem) {
  if (paying.value) return;
  paying.value = true;
  try {
    const data = await api.post(`/api/v1/bills/${bill.id}/pay`, {
      month: month.value,
      actual_amount: parseAmount(payAmount.value),
      notes: payNotes.value || null,
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
    const ok = await confirmAction({
      title: 'Lewati bulan ini',
      message: `"${bill.name}" tidak berlaku untuk ${monthLabel(month.value)}?`,
      confirmLabel: 'Lewati',
    });
    if (!ok) return;
    if (bill.paid) {
      await api.delete(`/api/v1/bills/${bill.id}/pay/${month.value}`);
      bill.paid = false;
      bill.payment = null;
    }
    await api.post(`/api/v1/bills/${bill.id}/skip`, { month: month.value });
    bill.skipped = true;
    payingId.value = null;
  }
}

async function saveBudget() {
  const amount = parseAmount(budgetInput.value);
  await api.post('/api/v1/bills/set-budget', { month: month.value, amount });
  budget.value = amount;
  editingBudget.value = false;
  toast({ tone: 'success', title: 'Budget disimpan', message: formatRp(amount) });
}

async function addBill() {
  if (!addForm.value.name.trim() || adding.value) return;
  adding.value = true;
  try {
    const payload: any = {
      name: addForm.value.name.trim(),
      is_recurring: addForm.value.is_recurring,
    };
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
    message: bill.is_recurring
      ? `Hapus "${bill.name}"? Tagihan ini tidak akan muncul lagi di bulan mana pun.`
      : `Hapus "${bill.name}" dari bulan ini?`,
    confirmLabel: 'Hapus',
  });
  if (!ok) return;
  await api.delete(`/api/v1/bills/${bill.id}`);
  bills.value = bills.value.filter((b) => b.id !== bill.id);
  toast({ tone: 'success', title: 'Dihapus', message: bill.name });
}

onMounted(load);
</script>

<template>
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
    <div class="card mb-4 flex items-center justify-between gap-3 p-3">
      <button class="rounded-lg p-1.5 text-slate-400 transition-colors hover:text-slate-700 dark:hover:text-zinc-200" aria-label="Bulan sebelumnya" @click="shiftMonth(-1)">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6" /></svg>
      </button>
      <span class="text-sm font-extrabold capitalize text-slate-700 dark:text-zinc-200">{{ monthLabel(month) }}</span>
      <button class="rounded-lg p-1.5 text-slate-400 transition-colors hover:text-slate-700 dark:hover:text-zinc-200" aria-label="Bulan berikutnya" @click="shiftMonth(1)">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6" /></svg>
      </button>
    </div>

    <!-- Add bill form -->
    <form v-if="showAddForm" class="card mb-4 space-y-4 p-4" @submit.prevent="addBill">
      <div class="flex items-center justify-between">
        <p class="text-xs font-extrabold uppercase text-slate-500 dark:text-zinc-400">Tagihan baru</p>
        <!-- Recurring toggle -->
        <div class="flex items-center gap-1 rounded-lg border border-slate-200 bg-slate-50 p-0.5 text-xs font-extrabold dark:border-zinc-700 dark:bg-zinc-800">
          <button
            type="button"
            class="rounded-md px-2.5 py-1 transition-colors"
            :class="addForm.is_recurring ? 'bg-white text-teal-700 shadow-sm dark:bg-zinc-700 dark:text-teal-300' : 'text-slate-400 dark:text-zinc-500'"
            @click="addForm.is_recurring = true"
          >Setiap bulan</button>
          <button
            type="button"
            class="rounded-md px-2.5 py-1 transition-colors"
            :class="!addForm.is_recurring ? 'bg-white text-amber-700 shadow-sm dark:bg-zinc-700 dark:text-amber-300' : 'text-slate-400 dark:text-zinc-500'"
            @click="addForm.is_recurring = false"
          >Bulan ini saja</button>
        </div>
      </div>
      <p v-if="!addForm.is_recurring" class="rounded-lg bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-700 dark:bg-amber-900/20 dark:text-amber-400">
        Hanya muncul di {{ monthLabel(month) }}, tidak akan muncul di bulan lain.
      </p>
      <div class="grid gap-3 sm:grid-cols-2">
        <div>
          <label class="label mb-1">Nama tagihan *</label>
          <input v-model="addForm.name" class="field" placeholder="Listrik rumah" required />
        </div>
        <div>
          <label class="label mb-1">Estimasi (Rp)</label>
          <input v-model="addForm.estimated_amount" class="field" placeholder="150.000" inputmode="numeric" />
        </div>
        <div>
          <label class="label mb-1">Tanggal jatuh tempo</label>
          <input v-model="addForm.due_day" class="field" type="number" min="1" max="31" placeholder="Tgl 1–31" />
        </div>
        <div>
          <label class="label mb-1">Kategori</label>
          <input v-model="addForm.category" class="field" placeholder="Utilitas, Subsidi, dll" />
        </div>
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
        <p class="mx-auto mt-2 max-w-xs text-sm text-slate-400 dark:text-zinc-500">Tambahkan tagihan rutin seperti listrik, internet, kiriman uang, dll. Akan muncul otomatis tiap bulan.</p>
        <button class="btn btn-primary mt-4" @click="showAddForm = true">+ Tambah Tagihan Pertama</button>
      </div>

      <template v-else>
        <!-- Bill list -->
        <div class="card mb-4 divide-y divide-slate-100 overflow-hidden p-0 dark:divide-zinc-800">
          <div v-for="bill in bills" :key="bill.id">
            <!-- Edit mode -->
            <form v-if="editingId === bill.id" class="flex flex-col gap-2 bg-slate-50/60 p-4 dark:bg-zinc-900/50" @submit.prevent="saveEdit(bill)">
              <div class="grid gap-2 sm:grid-cols-2">
                <input ref="editNameRef" v-model="editForm.name" class="field" placeholder="Nama" required />
                <input v-model="editForm.estimated_amount" class="field" placeholder="Estimasi (Rp)" inputmode="numeric" />
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
                <!-- Checkbox (disabled if skipped) -->
                <button
                  class="shrink-0 rounded-lg p-0.5 transition-colors"
                  :disabled="bill.skipped"
                  :aria-label="bill.paid ? 'Batalkan pembayaran' : 'Tandai sudah bayar'"
                  @click="startPay(bill)"
                >
                  <div
                    class="flex h-6 w-6 items-center justify-center rounded-md border-2 transition-all"
                    :class="bill.paid
                      ? 'border-teal-500 bg-teal-500'
                      : bill.skipped
                        ? 'border-slate-200 bg-slate-100 dark:border-zinc-700 dark:bg-zinc-800'
                        : 'border-slate-300 bg-white hover:border-teal-400 dark:border-zinc-600 dark:bg-zinc-800 dark:hover:border-teal-500'"
                  >
                    <svg v-if="bill.paid" class="h-3.5 w-3.5 text-white" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5" /></svg>
                    <svg v-else-if="bill.skipped" class="h-3 w-3 text-slate-400" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12" /></svg>
                  </div>
                </button>

                <!-- Bill info -->
                <div class="min-w-0 flex-1">
                  <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                    <span
                      class="text-sm font-extrabold"
                      :class="[
                        bill.paid ? 'text-slate-400 line-through dark:text-zinc-500' : 'text-slate-900 dark:text-zinc-100',
                        bill.skipped ? 'text-slate-400 line-through dark:text-zinc-600' : '',
                      ]"
                    >{{ bill.name }}</span>
                    <span v-if="bill.skipped" class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-extrabold text-slate-400 dark:bg-zinc-800 dark:text-zinc-500">Dilewati</span>
                    <span v-else-if="!bill.is_recurring" class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-extrabold text-amber-600 dark:bg-amber-900/30 dark:text-amber-400">Bulan ini</span>
                    <span v-if="bill.category && !bill.skipped" class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-extrabold text-slate-500 dark:bg-zinc-800 dark:text-zinc-400">{{ bill.category }}</span>
                    <span v-if="bill.due_day && !bill.skipped" class="text-[11px] font-semibold text-slate-400 dark:text-zinc-500">tgl {{ bill.due_day }}</span>
                  </div>
                  <p v-if="!bill.skipped" class="mt-0.5 text-xs font-semibold text-slate-400 dark:text-zinc-500">
                    <template v-if="bill.paid && bill.payment">
                      <span class="font-extrabold text-teal-600 dark:text-teal-400">{{ formatRp(bill.payment.actual_amount) }}</span>
                      <span
                        v-if="bill.payment.actual_amount && bill.estimated_amount && parseFloat(bill.payment.actual_amount) !== parseFloat(bill.estimated_amount)"
                        class="ml-1 text-slate-300 dark:text-zinc-600"
                      >(est. {{ formatRp(bill.estimated_amount) }})</span>
                    </template>
                    <template v-else>{{ formatRp(bill.estimated_amount) }}</template>
                  </p>
                </div>

                <!-- Actions -->
                <div class="flex shrink-0 items-center gap-0.5">
                  <!-- Skip/unskip: hanya untuk recurring -->
                  <button
                    v-if="bill.is_recurring"
                    class="rounded-lg p-1.5 transition-colors"
                    :class="bill.skipped
                      ? 'text-amber-500 hover:text-amber-600 dark:text-amber-400'
                      : 'text-slate-300 hover:text-slate-500 dark:text-zinc-700 dark:hover:text-zinc-400'"
                    :aria-label="bill.skipped ? 'Aktifkan kembali bulan ini' : 'Lewati bulan ini'"
                    :title="bill.skipped ? 'Aktifkan kembali' : 'Lewati bulan ini'"
                    @click="toggleSkip(bill)"
                  >
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="M13 9l3 3-3 3M6 9l3 3-3 3" /></svg>
                  </button>
                  <button
                    v-if="!bill.skipped"
                    class="rounded-lg p-1.5 text-slate-300 transition-colors hover:text-slate-600 dark:text-zinc-700 dark:hover:text-zinc-300"
                    aria-label="Edit tagihan"
                    @click="startEdit(bill)"
                  >
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5Z"/></svg>
                  </button>
                  <button
                    class="rounded-lg p-1.5 text-slate-300 transition-colors hover:text-red-400 dark:text-zinc-700 dark:hover:text-red-400"
                    aria-label="Hapus tagihan"
                    @click="deleteBill(bill)"
                  >
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
                  </button>
                </div>
              </div>

              <!-- Inline pay form -->
              <div v-if="payingId === bill.id" class="border-t border-slate-100 bg-slate-50/60 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-900/50">
                <p class="mb-2 text-xs font-extrabold text-slate-500 dark:text-zinc-400">Konfirmasi pembayaran — {{ bill.name }}</p>
                <div class="flex items-end gap-2">
                  <div class="flex-1">
                    <label class="label mb-1">Nominal aktual (Rp)</label>
                    <input
                      ref="payAmountRef"
                      v-model="payAmount"
                      class="field"
                      inputmode="numeric"
                      placeholder="0"
                      @keydown.enter.prevent="confirmPay(bill)"
                      @keydown.escape="cancelPay"
                    />
                  </div>
                  <div class="flex-1">
                    <label class="label mb-1">Catatan (opsional)</label>
                    <input v-model="payNotes" class="field" placeholder="Transfer BCA, dll" @keydown.escape="cancelPay" />
                  </div>
                </div>
                <div class="mt-2 flex gap-2">
                  <button class="btn btn-primary text-xs" :disabled="paying" @click="confirmPay(bill)">{{ paying ? 'Menyimpan…' : '✓ Tandai Bayar' }}</button>
                  <button class="btn btn-muted text-xs" @click="cancelPay">Batal</button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Skipped summary note -->
        <p v-if="skippedBills.length" class="mb-4 text-xs font-semibold text-slate-400 dark:text-zinc-600">
          {{ skippedBills.length }} tagihan dilewati bulan ini dan tidak dihitung dalam total.
        </p>

        <!-- Summary + Budget -->
        <div class="grid gap-3 sm:grid-cols-2">
          <!-- Summary card -->
          <div class="card p-4">
            <div class="mb-3 flex items-center justify-between">
              <p class="text-xs font-extrabold uppercase text-slate-500 dark:text-zinc-400">Ringkasan {{ monthLabel(month) }}</p>
              <span class="rounded-full bg-teal-50 px-2 py-0.5 text-[10px] font-extrabold text-teal-700 dark:bg-teal-900/30 dark:text-teal-400">{{ paidCount }}/{{ activeBills.length }} lunas</span>
            </div>
            <div class="mb-3 h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-zinc-800">
              <div
                class="h-full rounded-full bg-teal-500 transition-all duration-500"
                :style="{ width: activeBills.length ? (paidCount / activeBills.length * 100) + '%' : '0%' }"
              />
            </div>
            <div class="space-y-1.5">
              <div class="flex items-center justify-between text-sm">
                <span class="font-semibold text-slate-500 dark:text-zinc-400">Sudah dibayar</span>
                <span class="font-extrabold text-teal-600 dark:text-teal-400">{{ formatRp(totalPaid) }}</span>
              </div>
              <div class="flex items-center justify-between text-sm">
                <span class="font-semibold text-slate-500 dark:text-zinc-400">Belum dibayar</span>
                <span class="font-extrabold" :class="totalUnpaid > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-400'">{{ formatRp(totalUnpaid) }}</span>
              </div>
              <div class="flex items-center justify-between border-t border-slate-100 pt-1.5 text-sm dark:border-zinc-800">
                <span class="font-semibold text-slate-500 dark:text-zinc-400">Total estimasi</span>
                <span class="font-extrabold text-slate-700 dark:text-zinc-200">{{ formatRp(totalEstimated) }}</span>
              </div>
            </div>
          </div>

          <!-- Budget card -->
          <div class="card p-4">
            <div class="mb-3 flex items-center justify-between">
              <p class="text-xs font-extrabold uppercase text-slate-500 dark:text-zinc-400">Budget / Pemasukan</p>
              <button class="text-xs font-extrabold text-teal-700 hover:underline dark:text-teal-400" @click="editingBudget = !editingBudget; budgetInput = budget ? String(budget) : ''">
                {{ editingBudget ? 'Batal' : 'Ubah' }}
              </button>
            </div>
            <form v-if="editingBudget" class="flex gap-2" @submit.prevent="saveBudget">
              <input v-model="budgetInput" class="field flex-1" placeholder="Rp 5.000.000" inputmode="numeric" autofocus />
              <button class="btn btn-primary px-3 text-xs" type="submit">Simpan</button>
            </form>
            <template v-else>
              <p class="text-2xl font-extrabold text-slate-900 dark:text-zinc-100">{{ budget ? formatRp(budget) : '—' }}</p>
              <div v-if="budget" class="mt-3 space-y-1.5">
                <div class="flex items-center justify-between text-sm">
                  <span class="font-semibold text-slate-500 dark:text-zinc-400">Sisa setelah semua lunas</span>
                  <span
                    class="font-extrabold"
                    :class="(budget - totalEstimated) >= 0 ? 'text-teal-600 dark:text-teal-400' : 'text-red-600 dark:text-red-400'"
                  >{{ formatRp(budget - totalEstimated) }}</span>
                </div>
                <div class="flex items-center justify-between text-sm">
                  <span class="font-semibold text-slate-500 dark:text-zinc-400">Sisa sekarang</span>
                  <span
                    class="font-extrabold"
                    :class="(budget - totalPaid) >= 0 ? 'text-slate-700 dark:text-zinc-200' : 'text-red-600 dark:text-red-400'"
                  >{{ formatRp(budget - totalPaid) }}</span>
                </div>
              </div>
              <p v-else class="mt-1 text-sm text-slate-400 dark:text-zinc-500">Set pemasukan bulanan untuk lihat sisa budget.</p>
            </template>
          </div>
        </div>
      </template>
    </template>
  </div>
</template>
