<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { api, unwrap } from '../api';
import { toast } from '../feedback';

type AppUser = {
  id: number;
  name: string;
  email: string;
  timezone: string;
  is_disabled: boolean;
  disabled_at: string | null;
  created_at: string;
};

const users = ref<AppUser[]>([]);
const loading = ref(true);
const showCreate = ref(false);
const editTarget = ref<AppUser | null>(null);
const resetTarget = ref<AppUser | null>(null);
const showPassword = ref(false);
const showPasswordConfirm = ref(false);
const showResetPassword = ref(false);
const showResetPasswordConfirm = ref(false);

const createForm = ref({ name: '', email: '', password: '', password_confirmation: '', timezone: 'Asia/Jakarta' });
const editForm = ref({ name: '', email: '', timezone: '' });
const resetForm = ref({ password: '', password_confirmation: '' });

function generatePassword(): string {
  const chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%';
  return Array.from({ length: 14 }, () => chars[Math.floor(Math.random() * chars.length)]).join('');
}

function fillGeneratedPassword() {
  const pw = generatePassword();
  createForm.value.password = pw;
  createForm.value.password_confirmation = pw;
  showPassword.value = true;
}

function fillGeneratedResetPassword() {
  const pw = generatePassword();
  resetForm.value.password = pw;
  resetForm.value.password_confirmation = pw;
  showResetPassword.value = true;
}

async function load() {
  loading.value = true;
  try {
    const data = await api.get('/api/admin/users').then(unwrap);
    users.value = data.users;
  } finally {
    loading.value = false;
  }
}

async function createUser() {
  try {
    const data = await api.post('/api/admin/users', createForm.value).then(unwrap);
    users.value.push(data.user);
    showCreate.value = false;
    showPassword.value = false;
    createForm.value = { name: '', email: '', password: '', password_confirmation: '', timezone: 'Asia/Jakarta' };
    toast({ tone: 'success', title: 'User dibuat', message: `Akun ${data.user.name} berhasil dibuat.` });
  } catch (e: any) {
    toast({ tone: 'error', title: 'Gagal', message: e?.response?.data?.message || 'Gagal membuat user.' });
  }
}

function openEdit(user: AppUser) {
  editTarget.value = user;
  editForm.value = { name: user.name, email: user.email, timezone: user.timezone };
}

async function saveEdit() {
  if (!editTarget.value) return;
  try {
    const data = await api.patch(`/api/admin/users/${editTarget.value.id}`, editForm.value).then(unwrap);
    const idx = users.value.findIndex((u) => u.id === editTarget.value!.id);
    if (idx !== -1) users.value[idx] = data.user;
    editTarget.value = null;
    toast({ tone: 'success', title: 'User diperbarui' });
  } catch (e: any) {
    toast({ tone: 'error', title: 'Gagal', message: e?.response?.data?.message || 'Gagal memperbarui user.' });
  }
}

function openResetPassword(user: AppUser) {
  resetTarget.value = user;
  resetForm.value = { password: '', password_confirmation: '' };
}

async function saveResetPassword() {
  if (!resetTarget.value) return;
  try {
    await api.post(`/api/admin/users/${resetTarget.value.id}/reset-password`, resetForm.value);
    resetTarget.value = null;
    showResetPassword.value = false;
    resetForm.value = { password: '', password_confirmation: '' };
    toast({ tone: 'success', title: 'Password direset' });
  } catch (e: any) {
    toast({ tone: 'error', title: 'Gagal', message: e?.response?.data?.message || 'Gagal reset password.' });
  }
}

async function toggleDisable(user: AppUser) {
  const action = user.is_disabled ? 'enable' : 'disable';
  const label = user.is_disabled ? 'mengaktifkan' : 'menonaktifkan';
  if (!confirm(`Yakin ${label} akun ${user.name}?`)) return;
  try {
    const data = await api.post(`/api/admin/users/${user.id}/${action}`).then(unwrap);
    const idx = users.value.findIndex((u) => u.id === user.id);
    if (idx !== -1) users.value[idx] = data.user;
    toast({ tone: 'success', title: `User ${user.is_disabled ? 'diaktifkan' : 'dinonaktifkan'}` });
  } catch (e: any) {
    toast({ tone: 'error', title: 'Gagal', message: e?.response?.data?.message || 'Gagal mengubah status user.' });
  }
}

async function deleteUser(user: AppUser) {
  if (!confirm(`Hapus permanen akun ${user.name} beserta seluruh datanya? Tindakan ini tidak bisa dibatalkan.`)) return;
  try {
    await api.delete(`/api/admin/users/${user.id}`);
    users.value = users.value.filter((u) => u.id !== user.id);
    toast({ tone: 'success', title: 'User dihapus' });
  } catch (e: any) {
    toast({ tone: 'error', title: 'Gagal', message: e?.response?.data?.message || 'Gagal menghapus user.' });
  }
}

