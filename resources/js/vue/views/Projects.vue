<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { api, unwrap } from '../api';

const projects = ref<any[]>([]);
onMounted(async () => projects.value = (await api.get('/api/v1/projects').then(unwrap)).projects);
</script>

<template>
  <div class="mb-5"><h1 class="text-2xl font-semibold">Projects</h1><p class="mt-1 text-sm text-slate-500">Project workspaces powered by REST API data.</p></div>
  <div v-if="projects.length === 0" class="card p-8 text-center text-sm text-slate-500">Projects appear when tasks or work logs use a project.</div>
  <div v-else class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
    <RouterLink v-for="project in projects" :key="project.id" :to="`/projects/${project.id}`" class="card p-5 hover:border-teal-400">
      <h2 class="text-lg font-semibold">{{ project.name }}</h2>
      <div class="mt-4 grid grid-cols-3 gap-2 text-center text-sm">
        <div class="rounded-lg bg-slate-50 p-3 dark:bg-zinc-800/60"><b>{{ project.open_tasks_count }}</b><p class="text-slate-500 dark:text-zinc-400">Open</p></div>
        <div class="rounded-lg bg-slate-50 p-3 dark:bg-zinc-800/60"><b>{{ project.tasks_count }}</b><p class="text-slate-500 dark:text-zinc-400">Tasks</p></div>
        <div class="rounded-lg bg-slate-50 p-3 dark:bg-zinc-800/60"><b>{{ project.work_logs_count }}</b><p class="text-slate-500 dark:text-zinc-400">Logs</p></div>
      </div>
    </RouterLink>
  </div>
</template>
