<script setup lang="ts">
import { ref } from 'vue';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
const profile = ref({ name: auth.user?.name || '', email: auth.user?.email || '', timezone: auth.user?.timezone || 'Asia/Jakarta', theme: auth.user?.theme || 'system' });
const password = ref({ current_password: '', password: '', password_confirmation: '' });
const avatar = ref<File | null>(null);
const message = ref('');
const error = ref('');

async function saveProfile() {
  message.value = '';
  error.value = '';
  try {
    await auth.updateProfile(profile.value);
    message.value = 'Profile saved.';
  } catch (e: any) {
    error.value = e.response?.data?.message || 'Could not save profile.';
  }
}

async function savePassword() {
  message.value = '';
  error.value = '';
  try {
    await auth.updatePassword(password.value);
    password.value = { current_password: '', password: '', password_confirmation: '' };
    message.value = 'Password changed.';
  } catch (e: any) {
    error.value = e.response?.data?.message || 'Could not change password.';
  }
}

async function saveAvatar() {
  if (!avatar.value) return;
  message.value = '';
  error.value = '';
  const payload = new FormData();
  payload.append('avatar', avatar.value);
  try {
    await auth.updateAvatar(payload);
    avatar.value = null;
    message.value = 'Avatar updated.';
  } catch (e: any) {
    error.value = e.response?.data?.message || 'Could not update avatar.';
  }
}
</script>

<template>
  <div class="mb-5">
    <p class="text-sm font-semibold text-teal-700">Account</p>
    <h1 class="mt-1 text-2xl font-semibold">Profile & Settings</h1>
  </div>
  <p v-if="message" class="mb-4 rounded-xl border border-teal-200 bg-teal-50 p-3 text-sm font-medium text-teal-800">{{ message }}</p>
  <p v-if="error" class="mb-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm font-medium text-red-700">{{ error }}</p>
  <div class="grid gap-5 xl:grid-cols-2">
    <form class="card p-5" @submit.prevent="saveProfile">
      <h2 class="mb-4 font-semibold">Profile</h2>
      <div class="grid gap-4">
        <label><span class="label mb-1">Name</span><input v-model="profile.name" class="field" required /></label>
        <label><span class="label mb-1">Email</span><input v-model="profile.email" class="field" type="email" required /></label>
        <label><span class="label mb-1">Timezone</span><input v-model="profile.timezone" class="field" required /></label>
        <label><span class="label mb-1">Theme</span><select v-model="profile.theme" class="field"><option value="light">Light</option><option value="dark">Dark</option><option value="system">System</option></select></label>
      </div>
      <div class="mt-5 flex justify-end"><button class="btn btn-primary">Save profile</button></div>
    </form>
    <form class="card p-5" @submit.prevent="saveAvatar">
      <h2 class="mb-4 font-semibold">Avatar</h2>
      <div class="flex items-center gap-4">
        <img v-if="auth.user?.avatar_url" :src="auth.user.avatar_url" class="h-16 w-16 rounded-full object-cover" alt="Current avatar" />
        <div v-else class="grid h-16 w-16 place-items-center rounded-full bg-teal-100 text-xl font-bold text-teal-800">{{ auth.user?.name?.slice(0, 1) || 'P' }}</div>
        <label class="block flex-1"><span class="label mb-1">Upload image</span><input class="field" type="file" accept="image/*" @change="avatar = ($event.target as HTMLInputElement).files?.[0] || null" /></label>
      </div>
      <div class="mt-5 flex justify-end"><button class="btn btn-primary" :disabled="!avatar">Save avatar</button></div>
    </form>
    <form class="card p-5" @submit.prevent="savePassword">
      <h2 class="mb-4 font-semibold">Password</h2>
      <div class="grid gap-4">
        <label><span class="label mb-1">Current password</span><input v-model="password.current_password" class="field" type="password" required /></label>
        <label><span class="label mb-1">New password</span><input v-model="password.password" class="field" type="password" required /></label>
        <label><span class="label mb-1">Confirm new password</span><input v-model="password.password_confirmation" class="field" type="password" required /></label>
      </div>
      <div class="mt-5 flex justify-end"><button class="btn btn-primary">Change password</button></div>
    </form>
  </div>
</template>
