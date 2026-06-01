<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { api, unwrap } from '../api';
import { confirmAction, toast } from '../feedback';

const loading = ref(true);
const modules = ref<string[]>([]);
const frequencies = ref<string[]>([]);
const connection = ref<any>(null);
const syncs = ref<any[]>([]);
const runs = ref<any[]>([]);
const connectionForm = ref({ name: 'Google Sheets', spreadsheet_id: '', credentials_json: '' });
const credentialFileName = ref('');
const saving = ref(false);
const loadError = ref('');
const googleHelpOpen = ref(false);
const openGroups = ref({ google_oauth: true, sync_data: true, history: false });

function moduleLabel(value: string) {
  return value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function blankSync() {
  return { id: null, module: 'work_logs', frequency: 'daily', destination_sheet_name: 'work_logs', enabled: true };
}

async function load() {
  loading.value = true;
  loadError.value = '';
  try {
    const data: any = await api.get('/api/v1/configuration').then(unwrap);
    const config = data.configuration;
    modules.value = config.available_modules;
    frequencies.value = config.frequencies;
    connection.value = config.connection;
    syncs.value = config.syncs.length ? config.syncs : [blankSync()];
    runs.value = config.runs;
    connectionForm.value = {
      name: config.connection?.name || 'Google Sheets',
      spreadsheet_id: config.connection?.spreadsheet_id || '',
      credentials_json: '',
    };
  } catch (error: any) {
    loadError.value = error.response?.data?.message || 'Could not load configuration. Please refresh or sign in again.';
    syncs.value = [blankSync()];
    modules.value = ['daily_progress', 'work_logs', 'tasks', 'learning', 'milestones', 'reports'];
    frequencies.value = ['daily', 'weekly', 'monthly'];
  } finally {
    loading.value = false;
  }
}

function toggleGroup(key: keyof typeof openGroups.value) {
  openGroups.value[key] = !openGroups.value[key];
}

async function selectCredentialFile(event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0];
  if (!file) return;
  credentialFileName.value = file.name;
  try {
    connectionForm.value.credentials_json = await file.text();
    toast({ tone: 'success', title: 'Credential loaded', message: `${file.name} is ready to save.` });
  } catch {
    credentialFileName.value = '';
    connectionForm.value.credentials_json = '';
    toast({ tone: 'error', title: 'Could not read file', message: 'Choose a valid Google service account JSON file.' });
  }
}

async function saveConnection(showToast = true) {
  saving.value = true;
  try {
    const data: any = await api.put('/api/v1/configuration/backup-connection', connectionForm.value).then(unwrap);
    connection.value = data.connection;
    connectionForm.value.credentials_json = '';
    credentialFileName.value = '';
    if (showToast) toast({ tone: 'success', title: 'Connection saved', message: 'Backup destination settings were updated.' });
  } catch (error: any) {
    if (showToast) toast({ tone: 'error', title: 'Connection failed', message: error.response?.data?.message || 'Check the credential fields.' });
    throw error;
  } finally {
    saving.value = false;
  }
}

async function verifyConnection() {
  try {
    const data: any = await api.post('/api/v1/configuration/backup-connection/verify').then(unwrap);
    connection.value = data.connection;
    toast({ tone: 'success', title: 'Connection verified', message: 'Required Google Sheets fields are present.' });
  } catch (error: any) {
    toast({ tone: 'error', title: 'Verification failed', message: error.response?.data?.message || 'Missing service account fields.' });
  }
}

function addSync() {
  syncs.value.unshift(blankSync());
}

async function saveSync(sync: any, showToast = true) {
  const payload = {
    module: sync.module,
    frequency: sync.frequency,
    destination_sheet_name: sync.destination_sheet_name || sync.module,
    enabled: sync.enabled,
  };
  const request = sync.id
    ? api.patch(`/api/v1/configuration/backup-syncs/${sync.id}`, payload)
    : api.post('/api/v1/configuration/backup-syncs', payload);
  const data: any = await request.then(unwrap);
  Object.assign(sync, data.sync);
  if (showToast) toast({ tone: 'success', title: 'Sync saved', message: `${moduleLabel(sync.module)} backup is configured.` });
}

