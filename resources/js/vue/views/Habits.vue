<template>
  <div class="max-w-4xl mx-auto py-6 px-4 space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between gap-3 flex-wrap">
      <div>
        <h1 class="text-xl font-bold text-slate-900 dark:text-white">Habit Tracker</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Bangun konsistensi setiap hari</p>
      </div>
      <button @click="openForm()" class="flex items-center gap-2 px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors">
        + Habit Baru
      </button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="text-sm text-slate-400 dark:text-slate-500">Memuat...</div>

    <!-- Empty state -->
    <div v-else-if="!habits.length" class="text-center py-16 text-slate-400 dark:text-slate-500">
      <div class="text-4xl mb-3">✓</div>
      <p class="font-medium">Belum ada habit</p>
      <p class="text-sm mt-1">Mulai dengan membuat habit pertamamu</p>
    </div>

    <!-- Habits list -->
    <div v-else class="space-y-3">
      <!-- Today summary bar -->
      <div class="flex items-center gap-3 p-3 bg-slate-50 dark:bg-slate-800/50 rounded-lg text-sm">
        <span class="font-semibold text-slate-700 dark:text-slate-300">
          {{ todayDoneCount }}/{{ habits.length }} selesai hari ini
        </span>
        <div class="flex-1 h-2 bg-slate-200 dark:bg-slate-700 rounded-full overflow-hidden">
          <div class="h-2 bg-indigo-500 rounded-full transition-all" :style="{ width: (habits.length ? todayDoneCount/habits.length*100 : 0) + '%' }"></div>
        </div>
        <span class="text-slate-500">{{ today }}</span>
      </div>

      <div v-for="habit in habits" :key="habit.id" class="group bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl p-4 hover:border-indigo-300 dark:hover:border-indigo-600 transition-colors">
        <div class="flex items-start gap-3">
          <!-- Check button -->
          <button
            @click="toggleToday(habit)"
            :class="[
              'w-10 h-10 rounded-xl text-lg font-bold flex items-center justify-center flex-shrink-0 transition-all',
              habit.today_done
                ? 'text-white shadow-md'
                : 'bg-slate-100 dark:bg-slate-800 text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700'
            ]"
            :style="habit.today_done ? { background: habit.color } : {}"
            :title="habit.today_done ? 'Batalkan' : 'Tandai selesai'"
          >
            {{ habit.today_done ? '✓' : habit.icon }}
          </button>

          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="font-semibold text-slate-900 dark:text-white">{{ habit.name }}</span>
              <span v-if="habit.streak > 0" class="text-xs font-bold text-orange-500">🔥 {{ habit.streak }} hari</span>
              <span class="text-xs text-slate-400">{{ habit.total_logs }} total</span>
            </div>
            <p v-if="habit.description" class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 truncate">{{ habit.description }}</p>

            <!-- Week view (last 7 days) -->
            <div class="flex gap-1 mt-2">
              <div
                v-for="day in weekDays"
                :key="day.date"
                :class="[
                  'w-6 h-6 rounded text-center text-xs leading-6 font-medium transition-colors',
                  isLoggedOn(habit, day.date)
                    ? 'text-white'
                    : day.isToday ? 'bg-slate-200 dark:bg-slate-700 text-slate-500' : 'bg-slate-100 dark:bg-slate-800 text-slate-300'
                ]"
                :style="isLoggedOn(habit, day.date) ? { background: habit.color } : {}"
                :title="day.label"
              >
                {{ day.short }}
              </div>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
            <button @click="openForm(habit)" class="p-1.5 text-slate-400 hover:text-indigo-500 dark:hover:text-indigo-400" title="Edit">✏</button>
            <button @click="confirmDelete(habit)" class="p-1.5 text-slate-400 hover:text-red-500" title="Hapus">✕</button>
          </div>
        </div>

        <!-- Heatmap toggle -->
        <div v-if="expandedId === habit.id" class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-800">
          <div class="text-xs text-slate-500 mb-2">90 hari terakhir</div>
          <div class="flex flex-wrap gap-0.5">
            <div
              v-for="cell in getHeatmap(habit)"
              :key="cell.date"
              :class="['w-3 h-3 rounded-sm', cell.done ? '' : 'bg-slate-100 dark:bg-slate-800']"
              :style="cell.done ? { background: habit.color } : {}"
              :title="cell.date"
            ></div>
          </div>
        </div>
        <button @click="expandedId = expandedId === habit.id ? null : habit.id" class="mt-2 text-xs text-slate-400 hover:text-indigo-500">
          {{ expandedId === habit.id ? '▲ Sembunyikan heatmap' : '▼ Lihat heatmap 90 hari' }}
        </button>
      </div>
    </div>

    <!-- Form modal -->
    <div v-if="showForm" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 px-4">
      <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-xl w-full max-w-md p-6 space-y-4">
        <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ editingHabit ? 'Edit Habit' : 'Habit Baru' }}</h2>

        <div>
          <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Nama *</label>
          <input v-model="form.name" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Contoh: Olahraga 30 menit" />
        </div>

        <div>
          <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Deskripsi</label>
          <input v-model="form.description" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Opsional" />
        </div>

        <div class="flex gap-3">
          <div class="flex-1">
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Icon</label>
            <input v-model="form.icon" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="✓" maxlength="4" />
          </div>
          <div class="flex-1">
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Warna</label>
            <div class="flex gap-2 flex-wrap pt-1">
              <button
                v-for="c in colorOptions"
                :key="c"
                @click="form.color = c"
                :class="['w-6 h-6 rounded-full border-2 transition', form.color === c ? 'border-slate-900 dark:border-white scale-110' : 'border-transparent']"
                :style="{ background: c }"
              ></button>
            </div>
          </div>
        </div>

        <div>
          <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Frekuensi</label>
          <select v-model="form.frequency" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="daily">Harian</option>
            <option value="weekly">Mingguan</option>
          </select>
        </div>

        <div v-if="formError" class="text-sm text-red-500">{{ formError }}</div>

        <div class="flex justify-end gap-2 pt-2">
          <button @click="closeForm" class="px-4 py-2 text-sm text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg">Batal</button>
          <button @click="submitForm" :disabled="saving" class="px-4 py-2 text-sm font-semibold bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg disabled:opacity-50">
            {{ saving ? 'Menyimpan...' : (editingHabit ? 'Simpan' : 'Buat') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { api } from '../api'

interface Habit {
  id: number
  name: string
  description: string | null
  color: string
  icon: string
  frequency: string
  target_days: number[] | null
  active: boolean
  today_done: boolean
  streak: number
  week_dates: string[]
  heatmap: string[]
  total_logs: number
}

const habits = ref<Habit[]>([])
const loading = ref(true)
const today = ref('')
const expandedId = ref<number | null>(null)
const showForm = ref(false)
const editingHabit = ref<Habit | null>(null)
const saving = ref(false)
const formError = ref('')

const colorOptions = ['#6366f1', '#8b5cf6', '#ec4899', '#ef4444', '#f97316', '#eab308', '#22c55e', '#14b8a6', '#3b82f6', '#06b6d4']

const form = ref({ name: '', description: '', icon: '✓', color: '#6366f1', frequency: 'daily' })

const todayDoneCount = computed(() => habits.value.filter(h => h.today_done).length)

// Build last 7 days for week view
const weekDays = computed(() => {
  const days = []
  for (let i = 6; i >= 0; i--) {
    const d = new Date()
    d.setDate(d.getDate() - i)
    const dateStr = d.toISOString().split('T')[0]
    days.push({
      date: dateStr,
      short: ['S','M','T','W','T','F','S'][d.getDay()],
      label: dateStr,
      isToday: i === 0,
    })
  }
  return days
})

function isLoggedOn(habit: Habit, date: string): boolean {
  return habit.week_dates.includes(date)
}

function getHeatmap(habit: Habit) {
  const cells = []
  for (let i = 89; i >= 0; i--) {
    const d = new Date()
    d.setDate(d.getDate() - i)
    const date = d.toISOString().split('T')[0]
    cells.push({ date, done: habit.heatmap.includes(date) })
  }
  return cells
}

async function load() {
  loading.value = true
  try {
    const res = await api.get('/api/v1/habits')
    habits.value = res.data.habits
    today.value = res.data.today
  } finally {
    loading.value = false
  }
}

async function toggleToday(habit: Habit) {
  const prev = habit.today_done
  habit.today_done = !prev
  if (prev) {
    habit.streak = Math.max(0, habit.streak - 1)
    try {
      await api.delete(`/api/v1/habits/${habit.id}/log?date=${today.value}`)
    } catch {
      habit.today_done = prev
    }
  } else {
    habit.streak += 1
    habit.week_dates = [...habit.week_dates, today.value]
    try {
      await api.post(`/api/v1/habits/${habit.id}/log`, { date: today.value })
    } catch {
      habit.today_done = prev
      habit.streak -= 1
    }
  }
}

function openForm(habit?: Habit) {
  if (habit) {
    editingHabit.value = habit
    form.value = { name: habit.name, description: habit.description ?? '', icon: habit.icon, color: habit.color, frequency: habit.frequency }
  } else {
    editingHabit.value = null
    form.value = { name: '', description: '', icon: '✓', color: '#6366f1', frequency: 'daily' }
  }
  formError.value = ''
  showForm.value = true
}

function closeForm() {
  showForm.value = false
  editingHabit.value = null
}

async function submitForm() {
  if (!form.value.name.trim()) { formError.value = 'Nama wajib diisi'; return }
  saving.value = true
  formError.value = ''
  try {
    if (editingHabit.value) {
      await api.patch(`/api/v1/habits/${editingHabit.value.id}`, form.value)
    } else {
      await api.post('/api/v1/habits', form.value)
    }
    closeForm()
    await load()
  } catch (e: any) {
    formError.value = e?.response?.data?.message ?? 'Gagal menyimpan'
  } finally {
    saving.value = false
  }
}

async function confirmDelete(habit: Habit) {
  if (!confirm(`Hapus habit "${habit.name}"? Semua log akan ikut terhapus.`)) return
  await api.delete(`/api/v1/habits/${habit.id}`)
  await load()
}

onMounted(load)
</script>
