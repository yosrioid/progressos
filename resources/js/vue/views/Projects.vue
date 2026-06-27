<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { api, unwrap } from '../api';
import { toast } from '../feedback';

const projects = ref<any[]>([]);
const creating = ref(false);
const form = ref({ name: '', color: 'teal' });
const colors = ['teal', 'sky', 'amber', 'rose', 'violet', 'slate'];

async function loadProjects() {
  projects.value = (await api.get('/api/v1/projects').then(unwrap)).projects;
}

async function createProject() {
  const name = form.value.name.trim();
  if (!name || creating.value) return;

  creating.value = true;
  try {
    const response = await api.post('/api/v1/projects', { name, color: form.value.color }).then(unwrap);
    form.value.name = '';
    form.value.color = 'teal';
    await loadProjects();
    toast({ tone: 'success', title: 'Project dibuat', message: response.project?.name ?? name });
  } catch (error: any) {
    toast({
      tone: 'error',
      title: 'Project gagal dibuat',
      message: error.response?.data?.message ?? 'Cek nama project lalu coba lagi.',
    });
  } finally {
    creating.value = false;
  }
}

onMounted(loadProjects);
</script>

<template>
  <div class="mb-5 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
    <div>
      <h1 class="text-2xl font-semibold">Projects</h1>
      <p class="mt-1 text-sm text-slate-500">Project workspaces powered by REST API data.</p>
    </div>
    <form class="flex flex-col gap-2 sm:flex-row sm:items-center" @submit.prevent="createProject">
      <input
        v-model="form.name"
        class="field min-w-64"
        maxlength="120"
        placeholder="New project name"
        autocomplete="off"
      />
      <div class="flex gap-1">
        <button
          v-for="color in colors"
          :key="color"
          type="button"
          class="h-9 w-9 rounded-xl border-2 transition"
          :class="[
            form.color === color ? 'border-slate-900 dark:border-zinc-100' : 'border-transparent',
            {
              teal: 'bg-teal-500',
              sky: 'bg-sky-500',
              amber: 'bg-amber-500',
              rose: 'bg-rose-500',
              violet: 'bg-violet-500',
              slate: 'bg-slate-500',
            }[color],
          ]"
          :aria-label="`Use ${color} color`"
          @click="form.color = color"
        />
      </div>
      <button class="btn btn-primary" :disabled="creating || !form.name.trim()">
        {{ creating ? 'Creating...' : 'New project' }}
      </button>
    </form>
  </div>
  <div v-if="projects.length === 0" class="card p-8 text-center text-sm text-slate-500">Create a project here, or type a new project name while adding a task or work log.</div>
  <div v-else class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
    <RouterLink v-for="project in projects" :key="project.id" :to="`/projects/${project.id}`" class="card p-5 hover:border-teal-400">
      <h2 class="text-lg font-semibold">{{ project.name }}</h2>
      <div class="mt-4 grid grid-cols-3 gap-2 text-center text-sm">
        <div class="rounded-lg bg-slate-50 p-3"><b>{{ project.open_tasks_count }}</b><p class="text-slate-500">Open</p></div>
        <div class="rounded-lg bg-slate-50 p-3"><b>{{ project.tasks_count }}</b><p class="text-slate-500">Tasks</p></div>
        <div class="rounded-lg bg-slate-50 p-3"><b>{{ project.work_logs_count }}</b><p class="text-slate-500">Logs</p></div>
      </div>
    </RouterLink>
  </div>
</template>
