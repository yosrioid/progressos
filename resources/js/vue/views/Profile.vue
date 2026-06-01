<script setup lang="ts">
import { computed, ref } from 'vue';
import { toast } from '../feedback';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
const profile = ref({ name: auth.user?.name || '', email: auth.user?.email || '', timezone: auth.user?.timezone || 'Asia/Jakarta', theme: auth.user?.theme || 'system' });
const password = ref({ current_password: '', password: '', password_confirmation: '' });
const avatar = ref<File | null>(null);
const avatarPreview = ref('');
const cropZoom = ref(1.15);
const cropX = ref(0);
const cropY = ref(0);
const message = ref('');
const error = ref('');
const avatarStyle = computed(() => ({
  transform: `translate(calc(-50% + ${cropX.value}px), calc(-50% + ${cropY.value}px)) scale(${cropZoom.value})`,
}));

async function saveProfile() {
  message.value = '';
  error.value = '';
  try {
    await auth.updateProfile(profile.value);
    message.value = 'Profile saved.';
    toast({ tone: 'success', title: 'Profile saved', message: 'Your account details were updated.' });
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
    toast({ tone: 'success', title: 'Password changed', message: 'Use the new password next time you sign in.' });
  } catch (e: any) {
    error.value = e.response?.data?.message || 'Could not change password.';
  }
}

async function saveAvatar() {
  if (!avatar.value || !avatarPreview.value) return;
  message.value = '';
  error.value = '';
  const payload = new FormData();
  const cropped = await croppedAvatarBlob();
  payload.append('avatar', new File([cropped], 'avatar.jpg', { type: 'image/jpeg' }));
  try {
    await auth.updateAvatar(payload);
    avatar.value = null;
    avatarPreview.value = '';
    cropZoom.value = 1.15;
    cropX.value = 0;
    cropY.value = 0;
    message.value = 'Avatar updated.';
    toast({ tone: 'success', title: 'Avatar updated', message: 'Your profile image has been saved.' });
  } catch (e: any) {
    error.value = e.response?.data?.message || 'Could not update avatar.';
  }
}

function selectAvatar(event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0] || null;
  avatar.value = file;
  if (avatarPreview.value) URL.revokeObjectURL(avatarPreview.value);
  avatarPreview.value = file ? URL.createObjectURL(file) : '';
  cropZoom.value = 1.15;
  cropX.value = 0;
  cropY.value = 0;
}

async function croppedAvatarBlob(): Promise<Blob> {
  const image = new Image();
  image.src = avatarPreview.value;
  await new Promise((resolve, reject) => {
    image.onload = resolve;
    image.onerror = reject;
  });
  const outputSize = 512;
  const previewSize = 256;
  const canvas = document.createElement('canvas');
  canvas.width = outputSize;
  canvas.height = outputSize;
  const context = canvas.getContext('2d');
  if (!context) throw new Error('Canvas is not supported.');
  context.fillStyle = '#f8fafc';
  context.fillRect(0, 0, outputSize, outputSize);

  const baseScale = Math.max(previewSize / image.width, previewSize / image.height);
  const renderedWidth = image.width * baseScale * cropZoom.value;
  const renderedHeight = image.height * baseScale * cropZoom.value;
  const scaleToOutput = outputSize / previewSize;
  const dx = ((previewSize - renderedWidth) / 2 + cropX.value) * scaleToOutput;
  const dy = ((previewSize - renderedHeight) / 2 + cropY.value) * scaleToOutput;

  context.drawImage(image, dx, dy, renderedWidth * scaleToOutput, renderedHeight * scaleToOutput);
  return await new Promise((resolve) => canvas.toBlob((blob) => resolve(blob as Blob), 'image/jpeg', 0.9));
}
</script>

