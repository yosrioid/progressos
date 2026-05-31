<script setup lang="ts">
import { ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
const router = useRouter();
const error = ref('');
const form = ref({ email: 'test@example.com', password: 'password', remember: true });

async function submit() {
  error.value = '';
  try {
    await auth.login(form.value);
    await router.push('/dashboard');
  } catch (e: any) {
    error.value = e.response?.data?.message || 'Login failed.';
  }
}
</script>

<template>
  <main class="grid min-h-screen place-items-center bg-stone-100 px-4">
    <section class="w-full max-w-md">
      <div class="mb-6"><h1 class="text-3xl font-semibold">ProgressOS</h1><p class="mt-2 text-zinc-600">Personal operating system for progress, learning, and work review.</p></div>
      <form class="card space-y-4 p-6" @submit.prevent="submit">
        <h2 class="text-xl font-semibold">Welcome back</h2>
        <p v-if="error" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>
        <label><span class="label mb-1">Email</span><input v-model="form.email" class="field" type="email" required /></label>
        <label><span class="label mb-1">Password</span><input v-model="form.password" class="field" type="password" required /></label>
        <label class="flex items-center gap-2 text-sm"><input v-model="form.remember" type="checkbox" /> Remember this device</label>
        <button class="btn btn-primary w-full">Log in</button>
        <div class="flex justify-between gap-3 text-sm font-semibold text-teal-700">
          <RouterLink to="/forgot-password">Forgot password</RouterLink>
          <RouterLink to="/register">Create account</RouterLink>
        </div>
      </form>
    </section>
  </main>
</template>
