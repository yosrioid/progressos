<script setup lang="ts">
import { computed, ref } from 'vue';
import { useRoute } from 'vue-router';
import { api, unwrap } from '../api';
import { toast } from '../feedback';
import { useAuthStore } from '../stores/auth';
import { timezones, useConfigurationStore } from '../stores/configuration';

const auth = useAuthStore();
const configuration = useConfigurationStore();
const profile = ref({ name: auth.user?.name || '', email: auth.user?.email || '', timezone: auth.user?.timezone || configuration.timezone || 'Asia/Jakarta', theme: auth.user?.theme || 'system' });
const password = ref({ current_password: '', password: '', password_confirmation: '' });
const avatar = ref<File | null>(null);
const avatarPreview = ref('');
const avatarLoadFailed = ref(false);
const cropOpen = ref(false);
const cropArea = ref<HTMLElement | null>(null);
const cropBox = ref({ x: 56, y: 56, size: 176 });
const cropDrag = ref<{ mode: 'move' | 'resize'; startX: number; startY: number; original: { x: number; y: number; size: number } } | null>(null);
const message = ref('');
const error = ref('');
const unlinking = ref(false);
const route = useRoute();
const googleLinked = computed(() => Boolean(auth.user?.google_id));

// Handle redirect from Google OAuth callback
if (route.query.success === 'google_linked') {
  toast({ tone: 'success', title: 'Google ditautkan', message: 'Akun Google berhasil dihubungkan ke akun ini.' });
}
if (route.query.error === 'google_taken') {
  toast({ tone: 'error', title: 'Gagal menautkan', message: 'Akun Google tersebut sudah ditautkan ke akun lain.' });
}
if (route.query.error === 'google_failed') {
  toast({ tone: 'error', title: 'Gagal', message: 'Proses autentikasi Google gagal. Coba lagi.' });
}

function linkGoogle() {
  window.location.href = '/profile/google/link';
}

async function unlinkGoogle() {
  unlinking.value = true;
  try {
    const data: any = await api.delete('/api/v1/profile/google-link').then(unwrap);
    auth.user = data.user;
    toast({ tone: 'success', title: 'Tautan dihapus', message: 'Akun Google berhasil dilepas.' });
  } catch (e: any) {
    toast({ tone: 'error', title: 'Gagal', message: e.response?.data?.message || 'Tidak bisa menghapus tautan.' });
  } finally {
    unlinking.value = false;
  }
}

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
    avatarLoadFailed.value = false;
    avatar.value = null;
    avatarPreview.value = '';
    cropOpen.value = false;
    cropBox.value = { x: 56, y: 56, size: 176 };
    message.value = 'Avatar updated.';
    toast({ tone: 'success', title: 'Avatar updated', message: 'Your profile image has been saved.' });
  } catch (e: any) {
    error.value = e.response?.data?.message || 'Could not update avatar.';
  }
}

function avatarInitial() {
  return auth.user?.name?.slice(0, 1) || 'P';
}

function selectAvatar(event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0] || null;
  avatar.value = file;
  if (avatarPreview.value) URL.revokeObjectURL(avatarPreview.value);
  avatarPreview.value = file ? URL.createObjectURL(file) : '';
  cropBox.value = { x: 56, y: 56, size: 176 };
  cropOpen.value = Boolean(file);
}

function closeCrop() {
  cropOpen.value = false;
  avatar.value = null;
  if (avatarPreview.value) URL.revokeObjectURL(avatarPreview.value);
  avatarPreview.value = '';
}

function useCrop() {
  cropOpen.value = false;
}

function startCropDrag(event: PointerEvent, mode: 'move' | 'resize') {
  event.preventDefault();
  cropDrag.value = { mode, startX: event.clientX, startY: event.clientY, original: { ...cropBox.value } };
  window.addEventListener('pointermove', moveCrop);
  window.addEventListener('pointerup', stopCropDrag, { once: true });
}

function moveCrop(event: PointerEvent) {
  if (!cropDrag.value || !cropArea.value) return;
  const bounds = cropArea.value.getBoundingClientRect();
  const deltaX = event.clientX - cropDrag.value.startX;
  const deltaY = event.clientY - cropDrag.value.startY;
  const original = cropDrag.value.original;

  if (cropDrag.value.mode === 'resize') {
    const size = Math.max(96, Math.min(bounds.width - original.x, bounds.height - original.y, original.size + Math.max(deltaX, deltaY)));
    cropBox.value = { ...cropBox.value, size };
    return;
  }

  cropBox.value = {
    ...cropBox.value,
    x: Math.max(0, Math.min(bounds.width - original.size, original.x + deltaX)),
    y: Math.max(0, Math.min(bounds.height - original.size, original.y + deltaY)),
  };
}

