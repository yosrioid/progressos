<script setup lang="ts">
import { ref } from 'vue';
import { RouterLink } from 'vue-router';
import { api } from '../api';

const email = ref('');
const message = ref('');
const error = ref('');
const sent = ref(false);

async function submit() {
  message.value = '';
  error.value = '';
  try {
    const response = await api.post('/api/forgot-password', { email: email.value });
    message.value = response.data.message || 'Link reset dikirim.';
    sent.value = true;
  } catch (e: any) {
    error.value = e.response?.data?.message || 'Gagal mengirim link reset.';
  }
}
</script>

<template>
  <main class="grid min-h-screen place-items-center bg-stone-100 px-4 dark:bg-zinc-950">
    <section class="w-full max-w-md">
      <div class="mb-6">
        <h1 class="text-3xl font-semibold">ProgressOS</h1>
        <p class="mt-2 text-zinc-600">Reset password via email.</p>
      </div>
      <div class="card space-y-4 p-6">
        <h2 class="text-xl font-semibold">Lupa password</h2>

        <template v-if="sent">
          <div class="rounded-lg border border-teal-200 bg-teal-50 p-4 text-sm text-teal-800">
            <p class="font-semibold">Link reset dikirim!</p>
            <p class="mt-1">Cek inbox email <strong>{{ email }}</strong> dan klik link di dalamnya. Cek folder spam jika tidak ada.</p>
          </div>
          <RouterLink to="/login" class="btn btn-muted block w-full text-center">Kembali ke login</RouterLink>
        </template>

        <template v-else>
          <p v-if="error" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>
          <form class="space-y-4" @submit.prevent="submit">
            <label>
              <span class="label mb-1">Email</span>
              <input v-model="email" class="field" type="email" autocomplete="email" placeholder="email@kamu.com" required />
            </label>
            <button class="btn btn-primary w-full">Kirim link reset</button>
          </form>
          <RouterLink to="/login" class="block text-center text-sm font-semibold text-teal-700">Kembali ke login</RouterLink>
        </template>
      </div>
    </section>
  </main>
</template>
