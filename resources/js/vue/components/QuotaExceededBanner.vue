<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { api, unwrap } from '../api';
import { toast } from '../feedback';
import { useConfigurationStore } from '../stores/configuration';

const config = useConfigurationStore();
const show = ref(false);

const isExceeded = computed(() => config.usagePercentage >= 100);
const providerName = computed(() => config.aiProvider === 'adacode' ? 'AdaCode' : 'Groq');

onMounted(() => {
  // Show banner if quota is exceeded
  if (isExceeded.value) {
    show.value = true;
  }
  
  const dismissed = localStorage.getItem('quota_banner_dismissed');
  if (!dismissed) {
    const lastError = sessionStorage.getItem('last_quota_error');
    if (lastError) {
      show.value = true;
      sessionStorage.removeItem('last_quota_error');
    }
  }
});

function dismiss() {
  show.value = false;
  localStorage.setItem('quota_banner_dismissed', '1');
}

async function switchToGroq() {
  try {
    await api.put('/api/admin/configuration/ai', { provider: 'groq' }).then(unwrap);
    config.applyGroups({ ai: { ...config.groups.ai, provider: 'groq' } });
    toast({ tone: 'success', title: 'Switched to Groq', message: 'Chat and quote now use Groq.' });
  } catch {
    // silently fail
  }
  dismiss();
}
</script>

<template>
  <Transition
    enter-active-class="transition duration-300 ease-out"
    enter-from-class="opacity-0 -translate-y-2"
    enter-to-class="opacity-100 translate-y-0"
  >
    <div v-if="show && isExceeded" class="fixed top-0 left-0 right-0 z-50 bg-red-600 text-white px-4 py-2">
      <div class="mx-auto flex max-w-6xl items-center justify-between gap-4">
        <div class="flex items-center gap-3">
          <span class="text-lg">⛔</span>
          <div>
            <p class="text-sm font-extrabold">{{ providerName }} Quota Exceeded!</p>
            <p class="text-xs text-red-100">Please upgrade your plan or switch to another provider.</p>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <button v-if="config.aiProvider !== 'groq'" class="rounded-lg bg-white/20 px-3 py-1.5 text-xs font-bold text-white hover:bg-white/30" @click="switchToGroq">
            Switch to Groq
          </button>
          <a v-if="config.aiProvider === 'adacode'" href="https://adacode.ai/billing" target="_blank" class="rounded-lg bg-white/20 px-3 py-1.5 text-xs font-bold text-white hover:bg-white/30">
            Upgrade Plan
          </a>
          <button class="text-white/70 hover:text-white text-lg leading-none" @click="dismiss">×</button>
        </div>
      </div>
    </div>
  </Transition>
</template>