<template>
  <div class="mb-5">
    <p class="text-sm font-extrabold text-teal-700">Account</p>
    <h1 class="mt-1 text-3xl font-extrabold tracking-tight">Profile & Settings</h1>
    <p class="mt-1 text-sm font-medium text-slate-500">Manage identity, preferences, avatar, and password from one place.</p>
  </div>
  <p v-if="message" class="mb-4 rounded-xl border border-teal-200 bg-teal-50 p-3 text-sm font-medium text-teal-800">{{ message }}</p>
  <p v-if="error" class="mb-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm font-medium text-red-700">{{ error }}</p>
  <div class="grid gap-5 xl:grid-cols-[22rem_1fr]">
    <aside class="card h-fit overflow-hidden p-0">
      <div class="bg-gradient-to-r from-teal-50 to-sky-50 p-5">
        <div class="flex items-center gap-4">
          <img v-if="auth.user?.avatar_url" :src="auth.user.avatar_url" class="h-16 w-16 rounded-2xl object-cover ring-4 ring-white" alt="Current avatar" />
          <div v-else class="grid h-16 w-16 place-items-center rounded-2xl bg-teal-700 text-2xl font-extrabold text-white">{{ auth.user?.name?.slice(0, 1) || 'P' }}</div>
          <div class="min-w-0">
            <p class="truncate font-extrabold text-slate-900">{{ auth.user?.name }}</p>
            <p class="truncate text-sm font-semibold text-slate-500">{{ auth.user?.email }}</p>
          </div>
        </div>
      </div>
      <div class="space-y-3 p-5 text-sm font-semibold text-slate-600">
        <div class="flex justify-between"><span>Timezone</span><span class="text-slate-900">{{ profile.timezone }}</span></div>
        <div class="flex justify-between"><span>Theme</span><span class="capitalize text-slate-900">{{ profile.theme }}</span></div>
      </div>
    </aside>
    <div class="grid gap-5">
    <form class="card overflow-hidden p-0" @submit.prevent="saveProfile">
      <div class="border-b border-slate-100 bg-slate-50/70 px-5 py-4">
        <h2 class="font-extrabold">Profile</h2>
        <p class="text-sm font-medium text-slate-500">Keep account details accurate for reports and local preferences.</p>
      </div>
      <div class="grid gap-4 p-5 md:grid-cols-2">
        <label><span class="label mb-1">Name</span><input v-model="profile.name" class="field" required /></label>
        <label><span class="label mb-1">Email</span><input v-model="profile.email" class="field" type="email" required /></label>
        <label><span class="label mb-1">Timezone</span><input v-model="profile.timezone" class="field" required /></label>
        <div>
          <span class="label mb-1">Theme</span>
          <div class="inline-flex rounded-xl border border-slate-200 bg-white p-1">
            <button v-for="option in ['light', 'dark', 'system']" :key="option" type="button" class="rounded-lg px-3 py-2 text-sm font-bold capitalize" :class="profile.theme === option ? 'bg-slate-900 text-white' : 'text-slate-500'" @click="profile.theme = option">{{ option }}</button>
          </div>
        </div>
      </div>
      <div class="flex justify-end border-t border-slate-100 bg-slate-50/70 px-5 py-4"><button class="btn btn-primary">Save profile</button></div>
    </form>
    <form class="card overflow-hidden p-0" @submit.prevent="saveAvatar">
      <div class="border-b border-slate-100 bg-slate-50/70 px-5 py-4">
        <h2 class="font-extrabold">Avatar</h2>
        <p class="text-sm font-medium text-slate-500">Crop and position your profile photo before saving it.</p>
      </div>
      <div class="grid gap-5 p-5 lg:grid-cols-[18rem_1fr]">
        <div class="space-y-4">
          <div class="relative mx-auto h-64 w-64 overflow-hidden rounded-[2rem] border border-slate-200 bg-slate-100 shadow-inner">
            <img
              v-if="avatarPreview"
              :src="avatarPreview"
              class="absolute left-1/2 top-1/2 h-full w-full select-none object-cover"
              :style="avatarStyle"
              alt="Avatar crop preview"
              draggable="false"
            />
            <img v-else-if="auth.user?.avatar_url" :src="auth.user.avatar_url" class="h-full w-full object-cover" alt="Current avatar" />
            <div v-else class="grid h-full w-full place-items-center bg-teal-100 text-5xl font-extrabold text-teal-800">{{ auth.user?.name?.slice(0, 1) || 'P' }}</div>
            <div class="pointer-events-none absolute inset-0 rounded-[2rem] ring-4 ring-white/80"></div>
          </div>
          <p class="text-center text-xs font-semibold text-slate-500">Saved image will be cropped as a square avatar.</p>
        </div>
        <div class="space-y-4">
          <label class="block rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-4">
            <span class="label mb-1">Upload image</span>
            <input class="field bg-white" type="file" accept="image/*" @change="selectAvatar" />
          </label>
          <div class="grid gap-4 rounded-2xl border border-slate-200 bg-white p-4" :class="!avatarPreview ? 'opacity-60' : ''">
            <label>
              <span class="label mb-2">Zoom</span>
              <input v-model.number="cropZoom" class="w-full accent-teal-700" type="range" min="1" max="3" step="0.05" :disabled="!avatarPreview" />
            </label>
            <label>
              <span class="label mb-2">Horizontal position</span>
              <input v-model.number="cropX" class="w-full accent-teal-700" type="range" min="-100" max="100" step="1" :disabled="!avatarPreview" />
            </label>
            <label>
              <span class="label mb-2">Vertical position</span>
              <input v-model.number="cropY" class="w-full accent-teal-700" type="range" min="-100" max="100" step="1" :disabled="!avatarPreview" />
            </label>
          </div>
        </div>
      </div>
      <div class="flex justify-end border-t border-slate-100 bg-slate-50/70 px-5 py-4"><button class="btn btn-primary" :disabled="!avatar">Save avatar</button></div>
    </form>
    <form class="card overflow-hidden p-0" @submit.prevent="savePassword">
      <div class="border-b border-slate-100 bg-slate-50/70 px-5 py-4">
        <h2 class="font-extrabold">Password</h2>
        <p class="text-sm font-medium text-slate-500">Use a strong password and update it when access changes.</p>
      </div>
      <div class="grid gap-4 p-5 md:grid-cols-3">
        <label><span class="label mb-1">Current password</span><input v-model="password.current_password" class="field" type="password" required /></label>
        <label><span class="label mb-1">New password</span><input v-model="password.password" class="field" type="password" required /></label>
        <label><span class="label mb-1">Confirm new password</span><input v-model="password.password_confirmation" class="field" type="password" required /></label>
      </div>
      <div class="flex justify-end border-t border-slate-100 bg-slate-50/70 px-5 py-4"><button class="btn btn-primary">Change password</button></div>
    </form>
    </div>
  </div>
</template>