onMounted(load);
</script>

<template>
  <div class="mx-auto max-w-4xl px-4 py-8">
    <div class="mb-6 flex items-center justify-between">
      <div>
        <h1 class="text-xl font-extrabold tracking-tight text-slate-900 dark:text-zinc-100">User Management</h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-zinc-500">Kelola akun pengguna ProgressOS</p>
      </div>
      <button class="btn btn-primary" @click="showCreate = true">+ Buat User</button>
    </div>

    <!-- User list -->
    <div class="card overflow-hidden p-0">
      <div v-if="loading" class="py-12 text-center text-sm text-slate-400 dark:text-zinc-600">Memuat...</div>
      <div v-else-if="users.length === 0" class="py-12 text-center text-sm text-slate-400 dark:text-zinc-600">Belum ada user.</div>
      <table v-else class="w-full text-sm">
        <thead>
          <tr class="border-b border-slate-100 dark:border-zinc-800">
            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-zinc-500">Nama</th>
            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-zinc-500">Email</th>
            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-zinc-500">Status</th>
            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-zinc-500">Dibuat</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="user in users" :key="user.id" class="border-b border-slate-50 last:border-0 dark:border-zinc-800/60">
            <td class="px-4 py-3 font-semibold text-slate-800 dark:text-zinc-200">{{ user.name }}</td>
            <td class="px-4 py-3 text-slate-600 dark:text-zinc-400">{{ user.email }}</td>
            <td class="px-4 py-3">
              <span
                class="pill"
                :class="user.is_disabled ? 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400' : 'bg-teal-50 text-teal-700 dark:bg-teal-900/20 dark:text-teal-400'"
              >
                {{ user.is_disabled ? 'Nonaktif' : 'Aktif' }}
              </span>
            </td>
            <td class="px-4 py-3 text-slate-400 dark:text-zinc-600">{{ new Date(user.created_at).toLocaleDateString('id-ID') }}</td>
            <td class="px-4 py-3">
              <div class="flex items-center justify-end gap-2">
                <button class="btn btn-muted py-1 text-xs" @click="openEdit(user)">Edit</button>
                <button class="btn btn-muted py-1 text-xs" @click="openResetPassword(user)">Reset PW</button>
                <button
                  class="btn py-1 text-xs"
                  :class="user.is_disabled ? 'btn-muted text-teal-700 dark:text-teal-400' : 'btn-muted text-amber-700 dark:text-amber-400'"
                  @click="toggleDisable(user)"
                >
                  {{ user.is_disabled ? 'Aktifkan' : 'Nonaktifkan' }}
                </button>
                <button class="btn btn-muted py-1 text-xs text-red-600 dark:text-red-400" @click="deleteUser(user)">Hapus</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal: Buat User -->
    <div v-if="showCreate" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-[2px]" @click.self="showCreate = false">
      <div class="card w-full max-w-md overflow-hidden p-0">
        <div class="border-b border-slate-100 bg-slate-50/70 px-5 py-4 dark:border-zinc-800 dark:bg-zinc-800/50">
          <h2 class="font-extrabold">Buat User Baru</h2>
        </div>
        <form class="grid gap-4 p-5 sm:grid-cols-2" @submit.prevent="createUser">
          <label class="sm:col-span-2"><span class="label mb-1">Nama</span><input v-model="createForm.name" class="field" required /></label>
          <label class="sm:col-span-2"><span class="label mb-1">Email</span><input v-model="createForm.email" type="email" class="field" required /></label>
          <div class="sm:col-span-2">
            <div class="mb-1 flex items-center justify-between">
              <span class="label">Password</span>
              <button type="button" class="btn btn-muted py-0.5 text-xs" @click="fillGeneratedPassword">Generate</button>
            </div>
            <div class="relative">
              <input v-model="createForm.password" :type="showPassword ? 'text' : 'password'" class="field pr-9" required />
              <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 p-1 text-slate-400 hover:text-slate-600 dark:hover:text-zinc-300" @click="showPassword = !showPassword">
                <svg v-if="!showPassword" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg v-else class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
              </button>
            </div>
            <div v-if="showPassword && createForm.password" class="mt-1 flex items-center gap-2 rounded bg-slate-50 px-3 py-2 font-mono text-sm dark:bg-zinc-800">
              <span class="flex-1 select-all break-all text-slate-800 dark:text-zinc-200">{{ createForm.password }}</span>
            </div>
          </div>
          <div class="sm:col-span-2">
            <span class="label mb-1">Konfirmasi Password</span>
            <div class="relative">
              <input v-model="createForm.password_confirmation" :type="showPasswordConfirm ? 'text' : 'password'" class="field pr-9" required />
              <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 p-1 text-slate-400 hover:text-slate-600 dark:hover:text-zinc-300" @click="showPasswordConfirm = !showPasswordConfirm">
                <svg v-if="!showPasswordConfirm" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg v-else class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
              </button>
            </div>
          </div>
          <label class="sm:col-span-2">
            <span class="label mb-1">Timezone</span>
            <select v-model="createForm.timezone" class="field">
              <option value="Asia/Jakarta">Asia/Jakarta</option>
              <option value="Asia/Makassar">Asia/Makassar</option>
              <option value="Asia/Jayapura">Asia/Jayapura</option>
              <option value="UTC">UTC</option>
            </select>
          </label>
          <div class="flex justify-end gap-2 sm:col-span-2">
            <button type="button" class="btn btn-muted" @click="showCreate = false; showPassword = false">Batal</button>
            <button type="submit" class="btn btn-primary">Buat</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal: Edit User -->
    <div v-if="editTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-[2px]" @click.self="editTarget = null">
      <div class="card w-full max-w-md overflow-hidden p-0">
        <div class="border-b border-slate-100 bg-slate-50/70 px-5 py-4 dark:border-zinc-800 dark:bg-zinc-800/50">
          <h2 class="font-extrabold">Edit User</h2>
        </div>
        <form class="grid gap-4 p-5 sm:grid-cols-2" @submit.prevent="saveEdit">
          <label><span class="label mb-1">Nama</span><input v-model="editForm.name" class="field" required /></label>
          <label><span class="label mb-1">Email</span><input v-model="editForm.email" type="email" class="field" required /></label>
          <label class="sm:col-span-2">
            <span class="label mb-1">Timezone</span>
            <select v-model="editForm.timezone" class="field">
              <option value="Asia/Jakarta">Asia/Jakarta</option>
              <option value="Asia/Makassar">Asia/Makassar</option>
              <option value="Asia/Jayapura">Asia/Jayapura</option>
              <option value="UTC">UTC</option>
            </select>
          </label>
          <div class="flex justify-end gap-2 sm:col-span-2">
            <button type="button" class="btn btn-muted" @click="editTarget = null">Batal</button>
            <button type="submit" class="btn btn-primary">Simpan</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal: Reset Password -->
    <div v-if="resetTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-[2px]" @click.self="resetTarget = null">
      <div class="card w-full max-w-sm overflow-hidden p-0">
        <div class="border-b border-slate-100 bg-slate-50/70 px-5 py-4 dark:border-zinc-800 dark:bg-zinc-800/50">
          <h2 class="font-extrabold">Reset Password</h2>
          <p class="mt-0.5 text-sm font-medium text-slate-500">{{ resetTarget.name }}</p>
        </div>
        <form class="grid gap-4 p-5" @submit.prevent="saveResetPassword">
          <div>
            <div class="mb-1 flex items-center justify-between">
              <span class="label">Password Baru</span>
              <button type="button" class="btn btn-muted py-0.5 text-xs" @click="fillGeneratedResetPassword">Generate</button>
            </div>
            <div class="relative">
              <input v-model="resetForm.password" :type="showResetPassword ? 'text' : 'password'" class="field pr-9" required />
              <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 p-1 text-slate-400 hover:text-slate-600 dark:hover:text-zinc-300" @click="showResetPassword = !showResetPassword">
                <svg v-if="!showResetPassword" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg v-else class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
              </button>
            </div>
            <div v-if="showResetPassword && resetForm.password" class="mt-1 flex items-center gap-2 rounded bg-slate-50 px-3 py-2 font-mono text-sm dark:bg-zinc-800">
              <span class="flex-1 select-all break-all text-slate-800 dark:text-zinc-200">{{ resetForm.password }}</span>
            </div>
          </div>
          <div>
            <span class="label mb-1">Konfirmasi Password</span>
            <div class="relative">
              <input v-model="resetForm.password_confirmation" :type="showResetPasswordConfirm ? 'text' : 'password'" class="field pr-9" required />
              <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 p-1 text-slate-400 hover:text-slate-600 dark:hover:text-zinc-300" @click="showResetPasswordConfirm = !showResetPasswordConfirm">
                <svg v-if="!showResetPasswordConfirm" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg v-else class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
              </button>
            </div>
          </div>
          <div class="flex justify-end gap-2">
            <button type="button" class="btn btn-muted" @click="resetTarget = null; showResetPassword = false; showResetPasswordConfirm = false">Batal</button>
            <button type="submit" class="btn btn-primary">Reset</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
