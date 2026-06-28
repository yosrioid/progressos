<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { api, unwrap } from '../api';

interface Quote {
  quote: string;
  author: string;
  themes: string;
}

const quote = ref<Quote | null>(null);
const visible = ref(false);
const loading = ref(true);

const dismissKey = `quote_dismissed_${new Date().toISOString().slice(0, 10)}`;

onMounted(async () => {
  if (sessionStorage.getItem(dismissKey)) {
    loading.value = false;
    return;
  }
  try {
    const res = await api.get('/api/v1/quote/daily').then(unwrap);
    if (res.quote) {
      quote.value = res.quote;
      setTimeout(() => { visible.value = true; }, 600);
    }
  } catch {
    // non-critical
  } finally {
    loading.value = false;
  }
});

function dismiss() {
  visible.value = false;
  sessionStorage.setItem(dismissKey, '1');
}
</script>

<template>
  <Transition
    enter-active-class="transition duration-500 ease-out"
    enter-from-class="opacity-0 translate-y-2"
    enter-to-class="opacity-100 translate-y-0"
    leave-active-class="transition duration-300 ease-in"
    leave-from-class="opacity-100"
    leave-to-class="opacity-0"
  >
    <div
      v-if="visible && quote"
      class="mx-4 mb-3 rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-2.5 dark:border-zinc-800 dark:bg-zinc-800/40"
    >
      <div class="flex items-start justify-between gap-2">
        <span class="text-[10px] font-extrabold uppercase tracking-wide text-teal-600 dark:text-teal-500">✦ Daily Quote</span>
        <button
          class="shrink-0 text-slate-300 hover:text-slate-500 dark:text-zinc-600 dark:hover:text-zinc-400 transition-colors"
          :title="`Dismiss (kembali besok)`"
          @click="dismiss"
        >
          <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
      </div>
      <p class="mt-1.5 text-[11px] font-medium italic leading-relaxed text-slate-600 dark:text-zinc-400">
        "{{ quote.quote }}"
      </p>
      <p class="mt-1 text-[10px] font-semibold text-slate-400 dark:text-zinc-500">— {{ quote.author }}</p>
    </div>
  </Transition>
</template>