function stopCropDrag() {
  window.removeEventListener('pointermove', moveCrop);
  cropDrag.value = null;
}

async function croppedAvatarBlob(): Promise<Blob> {
  const image = new Image();
  image.src = avatarPreview.value;
  await new Promise((resolve, reject) => {
    image.onload = resolve;
    image.onerror = reject;
  });
  const outputSize = 512;
  const bounds = cropArea.value?.getBoundingClientRect();
  const previewWidth = bounds?.width || 288;
  const previewHeight = bounds?.height || 288;
  const canvas = document.createElement('canvas');
  canvas.width = outputSize;
  canvas.height = outputSize;
  const context = canvas.getContext('2d');
  if (!context) throw new Error('Canvas is not supported.');
  context.fillStyle = '#f8fafc';
  context.fillRect(0, 0, outputSize, outputSize);

  const imageScale = Math.min(previewWidth / image.width, previewHeight / image.height);
  const renderedWidth = image.width * imageScale;
  const renderedHeight = image.height * imageScale;
  const offsetX = (previewWidth - renderedWidth) / 2;
  const offsetY = (previewHeight - renderedHeight) / 2;
  const sourceX = Math.max(0, (cropBox.value.x - offsetX) / imageScale);
  const sourceY = Math.max(0, (cropBox.value.y - offsetY) / imageScale);
  const sourceSize = Math.min(image.width - sourceX, image.height - sourceY, cropBox.value.size / imageScale);

  context.drawImage(image, sourceX, sourceY, sourceSize, sourceSize, 0, 0, outputSize, outputSize);
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
      <div class="bg-gradient-to-r from-teal-50 to-sky-50 p-5 dark:from-teal-900/20 dark:to-sky-900/20">
        <div class="flex items-center gap-4">
          <img v-if="auth.user?.avatar_url && !avatarLoadFailed" :src="auth.user.avatar_url" class="h-16 w-16 rounded-2xl object-cover ring-4 ring-white dark:ring-zinc-700" alt="Current avatar" @error="avatarLoadFailed = true" />
          <div v-else class="grid h-16 w-16 place-items-center rounded-2xl bg-teal-700 text-2xl font-extrabold text-white">{{ avatarInitial() }}</div>
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
        <label>
          <span class="label mb-1">Timezone</span>
          <select v-model="profile.timezone" class="field" required>
            <option v-for="timezone in timezones" :key="timezone" :value="timezone">{{ timezone.replace('_', ' ') }}</option>
          </select>
        </label>
        <div>
          <span class="label mb-1">Theme</span>
          <div class="inline-flex rounded-xl border border-slate-200 bg-white p-1 dark:border-zinc-700 dark:bg-zinc-800">
            <button v-for="option in ['light', 'dark', 'system']" :key="option" type="button" class="rounded-lg px-3 py-2 text-sm font-bold capitalize" :class="profile.theme === option ? 'bg-slate-900 text-white' : 'text-slate-500'" @click="profile.theme = option">{{ option }}</button>
          </div>
        </div>
      </div>
      <div class="flex justify-end border-t border-slate-100 bg-slate-50/70 px-5 py-4"><button class="btn btn-primary">Save profile</button></div>
    </form>
    <form class="card overflow-hidden p-0" @submit.prevent="saveAvatar">
      <div class="border-b border-slate-100 bg-slate-50/70 px-5 py-4">
        <h2 class="font-extrabold">Avatar</h2>
        <p class="text-sm font-medium text-slate-500">Upload a photo, crop it visually, then save it as your profile avatar.</p>
      </div>
      <div class="grid gap-5 p-5 lg:grid-cols-[10rem_1fr]">
        <div class="relative h-32 w-32 overflow-hidden rounded-3xl border border-slate-200 bg-slate-100">
          <img v-if="avatarPreview" :src="avatarPreview" class="h-full w-full object-cover" alt="Selected avatar preview" />
          <img v-else-if="auth.user?.avatar_url && !avatarLoadFailed" :src="auth.user.avatar_url" class="h-full w-full object-cover" alt="Current avatar" @error="avatarLoadFailed = true" />
          <div v-else class="grid h-full w-full place-items-center bg-teal-100 text-4xl font-extrabold text-teal-800">{{ avatarInitial() }}</div>
        </div>
        <div class="space-y-3">
          <label class="block rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-4">
            <span class="label mb-1">Upload image</span>
            <input class="field bg-white" type="file" accept="image/*" @change="selectAvatar" />
          </label>
          <p class="text-sm font-medium text-slate-500">After choosing an image, a crop editor opens so you can drag the square over the exact area to use.</p>
          <button v-if="avatarPreview" type="button" class="btn btn-muted" @click="cropOpen = true">Edit crop</button>
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

    <!-- Connected accounts -->
    <div class="card overflow-hidden p-0">
      <div class="border-b border-slate-100 bg-slate-50/70 px-5 py-4">
        <h2 class="font-extrabold">Tautan Akun</h2>
        <p class="text-sm font-medium text-slate-500">Kelola metode login yang terhubung ke akun ini.</p>
      </div>
      <div class="p-5">
        <div class="flex items-center justify-between gap-4 rounded-xl border border-slate-200 p-4">
          <div class="flex items-center gap-3">
            <div class="grid h-9 w-9 shrink-0 place-items-center rounded-xl border border-slate-200">
              <svg class="h-5 w-5" viewBox="0 0 24 24">
                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z"/>
                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
              </svg>
            </div>
            <div>
              <p class="font-bold text-slate-900">Google</p>
              <p class="text-sm text-slate-500">{{ googleLinked ? auth.user?.email : 'Belum ditautkan' }}</p>
            </div>
          </div>
          <button
            v-if="!googleLinked"
            class="btn btn-muted text-sm"
            @click="linkGoogle"
          >
            Tautkan
          </button>
          <button
            v-else
            class="btn border border-red-200 bg-red-50 text-sm font-bold text-red-700 hover:bg-red-100"
            :disabled="unlinking"
            @click="unlinkGoogle"
          >
            {{ unlinking ? 'Menghapus...' : 'Hapus tautan' }}
          </button>
        </div>
      </div>
    </div>

    </div>
  </div>
  <div v-if="cropOpen" class="fixed inset-0 z-50 grid place-items-center bg-slate-950/50 p-4 backdrop-blur-[2px]" role="dialog" aria-modal="true" aria-labelledby="avatar-crop-title">
    <section class="card w-full max-w-2xl overflow-hidden p-0 shadow-2xl shadow-slate-950/20">
      <div class="flex items-center justify-between gap-3 border-b border-slate-100 bg-slate-50/80 px-5 py-4">
        <div>
          <p class="text-xs font-extrabold uppercase text-teal-700">Avatar</p>
          <h2 id="avatar-crop-title" class="text-xl font-extrabold">Crop photo</h2>
        </div>
        <button type="button" class="btn btn-muted" @click="closeCrop">Cancel</button>
      </div>
      <div class="grid gap-5 p-5 lg:grid-cols-[1fr_13rem]">
        <div
          ref="cropArea"
          class="relative mx-auto h-80 w-full max-w-md overflow-hidden rounded-3xl border border-slate-200 bg-slate-100 shadow-inner"
        >
          <img :src="avatarPreview" class="h-full w-full select-none object-contain" alt="Avatar crop preview" draggable="false" />
          <div class="absolute inset-0 bg-slate-950/35"></div>
          <div
            class="absolute cursor-move touch-none border-2 border-white bg-white/10 shadow-[0_0_0_9999px_rgb(15_23_42/0.42)] ring-2 ring-teal-400"
            :style="{ left: `${cropBox.x}px`, top: `${cropBox.y}px`, width: `${cropBox.size}px`, height: `${cropBox.size}px` }"
            data-testid="avatar-crop-box"
            @pointerdown="startCropDrag($event, 'move')"
          >
            <span class="absolute left-1/3 top-0 h-full w-px bg-white/70"></span>
            <span class="absolute left-2/3 top-0 h-full w-px bg-white/70"></span>
            <span class="absolute left-0 top-1/3 h-px w-full bg-white/70"></span>
            <span class="absolute left-0 top-2/3 h-px w-full bg-white/70"></span>
            <button
              type="button"
              class="absolute -bottom-3 -right-3 h-7 w-7 cursor-nwse-resize rounded-full border-2 border-white bg-teal-700 shadow-lg"
              aria-label="Resize crop"
              data-testid="avatar-crop-resize"
              @pointerdown.stop="startCropDrag($event, 'resize')"
            ></button>
          </div>
        </div>
        <div class="space-y-3">
          <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
            <h3 class="font-extrabold text-slate-900">How it works</h3>
            <p class="mt-2 text-sm font-medium leading-6 text-slate-500">Drag the white square to the area you want. Use the corner handle to resize the crop.</p>
          </div>
          <button type="button" class="btn btn-primary w-full" @click="useCrop">Use crop</button>
        </div>
      </div>
    </section>
  </div>
</template>
