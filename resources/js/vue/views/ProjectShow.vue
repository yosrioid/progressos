<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useRoute, RouterLink } from 'vue-router';
import { api, unwrap } from '../api';
import { formatDate, minutes } from '../format';

const route = useRoute();
const data = ref<any>(null);
onMounted(async () => data.value = await api.get(`/api/v1/projects/${route.params.id}`).then(unwrap));
</script>

<template>
  <div v-if="!data" class="card p-8 text-center text-sm text-slate-500">Loading project...</div>
  <template v-else>
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
      <div><p class="text-sm font-semibold text-teal-700">Project workspace</p><h1 class="text-2xl font-semibold">{{ data.project.name }}</h1></div>
      <div class="flex flex-wrap gap-2">
        <RouterLink class="btn btn-primary" :to="`/work-logs/create?project_name=${encodeURIComponent(data.project.name)}`">Add work log</RouterLink>
        <RouterLink class="btn btn-muted" :to="`/tasks/create?project_id=${data.project.id}`">Add task</RouterLink>
        <RouterLink class="btn btn-muted" :to="`/milestones/create?category=${encodeURIComponent(data.project.name)}`">Add milestone</RouterLink>
        <RouterLink class="btn btn-muted" to="/projects">Back</RouterLink>
      </div>
    </div>
    <div class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
      <div class="card p-4"><p class="label">Open tasks</p><p class="mt-2 text-2xl font-semibold">{{ data.metrics.open_tasks }}</p></div>
      <div class="card p-4"><p class="label">Done tasks</p><p class="mt-2 text-2xl font-semibold text-teal-800">{{ data.metrics.completed_tasks }}</p></div>
      <div class="card p-4"><p class="label">Logged</p><p class="mt-2 text-2xl font-semibold">{{ minutes(data.metrics.logged_minutes) }}</p></div>
      <div class="card p-4"><p class="label">Blockers</p><p class="mt-2 text-2xl font-semibold text-rose-800">{{ data.metrics.blockers }}</p></div>
    </div>
    <div class="grid gap-5 xl:grid-cols-2">
      <section class="card p-5"><div class="mb-4 flex items-center justify-between"><h2 class="font-semibold">Tasks</h2><RouterLink class="text-sm font-semibold text-teal-700" :to="`/tasks?project_id=${data.project.id}`">View all</RouterLink></div><div class="space-y-2"><RouterLink v-for="task in data.tasks" :key="task.id" :to="`/tasks/${task.id}`" class="block rounded-lg border p-3 hover:bg-teal-50/40"><b>{{ task.title }}</b><p class="text-sm text-slate-500">{{ task.status }} · {{ task.priority }}</p></RouterLink></div></section>
      <section class="card p-5"><div class="mb-4 flex items-center justify-between"><h2 class="font-semibold">Work logs</h2><RouterLink class="text-sm font-semibold text-teal-700" :to="`/work-logs?project_name=${encodeURIComponent(data.project.name)}`">View all</RouterLink></div><div class="space-y-2"><RouterLink v-for="log in data.workLogs" :key="log.id" :to="`/work-logs/${log.id}`" class="block rounded-lg border p-3 hover:bg-teal-50/40"><b>{{ log.title }}</b><p class="text-sm text-slate-500">{{ formatDate(log.date) }} · {{ minutes(log.actual_duration) }}</p></RouterLink></div></section>
    </div>
  </template>
</template>
