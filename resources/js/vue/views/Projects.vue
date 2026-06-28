<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { api, unwrap } from '../api';
import { toast } from '../feedback';

const projects = ref<any[]>([]);
const loading = ref(true);

onMounted(async () => {
  try {
    projects.value = (await api.get('/api/v1/projects').then(unwrap)).projects;
  } catch (e: any) {
    toast({ tone: 'error', title: 'Gagal memuat proyek', message: e?.response?.data?.message ?? 'Terjadi kesalahan.' });
  } finally {
    loading.value = false;
  }
});
</script>

<template>
  <div class="mb-5">
    <p class="text-xs font-extrabold uppercase text-teal-700 dark:text-teal-500">Workspace</p>
    <h1 class="text-3xl font-extrabold tracking-tight">Projects</h1>
    <p class="mt-1 text-sm font-medium text-slate-500 dark:text-zinc-500">Kelola proyek aktif dan lihat breakdown tasks, work logs, serta progres per proyek</p>
  </div>

  <!-- Loading skeleton -->
  <div v-if="loading" class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
    <div v-for="i in 6" :key="i" class="skeleton h-36 rounded-2xl"></div>
  </div>

  <!-- Empty state -->
  <div v-else-if="!projects.length" class="card p-12 text-center">
    <div class="mx-auto mb-4 grid h-12 w-12 place-items-center rounded-xl bg-teal-50 dark:bg-teal-900/20">
      <svg class="h-6 w-6 text-teal-700 dark:text-teal-400" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" viewBox="0 0 24 24"><path d="M3 7.5A2.5 2.5 0 0 1 5.5 5H10l2 2h6.5A2.5 2.5 0 0 1 21 9.5v7A2.5 2.5 0 0 1 18.5 19h-13A2.5 2.5 0 0 1 3 16.5v-9Z"/></svg>
    </div>
    <h2 class="text-base font-extrabold text-slate-900 dark:text-zinc-100">Belum ada proyek</h2>
    <p class="mx-auto mt-2 max-w-xs text-sm text-slate-400 dark:text-zinc-500">Proyek muncul otomatis saat work logs atau tasks menggunakan nama proyek.</p>
  </div>

  <!-- Projects grid -->
  <div v-else class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
    <RouterLink
      v-for="project in projects"
      :key="project.id"
      :to="`/projects/${project.id}`"
      class="card p-5 transition hover:border-teal-300 dark:hover:border-teal-700"
      :title="`Buka workspace proyek ${project.name}`"
    >
      <h2 class="text-lg font-extrabold text-slate-900 dark:text-zinc-100">{{ project.name }}</h2>
      <div class="mt-4 grid grid-cols-3 gap-2 text-center text-sm">
        <div class="rounded-lg bg-slate-50 p-3 dark:bg-zinc-800/60" title="Task yang masih open">
          <b class="text-slate-900 dark:text-zinc-100">{{ project.open_tasks_count }}</b>
          <p class="text-slate-500 dark:text-zinc-400">Open</p>
        </div>
        <div class="rounded-lg bg-slate-50 p-3 dark:bg-zinc-800/60" title="Total task di proyek ini">
          <b class="text-slate-900 dark:text-zinc-100">{{ project.tasks_count }}</b>
          <p class="text-slate-500 dark:text-zinc-400">Tasks</p>
        </div>
        <div class="rounded-lg bg-slate-50 p-3 dark:bg-zinc-800/60" title="Total work log yang tercatat">
          <b class="text-slate-900 dark:text-zinc-100">{{ project.work_logs_count }}</b>
          <p class="text-slate-500 dark:text-zinc-400">Logs</p>
        </div>
      </div>
    </RouterLink>
  </div>
</template>
