<script setup lang="ts">
import { ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
const router = useRouter();
const error = ref('');
const showPassword = ref(false);
const showPasswordConfirm = ref(false);
const form = ref({ name: '', email: '', password: '', password_confirmation: '', timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC' });

async function submit() {
  error.value = '';
  try {
    await auth.register(form.value);
    await router.push('/dashboard');
  } catch (e: any) {
    error.value = e.response?.data?.message || 'Registration failed.';
  }
}
</script>

<template>
  <main class="grid min-h-screen place-items-center bg-stone-100 px-4 dark:bg-zinc-950">
    <form class="card w-full max-w-md space-y-4 p-6" @submit.prevent="submit">
      <h1 class="text-2xl font-semibold">Create ProgressOS account</h1>
      <p v-if="error" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>
      <label><span class="label mb-1">Name</span><input v-model="form.name" class="field" required /></label>
      <label><span class="label mb-1">Email</span><input v-model="form.email" class="field" type="email" required /></label>
      <label><span class="label mb-1">Timezone</span><input v-model="form.timezone" class="field" /></label>
      <div>
        <span class="label mb-1">Password</span>
        <div class="relative">
          <input v-model="form.password" :type="showPassword ? 'text' : 'password'" class="field pr-9" required />
          <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 p-1 text-slate-400 hover:text-slate-600 dark:hover:text-zinc-300" @click="showPassword = !showPassword">
            <svg v-if="!showPassword" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
            <svg v-else class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
          </button>
        </div>
      </div>
      <div>
        <span class="label mb-1">Confirm password</span>
        <div class="relative">
          <input v-model="form.password_confirmation" :type="showPasswordConfirm ? 'text' : 'password'" class="field pr-9" required />
          <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 p-1 text-slate-400 hover:text-slate-600 dark:hover:text-zinc-300" @click="showPasswordConfirm = !showPasswordConfirm">
            <svg v-if="!showPasswordConfirm" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
            <svg v-else class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
          </button>
        </div>
      </div>
      <button class="btn btn-primary w-full">Register</button>
      <RouterLink to="/login" class="block text-center text-sm font-semibold text-teal-700">Back to login</RouterLink>
    </form>
  </main>
</template>
