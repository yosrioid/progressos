<script setup lang="ts">
import { ref } from 'vue';
import { RouterLink } from 'vue-router';
import { api } from '../api';

const email = ref('');
const message = ref('');
const error = ref('');

async function submit() {
  message.value = '';
  error.value = '';
  try {
    const response = await api.post('/api/forgot-password', { email: email.value });
    message.value = response.data.message || 'Reset link sent.';
  } catch (e: any) {
    error.value = e.response?.data?.message || 'Could not send reset link.';
  }
}
</script>

<template>
  <main class="grid min-h-screen place-items-center bg-stone-100 px-4">
    <section class="w-full max-w-md">
      <div class="mb-6"><h1 class="text-3xl font-semibold">ProgressOS</h1><p class="mt-2 text-zinc-600">Send a reset link to your email.</p></div>
      <form class="card space-y-4 p-6" @submit.prevent="submit">
        <h2 class="text-xl font-semibold">Forgot password</h2>
        <p v-if="message" class="rounded-lg border border-teal-200 bg-teal-50 p-3 text-sm text-teal-800">{{ message }}</p>
        <p v-if="error" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>
        <label><span class="label mb-1">Email</span><input v-model="email" class="field" type="email" required /></label>
        <button class="btn btn-primary w-full">Send reset link</button>
        <RouterLink to="/login" class="block text-center text-sm font-semibold text-teal-700">Back to login</RouterLink>
      </form>
    </section>
  </main>
</template>
