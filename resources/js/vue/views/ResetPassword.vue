<script setup lang="ts">
import { ref } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { api } from '../api';

const route = useRoute();
const router = useRouter();
const form = ref({ token: String(route.params.token || ''), email: String(route.query.email || ''), password: '', password_confirmation: '' });
const error = ref('');
const showPassword = ref(false);

async function submit() {
  error.value = '';
  try {
    await api.post('/api/reset-password', form.value);
    await router.push('/login');
  } catch (e: any) {
    error.value = e.response?.data?.message || 'Could not reset password.';
  }
}
</script>

<template>
  <main class="grid min-h-screen place-items-center bg-stone-100 px-4 dark:bg-zinc-950">
    <section class="w-full max-w-md">
      <div class="mb-6"><h1 class="text-3xl font-semibold">ProgressOS</h1><p class="mt-2 text-zinc-600">Choose a new password.</p></div>
      <form class="card space-y-4 p-6" @submit.prevent="submit">
        <h2 class="text-xl font-semibold">Reset password</h2>
        <p v-if="error" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>
        <label><span class="label mb-1">Email</span><input v-model="form.email" class="field" type="email" required /></label>
        <div>
          <span class="label mb-1">New password</span>
          <div class="relative">
            <input v-model="form.password" :type="showPassword ? 'text' : 'password'" class="field pr-20" required />
            <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 text-xs text-slate-400 hover:text-slate-700 dark:hover:text-zinc-300" @click="showPassword = !showPassword">{{ showPassword ? 'Sembunyikan' : 'Tampilkan' }}</button>
          </div>
        </div>
        <div>
          <span class="label mb-1">Confirm password</span>
          <div class="relative">
            <input v-model="form.password_confirmation" :type="showPassword ? 'text' : 'password'" class="field pr-20" required />
            <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 text-xs text-slate-400 hover:text-slate-700 dark:hover:text-zinc-300" @click="showPassword = !showPassword">{{ showPassword ? 'Sembunyikan' : 'Tampilkan' }}</button>
          </div>
        </div>
        <button class="btn btn-primary w-full">Reset password</button>
        <RouterLink to="/login" class="block text-center text-sm font-semibold text-teal-700">Back to login</RouterLink>
      </form>
    </section>
  </main>
</template>
