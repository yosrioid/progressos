<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { api, unwrap } from '../api';
import { confirmAction, toast } from '../feedback';

const loading = ref(true);
const sections = ref({ google_oauth: true, sync_data: true, history: true });
const modules = ref<string[]>([]);
const frequencies = ref<string[]>([]);
const connection = ref<any>(null);
const syncs = ref<any[]>([]);
const runs = ref<any[]>([]);
const connectionForm = ref({ name: 'Google Sheets', spreadsheet_id: '', credentials_json: '' });
const saving = ref(false);
const sectionKeys = computed(() => Object.keys(sections.value) as Array<keyof typeof sections.value>);

function moduleLabel(value: string) {
  return value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function blankSync() {
  return { id: null, module: 'work_logs', frequency: 'daily', destination_sheet_name: 'work_logs', enabled: true };
}

async function load() {
  loading.value = true;
  const data: any = await api.get('/api/v1/configuration').then(unwrap);
  const config = data.configuration;
  sections.value = config.sections;
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
  loading.value = false;
}

async function saveConnection() {
  saving.value = true;
  try {
    const data: any = await api.put('/api/v1/configuration/backup-connection', connectionForm.value).then(unwrap);
    connection.value = data.connection;
    connectionForm.value.credentials_json = '';
    toast({ tone: 'success', title: 'Connection saved', message: 'Backup destination settings were updated.' });
  } catch (error: any) {
    toast({ tone: 'error', title: 'Connection failed', message: error.response?.data?.message || 'Check the credential fields.' });
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

async function saveSync(sync: any) {
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
  toast({ tone: 'success', title: 'Sync saved', message: `${moduleLabel(sync.module)} backup is configured.` });
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
  if (!sync.id) await saveSync(sync);
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
        <p class="mt-1 max-w-3xl text-sm font-medium leading-6 text-slate-500">Choose which configuration sections are visible, store Google Sheets destination settings, and add module-level backup schedules.</p>
      </div>
      <button class="btn btn-primary" @click="addSync">Add Sync</button>
    </div>

    <div class="card p-4">
      <div class="grid gap-3 md:grid-cols-3">
        <label v-for="key in sectionKeys" :key="key" class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
          <span class="font-bold capitalize text-slate-700">{{ key.replaceAll('_', ' ') }}</span>
          <input v-model="sections[key]" type="checkbox" class="h-5 w-5 accent-teal-700" />
        </label>
      </div>
    </div>

    <section v-if="sections.google_oauth" class="card overflow-hidden p-0">
      <div class="border-b border-slate-100 bg-slate-50/70 px-5 py-4">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h2 class="font-extrabold">Google Sheets Connection</h2>
            <p class="text-sm font-medium text-slate-500">Service account credentials are stored encrypted. Share your spreadsheet with the service account email.</p>
          </div>
          <span class="pill" :class="connection?.status === 'verified' ? 'pill-green' : connection?.status === 'error' ? 'pill-red' : 'pill-slate'">{{ connection?.status || 'not configured' }}</span>
        </div>
      </div>
      <div class="grid gap-4 p-5 lg:grid-cols-2">
        <label><span class="label mb-1">Connection name</span><input v-model="connectionForm.name" class="field" placeholder="Google Sheets" /></label>
        <label><span class="label mb-1">Spreadsheet ID</span><input v-model="connectionForm.spreadsheet_id" class="field" placeholder="1AbC..." /></label>
        <label class="lg:col-span-2">
          <span class="label mb-1">Service account JSON</span>
          <textarea v-model="connectionForm.credentials_json" class="field min-h-40 font-mono text-xs" placeholder='{"project_id":"...","client_email":"...","private_key":"..."}'></textarea>
        </label>
        <div v-if="connection?.service_account_email" class="rounded-xl border border-teal-100 bg-teal-50 p-4 text-sm font-semibold text-teal-900 lg:col-span-2">
          Share the spreadsheet with: {{ connection.service_account_email }}
        </div>
      </div>
      <div class="flex flex-wrap justify-end gap-2 border-t border-slate-100 bg-slate-50/70 px-5 py-4">
        <button class="btn btn-muted" type="button" @click="verifyConnection">Test settings</button>
        <button class="btn btn-primary" type="button" :disabled="saving" @click="saveConnection">Save connection</button>
      </div>
    </section>

    <section v-if="sections.sync_data" class="card overflow-hidden p-0">
      <div class="border-b border-slate-100 bg-slate-50/70 px-5 py-4">
        <h2 class="font-extrabold">Sync Data</h2>
        <p class="text-sm font-medium text-slate-500">Add one or more schedules. Each row can target a different module and frequency.</p>
      </div>
      <div v-if="loading" class="p-6 text-sm font-semibold text-slate-500">Loading configuration...</div>
      <div v-else class="divide-y divide-slate-100">
        <div v-for="(sync, index) in syncs" :key="sync.id || `new-${index}`" class="grid gap-3 p-5 lg:grid-cols-[1.2fr_1fr_1.2fr_auto] lg:items-end">
          <label><span class="label mb-1">Feature</span><select v-model="sync.module" class="field"><option v-for="module in modules" :key="module" :value="module">{{ moduleLabel(module) }}</option></select></label>
          <label><span class="label mb-1">Frequency</span><select v-model="sync.frequency" class="field"><option v-for="frequency in frequencies" :key="frequency" :value="frequency">{{ frequency }}</option></select></label>
          <label><span class="label mb-1">Sheet/tab name</span><input v-model="sync.destination_sheet_name" class="field" :placeholder="sync.module" /></label>
          <div class="flex flex-wrap items-center gap-2">
            <label class="flex h-11 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 text-sm font-bold text-slate-700"><input v-model="sync.enabled" type="checkbox" class="accent-teal-700" />Active</label>
            <button class="btn btn-muted" type="button" @click="runNow(sync)">Run</button>
            <button class="btn btn-primary" type="button" @click="saveSync(sync)">Save</button>
            <button class="btn border border-red-200 bg-red-50 text-red-700 hover:bg-red-100" type="button" @click="removeSync(sync, index)">Delete</button>
          </div>
          <p class="text-xs font-semibold text-slate-500 lg:col-span-4">Last run: {{ sync.last_run_at || 'never' }} · Next run: {{ sync.next_run_at || 'after save' }}</p>
        </div>
      </div>
    </section>

    <section v-if="sections.history" class="card overflow-hidden p-0">
      <div class="border-b border-slate-100 bg-slate-50/70 px-5 py-4">
        <h2 class="font-extrabold">Backup History</h2>
        <p class="text-sm font-medium text-slate-500">Recent backup runs and generated spreadsheet-compatible CSV files.</p>
      </div>
      <div class="overflow-x-auto">
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
