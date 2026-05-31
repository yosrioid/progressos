<script setup lang="ts">
import { ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
const router = useRouter();
const error = ref('');
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
  <main class="grid min-h-screen place-items-center bg-stone-100 px-4">
    <form class="card w-full max-w-md space-y-4 p-6" @submit.prevent="submit">
      <h1 class="text-2xl font-semibold">Create ProgressOS account</h1>
      <p v-if="error" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>
      <label><span class="label mb-1">Name</span><input v-model="form.name" class="field" required /></label>
      <label><span class="label mb-1">Email</span><input v-model="form.email" class="field" type="email" required /></label>
      <label><span class="label mb-1">Timezone</span><input v-model="form.timezone" class="field" /></label>
      <label><span class="label mb-1">Password</span><input v-model="form.password" class="field" type="password" required /></label>
      <label><span class="label mb-1">Confirm password</span><input v-model="form.password_confirmation" class="field" type="password" required /></label>
      <button class="btn btn-primary w-full">Register</button>
      <RouterLink to="/login" class="block text-center text-sm font-semibold text-teal-700">Back to login</RouterLink>
    </form>
  </main>
</template>
