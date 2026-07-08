<script setup lang="ts">
import { computed } from 'vue';
import { useConfigurationStore } from '../stores/configuration';

const config = useConfigurationStore();

const usagePercent = computed(() => config.usagePercentage);
const isExceeded = computed(() => usagePercent.value >= 100);

const statusColor = computed(() => {
  const pct = usagePercent.value;
  if (pct >= 100) return 'text-red-600';
  if (pct >= 80) return 'text-orange-600';
  if (pct >= 50) return 'text-yellow-600';
  return 'text-teal-600';
});
</script>

<template>
  <div v-if="config.isAdaCode" class="flex items-center gap-2">
    <span :class="statusColor" class="text-xs font-extrabold">
      {{ isExceeded ? 'HABIS' : `${usagePercent}%` }}
    </span>
    <span v-if="!isExceeded" class="text-xs text-slate-400 dark:text-zinc-600">
      {{ config.ai.usage_requests || 0 }}/{{ config.ai.request_limit || 14400 }}
    </span>
  </div>
</template>
