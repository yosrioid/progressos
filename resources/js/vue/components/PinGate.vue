<script setup lang="ts">
import { nextTick, onMounted, ref } from 'vue';
import { usePrivacyStore } from '../stores/privacy';

const privacy = usePrivacyStore();
const pin = ref('');
const error = ref('');
const inputRef = ref<HTMLInputElement | null>(null);

function submit() {
  if (!pin.value.trim()) return;
  if (privacy.tryUnlock(pin.value)) {
    pin.value = '';
    error.value = '';
  } else {
    error.value = 'PIN salah. Coba lagi.';
    pin.value = '';
    nextTick(() => inputRef.value?.focus());
  }
}

onMounted(() => {
  if (privacy.hasPin && !privacy.isUnlocked) {
    nextTick(() => inputRef.value?.focus());
  }
});
</script>

<template>
  <template v-if="!privacy.hasPin || privacy.isUnlocked">
    <slot />
  </template>

  <div v-else class="flex min-h-[70vh] items-center justify-center px-4">
    <div class="card w-full max-w-sm p-8 text-center">
      <div class="mx-auto mb-5 grid h-14 w-14 place-items-center rounded-2xl bg-slate-100 dark:bg-zinc-800">
        <svg class="h-7 w-7 text-slate-500 dark:text-zinc-400" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" viewBox="0 0 24 24">
          <rect x="3" y="11" width="18" height="11" rx="2"/>
          <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
        </svg>
      </div>
      <h2 class="text-xl font-extrabold text-slate-900 dark:text-zinc-100">Halaman Terkunci</h2>
      <p class="mt-1 text-sm font-medium text-slate-500 dark:text-zinc-500">Masukkan PIN untuk melanjutkan</p>
      <input
        ref="inputRef"
        v-model="pin"
        type="password"
        inputmode="numeric"
        maxlength="8"
        autocomplete="off"
        class="field mt-5 text-center text-xl tracking-[0.4em]"
        placeholder="••••"
        @keydown.enter="submit"
      />
      <p v-if="error" class="mt-2 text-sm font-semibold text-red-500">{{ error }}</p>
      <button class="btn btn-primary mt-4 w-full" @click="submit">Buka</button>
      <p class="mt-4 text-xs text-slate-400 dark:text-zinc-600">Kunci otomatis setelah 5 menit tidak aktif</p>
    </div>
  </div>
</template>
