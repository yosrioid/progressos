<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { api } from '../api';
import { formatDate } from '../format';
import { toast } from '../feedback';

const router = useRouter();
const board = ref<Record<string, any[]>>({ todo: [], in_progress: [], blocked: [], done: [] });
const loading = ref(true);
const updatingId = ref<number | null>(null);

const columns = [
  { key: 'todo', label: 'To Do', color: 'border-slate-300 dark:border-zinc-600', headBg: 'bg-slate-100 dark:bg-zinc-800', dot: 'bg-slate-400' },
  { key: 'in_progress', label: 'In Progress', color: 'border-sky-300 dark:border-sky-700', headBg: 'bg-sky-50 dark:bg-sky-900/30', dot: 'bg-sky-500' },
  { key: 'blocked', label: 'Blocked', color: 'border-red-300 dark:border-red-800', headBg: 'bg-red-50 dark:bg-red-900/20', dot: 'bg-red-500' },
  { key: 'done', label: 'Done', color: 'border-teal-300 dark:border-teal-800', headBg: 'bg-teal-50 dark:bg-teal-900/20', dot: 'bg-teal-600' },
];

const priorityColors: Record<string, string> = {
  urgent: 'text-red-600 dark:text-red-400',
  high: 'text-orange-600 dark:text-orange-400',
  medium: 'text-sky-600 dark:text-sky-400',
  low: 'text-slate-400 dark:text-zinc-500',
};

const statusCycle: Record<string, string> = {
  todo: 'in_progress',
  in_progress: 'done',
  blocked: 'todo',
  done: 'todo',
};

const total = computed(() => Object.values(board.value).reduce((s, col) => s + col.length, 0));

async function load() {
  loading.value = true;
  const res = await api.get('/api/v1/tasks/kanban').then(r => r.data);
  board.value = res.board || { todo: [], in_progress: [], blocked: [], done: [] };
  loading.value = false;
}

async function cycleStatus(task: any, col: string) {
  const next = statusCycle[col];
  if (!next || updatingId.value) return;
  updatingId.value = task.id;
  try {
    await api.patch(`/api/v1/tasks/${task.id}/status`, { status: next });
    board.value[col] = board.value[col].filter((t: any) => t.id !== task.id);
    board.value[next] = [{ ...task, status: next }, ...board.value[next]];
    toast({ tone: 'success', title: 'Status diupdate', message: `${task.title} → ${next.replaceAll('_', ' ')}` });
  } finally {
    updatingId.value = null;
  }
}

function isOverdue(task: any) {
  return task.overdue;
}

onMounted(load);
</script>

<template>
  <div>
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <p class="text-xs font-extrabold uppercase text-teal-700 dark:text-teal-500">Board</p>
        <h1 class="text-2xl font-extrabold">Task Board</h1>
        <p class="mt-1 text-sm font-medium text-slate-500 dark:text-zinc-500">{{ total }} tasks total</p>
      </div>
      <div class="flex gap-2">
        <router-link class="btn btn-muted" to="/tasks">List view</router-link>
        <router-link class="btn btn-primary" to="/tasks/create">New task</router-link>
      </div>
    </div>

    <div v-if="loading" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <div v-for="i in 4" :key="i" class="skeleton h-64 rounded-2xl"></div>
    </div>

    <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <div v-for="col in columns" :key="col.key" class="flex flex-col rounded-2xl border" :class="col.color">
        <!-- Column header -->
        <div class="flex items-center justify-between rounded-t-2xl px-3 py-2.5" :class="col.headBg">
          <div class="flex items-center gap-2">
            <span class="h-2.5 w-2.5 rounded-full" :class="col.dot" />
            <span class="text-sm font-extrabold text-slate-700 dark:text-zinc-200">{{ col.label }}</span>
          </div>
          <span class="rounded-full bg-white/60 px-2 py-0.5 text-xs font-extrabold text-slate-500 dark:bg-zinc-900/50 dark:text-zinc-400">{{ board[col.key]?.length ?? 0 }}</span>
        </div>

        <!-- Cards -->
        <div class="flex-1 space-y-2 overflow-y-auto p-2" style="max-height: 70vh">
          <div
            v-for="task in board[col.key]"
            :key="task.id"
            class="group rounded-xl border border-slate-100 bg-white p-3 shadow-sm transition hover:border-teal-200 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-teal-700"
          >
            <p
              class="cursor-pointer text-sm font-extrabold leading-snug text-slate-800 hover:text-teal-700 dark:text-zinc-200 dark:hover:text-teal-400"
              @click="router.push(`/tasks/${task.id}`)"
            >{{ task.title }}</p>
            <div class="mt-2 flex items-center justify-between gap-2">
              <div class="flex flex-wrap items-center gap-1.5">
                <span v-if="task.priority" class="text-[10px] font-extrabold uppercase" :class="priorityColors[task.priority]">{{ task.priority }}</span>
                <span v-if="task.project_name" class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-500 dark:bg-zinc-800 dark:text-zinc-400">{{ task.project_name }}</span>
                <span v-if="task.due_date" class="rounded px-1.5 py-0.5 text-[10px] font-bold" :class="isOverdue(task) ? 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400' : 'text-slate-400 dark:text-zinc-600'">
                  {{ isOverdue(task) ? '⚠ ' : '' }}{{ formatDate(task.due_date) }}
                </span>
              </div>
              <!-- Quick cycle button -->
              <button
                v-if="col.key !== 'done'"
                class="invisible shrink-0 rounded-lg p-1 text-slate-300 hover:bg-teal-50 hover:text-teal-700 group-hover:visible dark:text-zinc-600 dark:hover:bg-teal-900/30 dark:hover:text-teal-400"
                :disabled="updatingId === task.id"
                :title="`Move to ${statusCycle[col.key]?.replaceAll('_', ' ')}`"
                @click.stop="cycleStatus(task, col.key)"
              >
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
              </button>
              <button
                v-else
                class="invisible shrink-0 rounded-lg p-1 text-slate-300 hover:bg-slate-50 hover:text-slate-600 group-hover:visible dark:text-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-300"
                title="Reopen (back to todo)"
                @click.stop="cycleStatus(task, col.key)"
              >
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
              </button>
            </div>
          </div>
          <p v-if="!board[col.key]?.length" class="py-8 text-center text-xs font-semibold text-slate-300 dark:text-zinc-600">Kosong</p>
        </div>
      </div>
    </div>
  </div>
</template>