async function saveAll() {
  saving.value = true;
  try {
    await saveConnection(false);
    for (const sync of syncs.value) {
      await saveSync(sync, false);
    }
    toast({ tone: 'success', title: 'Configuration saved', message: 'Connection and sync schedules were updated.' });
  } catch {
    toast({ tone: 'error', title: 'Save failed', message: 'Review the highlighted configuration values and try again.' });
  } finally {
    saving.value = false;
  }
}

async function removeSync(sync: any, index: number) {
  if (!sync.id) {
    syncs.value.splice(index, 1);
    return;
  }
  const ok = await confirmAction({ title: 'Delete backup sync?', message: 'This removes the schedule and its future runs. Existing backup files remain in storage.', confirmLabel: 'Delete', danger: true });
  if (!ok) return;
  await api.delete(`/api/v1/configuration/backup-syncs/${sync.id}`);
  syncs.value.splice(index, 1);
  toast({ tone: 'success', title: 'Sync deleted', message: 'Backup schedule removed.' });
}

async function runNow(sync: any) {
  if (!sync.id) await saveSync(sync, false);
  const data: any = await api.post(`/api/v1/configuration/backup-syncs/${sync.id}/run`).then(unwrap);
  runs.value.unshift(data.run);
  toast({ tone: data.run.status === 'completed' ? 'success' : 'error', title: data.run.status === 'completed' ? 'Backup complete' : 'Backup failed', message: data.run.file_path || data.run.error_message });
  await load();
}

onMounted(load);
</script>

