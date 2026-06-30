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

const createForm = ref({ name: '', email: '', password: '', password_confirmation: '', timezone: 'Asia/Jakarta' });
const editForm = ref({ name: '', email: '', timezone: '' });
const resetForm = ref({ password: '', password_confirmation: '' });

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
    createForm.value = { name: '', email: '', password: '', password_confirmation: '', timezone: 'Asia/Jakarta' };
    toast('User berhasil dibuat.', 'success');
  } catch (e: any) {
    toast(e?.response?.data?.message || 'Gagal membuat user.', 'error');
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
    toast('User diperbarui.', 'success');
  } catch (e: any) {
    toast(e?.response?.data?.message || 'Gagal memperbarui user.', 'error');
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
    toast('Password berhasil direset.', 'success');
  } catch (e: any) {
    toast(e?.response?.data?.message || 'Gagal reset password.', 'error');
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
    toast(`User ${user.is_disabled ? 'diaktifkan' : 'dinonaktifkan'}.`, 'success');
  } catch (e: any) {
    toast(e?.response?.data?.message || 'Gagal mengubah status user.', 'error');
  }
}

async function deleteUser(user: AppUser) {
  if (!confirm(`Hapus permanen akun ${user.name} beserta seluruh datanya? Tindakan ini tidak bisa dibatalkan.`)) return;
  try {
    await api.delete(`/api/admin/users/${user.id}`);
    users.value = users.value.filter((u) => u.id !== user.id);
    toast('User dihapus.', 'success');
  } catch (e: any) {
    toast(e?.response?.data?.message || 'Gagal menghapus user.', 'error');
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
      <div class="card w-full max-w-md">
        <h2 class="mb-4 text-base font-extrabold text-slate-900 dark:text-zinc-100">Buat User Baru</h2>
        <form class="space-y-3" @submit.prevent="createUser">
          <div class="field">
            <label class="field-label">Nama</label>
            <input v-model="createForm.name" class="field-input" required />
          </div>
          <div class="field">
            <label class="field-label">Email</label>
            <input v-model="createForm.email" type="email" class="field-input" required />
          </div>
          <div class="field">
            <label class="field-label">Password</label>
            <input v-model="createForm.password" type="password" class="field-input" required />
          </div>
          <div class="field">
            <label class="field-label">Konfirmasi Password</label>
            <input v-model="createForm.password_confirmation" type="password" class="field-input" required />
          </div>
          <div class="field">
            <label class="field-label">Timezone</label>
            <input v-model="createForm.timezone" class="field-input" />
          </div>
          <div class="flex justify-end gap-2 pt-2">
            <button type="button" class="btn btn-muted" @click="showCreate = false">Batal</button>
            <button type="submit" class="btn btn-primary">Buat</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal: Edit User -->
    <div v-if="editTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-[2px]" @click.self="editTarget = null">
      <div class="card w-full max-w-md">
        <h2 class="mb-4 text-base font-extrabold text-slate-900 dark:text-zinc-100">Edit User</h2>
        <form class="space-y-3" @submit.prevent="saveEdit">
          <div class="field">
            <label class="field-label">Nama</label>
            <input v-model="editForm.name" class="field-input" required />
          </div>
          <div class="field">
            <label class="field-label">Email</label>
            <input v-model="editForm.email" type="email" class="field-input" required />
          </div>
          <div class="field">
            <label class="field-label">Timezone</label>
            <input v-model="editForm.timezone" class="field-input" />
          </div>
          <div class="flex justify-end gap-2 pt-2">
            <button type="button" class="btn btn-muted" @click="editTarget = null">Batal</button>
            <button type="submit" class="btn btn-primary">Simpan</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal: Reset Password -->
    <div v-if="resetTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-[2px]" @click.self="resetTarget = null">
      <div class="card w-full max-w-md">
        <h2 class="mb-4 text-base font-extrabold text-slate-900 dark:text-zinc-100">Reset Password — {{ resetTarget.name }}</h2>
        <form class="space-y-3" @submit.prevent="saveResetPassword">
          <div class="field">
            <label class="field-label">Password Baru</label>
            <input v-model="resetForm.password" type="password" class="field-input" required />
          </div>
          <div class="field">
            <label class="field-label">Konfirmasi Password</label>
            <input v-model="resetForm.password_confirmation" type="password" class="field-input" required />
          </div>
          <div class="flex justify-end gap-2 pt-2">
            <button type="button" class="btn btn-muted" @click="resetTarget = null">Batal</button>
            <button type="submit" class="btn btn-primary">Reset</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
