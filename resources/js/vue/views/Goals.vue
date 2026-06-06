<template>
  <div class="max-w-5xl mx-auto py-6 px-4 space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between gap-3 flex-wrap">
      <div>
        <h1 class="text-xl font-bold text-slate-900 dark:text-white">Goals & OKR</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Tetapkan tujuan dan lacak key results</p>
      </div>
      <div class="flex items-center gap-2">
        <select v-model="filterStatus" class="text-sm border border-slate-300 dark:border-slate-600 rounded-lg px-2 py-1.5 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 focus:outline-none">
          <option value="">Semua status</option>
          <option value="active">Active</option>
          <option value="draft">Draft</option>
          <option value="completed">Completed</option>
          <option value="abandoned">Abandoned</option>
        </select>
        <button @click="openGoalForm()" class="flex items-center gap-2 px-3 py-1.5 bg-violet-600 hover:bg-violet-700 text-white text-sm font-medium rounded-lg transition-colors">
          + Goal Baru
        </button>
      </div>
    </div>

    <div v-if="loading" class="text-sm text-slate-400">Memuat...</div>

    <div v-else-if="!filteredGoals.length" class="text-center py-16 text-slate-400 dark:text-slate-500">
      <div class="text-4xl mb-3">🎯</div>
      <p class="font-medium">Belum ada goal</p>
    </div>

    <!-- Goals list -->
    <div v-else class="space-y-4">
      <div
        v-for="goal in filteredGoals"
        :key="goal.id"
        class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden"
      >
        <!-- Goal header -->
        <div class="flex items-start gap-3 p-4 cursor-pointer" @click="toggleExpand(goal.id)">
          <div class="w-3 h-3 rounded-full mt-1 flex-shrink-0" :style="{ background: goal.color }"></div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="font-semibold text-slate-900 dark:text-white">{{ goal.title }}</span>
              <span :class="['text-xs font-medium px-2 py-0.5 rounded-full', statusClass(goal.status)]">{{ goal.status }}</span>
              <span v-if="goal.period_label" class="text-xs text-slate-500 dark:text-slate-400">{{ goal.period_label }}</span>
            </div>
            <p v-if="goal.description" class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 truncate">{{ goal.description }}</p>
            <!-- Overall progress bar -->
            <div class="flex items-center gap-2 mt-2">
              <div class="flex-1 h-2 bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden">
                <div class="h-2 rounded-full transition-all" :style="{ width: goal.progress + '%', background: goal.color }"></div>
              </div>
              <span class="text-xs font-semibold text-slate-600 dark:text-slate-400 w-10 text-right">{{ goal.progress }}%</span>
            </div>
          </div>
          <div class="flex items-center gap-1">
            <button @click.stop="openGoalForm(goal)" class="p-1.5 text-slate-400 hover:text-violet-500" title="Edit">✏</button>
            <button @click.stop="deleteGoal(goal)" class="p-1.5 text-slate-400 hover:text-red-500" title="Hapus">✕</button>
            <span class="text-slate-400 text-sm ml-1">{{ expandedGoalId === goal.id ? '▲' : '▼' }}</span>
          </div>
        </div>

        <!-- Key Results (expanded) -->
        <div v-if="expandedGoalId === goal.id" class="border-t border-slate-100 dark:border-slate-800 p-4 space-y-3">
          <div v-if="!goal.key_results.length" class="text-sm text-slate-400">Belum ada Key Result</div>

          <div v-for="kr in goal.key_results" :key="kr.id" class="flex items-start gap-3">
            <button
              @click="toggleKrDone(goal, kr)"
              :class="['w-5 h-5 rounded border-2 flex-shrink-0 mt-0.5 flex items-center justify-center text-xs font-bold transition-colors',
                kr.status === 'done' ? 'bg-green-500 border-green-500 text-white' : 'border-slate-300 dark:border-slate-600 hover:border-green-400']"
            >{{ kr.status === 'done' ? '✓' : '' }}</button>
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <span :class="['text-sm font-medium', kr.status === 'done' ? 'line-through text-slate-400' : 'text-slate-800 dark:text-slate-200']">{{ kr.title }}</span>
                <span class="text-xs text-slate-500">{{ kr.current_value }}{{ kr.unit ? ' ' + kr.unit : '' }} / {{ kr.target_value }}{{ kr.unit ? ' ' + kr.unit : '' }}</span>
              </div>
              <div class="flex items-center gap-2 mt-1">
                <div class="flex-1 h-1.5 bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden max-w-xs">
                  <div class="h-1.5 bg-green-500 rounded-full" :style="{ width: kr.progress + '%' }"></div>
                </div>
                <span class="text-xs text-slate-400">{{ kr.progress }}%</span>
              </div>
            </div>
            <div class="flex items-center gap-1">
              <button @click="openKrForm(goal, kr)" class="p-1 text-slate-400 hover:text-violet-500 text-xs">✏</button>
              <button @click="deleteKr(goal, kr)" class="p-1 text-slate-400 hover:text-red-500 text-xs">✕</button>
            </div>
          </div>

          <button @click="openKrForm(goal)" class="text-xs text-violet-600 dark:text-violet-400 hover:underline mt-1">+ Tambah Key Result</button>
        </div>
      </div>
    </div>

    <!-- Goal form modal -->
    <div v-if="showGoalForm" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 px-4">
      <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-xl w-full max-w-lg p-6 space-y-4">
        <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ editingGoal ? 'Edit Goal' : 'Goal Baru' }}</h2>
        <div>
          <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Judul *</label>
          <input v-model="goalForm.title" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-violet-500" />
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Deskripsi</label>
          <textarea v-model="goalForm.description" rows="2" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-violet-500 resize-none"></textarea>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Period</label>
            <input v-model="goalForm.period_label" placeholder="Q2 2026" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-violet-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Status</label>
            <select v-model="goalForm.status" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-violet-500">
              <option value="draft">Draft</option>
              <option value="active">Active</option>
              <option value="completed">Completed</option>
              <option value="abandoned">Abandoned</option>
            </select>
          </div>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Mulai</label>
            <input type="date" v-model="goalForm.start_date" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-violet-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Selesai</label>
            <input type="date" v-model="goalForm.end_date" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-violet-500" />
          </div>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Warna</label>
          <div class="flex gap-2 flex-wrap">
            <button v-for="c in goalColors" :key="c" @click="goalForm.color = c" :class="['w-6 h-6 rounded-full border-2 transition', goalForm.color === c ? 'border-slate-900 dark:border-white scale-110' : 'border-transparent']" :style="{ background: c }"></button>
          </div>
        </div>
        <div v-if="goalFormError" class="text-sm text-red-500">{{ goalFormError }}</div>
        <div class="flex justify-end gap-2 pt-2">
          <button @click="closeGoalForm" class="px-4 py-2 text-sm text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg">Batal</button>
          <button @click="submitGoalForm" :disabled="goalSaving" class="px-4 py-2 text-sm font-semibold bg-violet-600 hover:bg-violet-700 text-white rounded-lg disabled:opacity-50">
            {{ goalSaving ? 'Menyimpan...' : (editingGoal ? 'Simpan' : 'Buat') }}
          </button>
        </div>
      </div>
    </div>

    <!-- KR form modal -->
    <div v-if="showKrForm" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 px-4">
      <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-xl w-full max-w-md p-6 space-y-4">
        <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ editingKr ? 'Edit Key Result' : 'Key Result Baru' }}</h2>
        <div>
          <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Judul *</label>
          <input v-model="krForm.title" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-violet-500" />
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Tipe Metrik</label>
            <select v-model="krForm.metric_type" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-violet-500">
              <option value="percentage">Persentase (%)</option>
              <option value="number">Angka</option>
              <option value="boolean">Ya/Tidak</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Satuan</label>
            <input v-model="krForm.unit" placeholder="jam, task, ..." class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-violet-500" />
          </div>
        </div>
        <div v-if="krForm.metric_type !== 'boolean'" class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Nilai Saat Ini</label>
            <input type="number" v-model.number="krForm.current_value" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-violet-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Target</label>
            <input type="number" v-model.number="krForm.target_value" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-violet-500" />
          </div>
        </div>
        <div v-if="krForm.metric_type === 'boolean'" class="flex items-center gap-2">
          <input type="checkbox" id="bool_done" v-model="krBoolDone" class="w-4 h-4" />
          <label for="bool_done" class="text-sm text-slate-700 dark:text-slate-300">Sudah selesai</label>
        </div>
        <div v-if="krFormError" class="text-sm text-red-500">{{ krFormError }}</div>
        <div class="flex justify-end gap-2 pt-2">
          <button @click="closeKrForm" class="px-4 py-2 text-sm text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg">Batal</button>
          <button @click="submitKrForm" :disabled="krSaving" class="px-4 py-2 text-sm font-semibold bg-violet-600 hover:bg-violet-700 text-white rounded-lg disabled:opacity-50">
            {{ krSaving ? 'Menyimpan...' : (editingKr ? 'Simpan' : 'Tambah') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { api } from '../api'

interface KeyResult { id: number; title: string; metric_type: string; current_value: number; target_value: number; unit: string | null; notes: string | null; status: string; order: number; progress: number }
interface Goal { id: number; title: string; description: string | null; period_label: string | null; start_date: string | null; end_date: string | null; status: string; color: string; progress: number; key_results: KeyResult[] }

const goals = ref<Goal[]>([])
const loading = ref(true)
const filterStatus = ref('')
const expandedGoalId = ref<number | null>(null)

const showGoalForm = ref(false)
const editingGoal = ref<Goal | null>(null)
const goalSaving = ref(false)
const goalFormError = ref('')
const goalForm = ref({ title: '', description: '', period_label: '', status: 'active', start_date: '', end_date: '', color: '#8b5cf6' })
const goalColors = ['#8b5cf6', '#6366f1', '#3b82f6', '#06b6d4', '#22c55e', '#eab308', '#f97316', '#ef4444', '#ec4899']

const showKrForm = ref(false)
const editingKr = ref<KeyResult | null>(null)
const activeGoalForKr = ref<Goal | null>(null)
const krSaving = ref(false)
const krFormError = ref('')
const krBoolDone = ref(false)
const krForm = ref({ title: '', metric_type: 'percentage', current_value: 0, target_value: 100, unit: '' })

const filteredGoals = computed(() => filterStatus.value ? goals.value.filter(g => g.status === filterStatus.value) : goals.value)

function statusClass(status: string) {
  return { active: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400', draft: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400', completed: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400', abandoned: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' }[status] ?? ''
}

function toggleExpand(id: number) {
  expandedGoalId.value = expandedGoalId.value === id ? null : id
}

async function load() {
  loading.value = true
  try {
    const res = await api.get('/goals')
    goals.value = res.data.goals
  } finally {
    loading.value = false
  }
}

function openGoalForm(goal?: Goal) {
  editingGoal.value = goal ?? null
  goalForm.value = goal
    ? { title: goal.title, description: goal.description ?? '', period_label: goal.period_label ?? '', status: goal.status, start_date: goal.start_date ?? '', end_date: goal.end_date ?? '', color: goal.color }
    : { title: '', description: '', period_label: '', status: 'active', start_date: '', end_date: '', color: '#8b5cf6' }
  goalFormError.value = ''
  showGoalForm.value = true
}

function closeGoalForm() { showGoalForm.value = false; editingGoal.value = null }

async function submitGoalForm() {
  if (!goalForm.value.title.trim()) { goalFormError.value = 'Judul wajib diisi'; return }
  goalSaving.value = true; goalFormError.value = ''
  try {
    if (editingGoal.value) {
      await api.patch(`/goals/${editingGoal.value.id}`, goalForm.value)
    } else {
      await api.post('/goals', goalForm.value)
    }
    closeGoalForm(); await load()
  } catch (e: any) { goalFormError.value = e?.response?.data?.message ?? 'Gagal' }
  finally { goalSaving.value = false }
}

async function deleteGoal(goal: Goal) {
  if (!confirm(`Hapus goal "${goal.title}"?`)) return
  await api.delete(`/goals/${goal.id}`); await load()
}

function openKrForm(goal: Goal, kr?: KeyResult) {
  activeGoalForKr.value = goal; editingKr.value = kr ?? null
  krBoolDone.value = kr?.status === 'done'
  krForm.value = kr
    ? { title: kr.title, metric_type: kr.metric_type, current_value: kr.current_value, target_value: kr.target_value, unit: kr.unit ?? '' }
    : { title: '', metric_type: 'percentage', current_value: 0, target_value: 100, unit: '' }
  krFormError.value = ''; showKrForm.value = true
}

function closeKrForm() { showKrForm.value = false; editingKr.value = null; activeGoalForKr.value = null }

async function submitKrForm() {
  if (!krForm.value.title.trim()) { krFormError.value = 'Judul wajib diisi'; return }
  krSaving.value = true; krFormError.value = ''
  const payload: any = { ...krForm.value }
  if (payload.metric_type === 'boolean') { payload.current_value = krBoolDone.value ? 1 : 0; payload.target_value = 1 }
  try {
    if (editingKr.value) {
      await api.patch(`/goals/${activeGoalForKr.value!.id}/key-results/${editingKr.value.id}`, payload)
    } else {
      await api.post(`/goals/${activeGoalForKr.value!.id}/key-results`, payload)
    }
    closeKrForm(); await load()
    expandedGoalId.value = activeGoalForKr.value?.id ?? null
  } catch (e: any) { krFormError.value = e?.response?.data?.message ?? 'Gagal' }
  finally { krSaving.value = false }
}

async function toggleKrDone(goal: Goal, kr: KeyResult) {
  const newStatus = kr.status === 'done' ? 'active' : 'done'
  await api.patch(`/goals/${goal.id}/key-results/${kr.id}`, { status: newStatus })
  await load(); expandedGoalId.value = goal.id
}

async function deleteKr(goal: Goal, kr: KeyResult) {
  if (!confirm('Hapus key result ini?')) return
  await api.delete(`/goals/${goal.id}/key-results/${kr.id}`)
  await load(); expandedGoalId.value = goal.id
}

onMounted(load)
</script>