<template>
  <div class="space-y-5">
    <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
      <div>
        <p class="text-sm font-extrabold text-teal-700">Configuration</p>
        <h1 class="mt-1 text-3xl font-extrabold tracking-tight">Backup & Sync Settings</h1>
        <p class="mt-1 max-w-3xl text-sm font-medium leading-6 text-slate-500">Store Google Sheets destination settings and add module-level backup schedules.</p>
      </div>
      <button class="btn btn-primary" :disabled="saving || loading" @click="saveAll">Save Changes</button>
    </div>

    <section class="card overflow-visible p-0">
      <button type="button" class="flex w-full items-center justify-between gap-4 border-b border-slate-100 bg-slate-50/70 px-5 py-4 text-left" :aria-expanded="openGroups.google_oauth" @click="toggleGroup('google_oauth')">
        <span class="min-w-0">
          <span class="block text-xs font-extrabold uppercase text-teal-700">Backup Destination</span>
          <span class="mt-1 block text-lg font-extrabold text-slate-950">Google Sheets Connection</span>
          <span class="mt-1 block text-sm font-medium text-slate-500">Encrypted service account settings and spreadsheet target.</span>
        </span>
        <span class="flex shrink-0 items-center gap-3">
          <span class="pill" :class="connection?.status === 'verified' ? 'pill-green' : connection?.status === 'error' ? 'pill-red' : 'pill-slate'">{{ connection?.status || 'not configured' }}</span>
          <span class="grid h-8 w-8 place-items-center rounded-xl border border-slate-200 bg-white text-slate-500 transition" :class="openGroups.google_oauth ? 'rotate-180' : ''">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6" /></svg>
          </span>
        </span>
      </button>
      <div v-if="openGroups.google_oauth">
        <div class="border-b border-slate-100 px-5 py-4">
          <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <div>
            <div class="flex items-center gap-2">
              <h3 class="font-extrabold">Service Account</h3>
              <div class="relative">
                <button
                  type="button"
                  class="grid h-7 w-7 place-items-center rounded-full border border-slate-200 bg-white text-xs font-black text-slate-500 hover:border-teal-200 hover:text-teal-700"
                  aria-label="How to get Google service account JSON"
                  @click="googleHelpOpen = !googleHelpOpen"
                >?</button>
                <div v-if="googleHelpOpen" class="absolute left-0 z-20 mt-2 w-[min(22rem,calc(100vw-2rem))] rounded-2xl border border-slate-200 bg-white p-4 text-sm font-medium leading-6 text-slate-600 shadow-2xl shadow-slate-950/10">
                  <p class="font-extrabold text-slate-900">How to get the JSON</p>
                  <ol class="mt-2 list-decimal space-y-1 pl-5">
                    <li>Open Google Cloud Console and create/select a project.</li>
                    <li>Enable the Google Sheets API.</li>
                    <li>Create a Service Account, then create a JSON key.</li>
                    <li>Upload the downloaded JSON file here.</li>
                    <li>Share your spreadsheet with the JSON's <span class="font-bold">client_email</span>.</li>
                  </ol>
                </div>
              </div>
            </div>
            <p class="text-sm font-medium text-slate-500">Service account credentials are stored encrypted. Share your spreadsheet with the service account email.</p>
          </div>
        </div>
      </div>
        <div class="divide-y divide-slate-100">
          <div class="grid gap-3 px-5 py-4 md:grid-cols-[16rem_1fr] md:items-center">
            <div>
              <span class="font-extrabold text-slate-800">Connection name</span>
              <p class="text-xs font-semibold text-slate-500">Friendly label for this destination.</p>
            </div>
            <input v-model="connectionForm.name" class="field" placeholder="Google Sheets" />
          </div>
          <div class="grid gap-3 px-5 py-4 md:grid-cols-[16rem_1fr] md:items-center">
            <div>
              <span class="font-extrabold text-slate-800">Spreadsheet ID</span>
              <p class="text-xs font-semibold text-slate-500">The long ID from the Google Sheets URL.</p>
            </div>
            <input v-model="connectionForm.spreadsheet_id" class="field" placeholder="1AbC..." />
          </div>
          <div class="grid gap-3 px-5 py-4 md:grid-cols-[16rem_1fr]">
            <div>
              <span class="font-extrabold text-slate-800">Service account JSON</span>
              <p class="text-xs font-semibold text-slate-500">Upload the downloaded JSON key. It will not be displayed after save.</p>
            </div>
            <div class="space-y-3">
              <label class="flex min-h-32 cursor-pointer flex-col items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-5 text-center transition hover:border-teal-300 hover:bg-teal-50/50">
                <input class="sr-only" type="file" accept="application/json,.json" @change="selectCredentialFile" />
                <span class="text-sm font-extrabold text-slate-800">{{ credentialFileName || (connection?.has_credentials ? 'Credential file already uploaded' : 'Upload service account JSON') }}</span>
                <span class="mt-1 text-xs font-semibold text-slate-500">{{ connectionForm.credentials_json ? 'Ready to save' : connection?.has_credentials ? 'Upload a new JSON only if you want to replace it.' : 'Choose the JSON key downloaded from Google Cloud.' }}</span>
              </label>
              <div v-if="connectionForm.credentials_json || connection?.has_credentials" class="rounded-xl border border-teal-100 bg-teal-50 px-4 py-3 text-sm font-bold text-teal-900">
                {{ connectionForm.credentials_json ? 'New credential selected. Click Save Changes to store it.' : 'Credential uploaded and encrypted.' }}
              </div>
            </div>
          </div>
          <div v-if="connection?.service_account_email" class="px-5 py-4">
            <div class="rounded-xl border border-teal-100 bg-teal-50 p-4 text-sm font-semibold text-teal-900">
              Share the spreadsheet with: {{ connection.service_account_email }}
            </div>
          </div>
        </div>
        <div class="flex flex-wrap justify-end gap-2 border-t border-slate-100 bg-slate-50/70 px-5 py-4">
          <button class="btn btn-muted" type="button" @click="verifyConnection">Test settings</button>
        </div>
      </div>
    </section>

    <section class="card overflow-hidden p-0">
      <div class="flex items-center justify-between gap-4 border-b border-slate-100 bg-slate-50/70 px-5 py-4">
        <button type="button" class="min-w-0 flex-1 text-left" :aria-expanded="openGroups.sync_data" @click="toggleGroup('sync_data')">
          <span class="block text-xs font-extrabold uppercase text-teal-700">Automation</span>
          <span class="mt-1 block text-lg font-extrabold text-slate-950">Sync Data</span>
          <span class="mt-1 block text-sm font-medium text-slate-500">Module schedules for daily, weekly, and monthly backup runs.</span>
        </button>
        <span class="flex shrink-0 items-center gap-2">
          <button class="btn btn-muted" type="button" @click="addSync">Add Sync</button>
          <button type="button" class="grid h-8 w-8 place-items-center rounded-xl border border-slate-200 bg-white text-slate-500 transition" :class="openGroups.sync_data ? 'rotate-180' : ''" aria-label="Toggle sync data" @click="toggleGroup('sync_data')">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6" /></svg>
          </button>
        </span>
      </div>
      <div v-if="openGroups.sync_data">
        <div v-if="loading" class="p-6 text-sm font-semibold text-slate-500">Loading configuration...</div>
        <div v-else-if="loadError" class="p-6">
          <div class="rounded-2xl border border-red-200 bg-red-50 p-4">
            <p class="font-extrabold text-red-800">Configuration failed to load</p>
            <p class="mt-1 text-sm font-medium text-red-700">{{ loadError }}</p>
            <button class="btn mt-3 border border-red-200 bg-white text-red-700 hover:bg-red-100" type="button" @click="load">Retry</button>
          </div>
        </div>
        <div v-else class="divide-y divide-slate-100">
          <div v-for="(sync, index) in syncs" :key="sync.id || `new-${index}`" class="p-5">
            <div class="mb-4 flex items-center justify-between gap-3">
              <div>
                <h3 class="font-extrabold">Sync #{{ index + 1 }}</h3>
                <p class="text-xs font-semibold text-slate-500">Last run: {{ sync.last_run_at || 'never' }} · Next run: {{ sync.next_run_at || 'after save' }}</p>
              </div>
              <label class="flex h-10 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 text-sm font-bold text-slate-700"><input v-model="sync.enabled" type="checkbox" class="accent-teal-700" />Active</label>
            </div>
            <div class="grid gap-3 md:grid-cols-[16rem_1fr] md:items-center">
              <span class="font-extrabold text-slate-800">Feature</span>
              <select v-model="sync.module" class="field"><option v-for="module in modules" :key="module" :value="module">{{ moduleLabel(module) }}</option></select>
              <span class="font-extrabold text-slate-800">Frequency</span>
              <select v-model="sync.frequency" class="field"><option v-for="frequency in frequencies" :key="frequency" :value="frequency">{{ frequency }}</option></select>
              <span class="font-extrabold text-slate-800">Sheet/tab name</span>
              <input v-model="sync.destination_sheet_name" class="field" :placeholder="sync.module" />
            </div>
            <div class="mt-4 flex flex-wrap justify-end gap-2">
              <button class="btn btn-muted" type="button" @click="runNow(sync)">Run</button>
              <button class="btn border border-red-200 bg-red-50 text-red-700 hover:bg-red-100" type="button" @click="removeSync(sync, index)">Delete</button>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="card overflow-hidden p-0">
      <button type="button" class="flex w-full items-center justify-between gap-4 border-b border-slate-100 bg-slate-50/70 px-5 py-4 text-left" :aria-expanded="openGroups.history" @click="toggleGroup('history')">
        <span>
          <span class="block text-xs font-extrabold uppercase text-teal-700">Audit</span>
          <span class="mt-1 block text-lg font-extrabold text-slate-950">Backup History</span>
          <span class="mt-1 block text-sm font-medium text-slate-500">Recent backup runs and generated spreadsheet-compatible CSV files.</span>
        </span>
        <span class="grid h-8 w-8 place-items-center rounded-xl border border-slate-200 bg-white text-slate-500 transition" :class="openGroups.history ? 'rotate-180' : ''">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6" /></svg>
        </span>
      </button>
      <div v-if="openGroups.history" class="overflow-x-auto">
        <table class="data-table">
          <thead><tr><th>Module</th><th>Status</th><th>Rows</th><th>File</th><th>Created</th></tr></thead>
          <tbody>
            <tr v-for="run in runs" :key="run.id">
              <td class="font-bold">{{ moduleLabel(run.module || 'unknown') }}</td>
              <td><span class="pill" :class="run.status === 'completed' ? 'pill-green' : run.status === 'failed' ? 'pill-red' : 'pill-slate'">{{ run.status }}</span></td>
              <td>{{ run.rows_exported }}</td>
              <td class="max-w-xs truncate text-slate-500">{{ run.file_path || run.error_message || '-' }}</td>
              <td class="text-slate-500">{{ run.created_at }}</td>
            </tr>
            <tr v-if="runs.length === 0"><td colspan="5" class="py-10 text-center font-semibold text-slate-500">No backup runs yet.</td></tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>
