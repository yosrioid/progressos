<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { api, unwrap } from '../api';
import { formatDate, minutes } from '../format';

const route = useRoute();
const report = ref<any>(null);

async function load() {
  report.value = (await api.get(`/api/v1/reports/${route.params.period}`).then(unwrap)).report;
}

watch(() => route.params.period, load);
onMounted(load);
</script>

<template>
  <div v-if="!report" class="card p-8 text-center text-sm text-slate-500">Loading report...</div>
  <template v-else>
    <div class="mb-5"><p class="text-sm font-semibold text-teal-700">{{ formatDate(report.start) }} to {{ formatDate(report.end) }}</p><h1 class="text-2xl font-semibold capitalize">{{ report.period }} Report</h1></div>
    <div class="mb-5 grid gap-3 md:grid-cols-3"><div class="card p-4"><p class="label">Completed work</p><p class="mt-2 text-2xl font-semibold">{{ report.completed_work_logs.length }}</p></div><div class="card p-4"><p class="label">Open blockers</p><p class="mt-2 text-2xl font-semibold text-rose-800">{{ report.open_blockers.length }}</p></div><div class="card p-4"><p class="label">Learning</p><p class="mt-2 text-2xl font-semibold">{{ minutes(report.learning_totals.minutes) }}</p></div></div>
    <section class="card p-5"><h2 class="mb-4 font-semibold">Key achievements</h2><ul class="grid gap-2 md:grid-cols-2"><li v-for="item in report.key_achievements" :key="item" class="rounded-lg border bg-teal-50/40 px-3 py-2 text-sm font-medium">{{ item }}</li></ul></section>
  </template>
</template>
