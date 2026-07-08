<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { api, unwrap } from '../api';
import { confirmAction, toast } from '../feedback';
import { timezones, useConfigurationStore } from '../stores/configuration';

const configuration = useConfigurationStore();
const loading = ref(true);
const modules = ref<string[]>([]);
const frequencies = ref<string[]>([]);
const connection = ref<any>(null);
const syncs = ref<any[]>([]);
const runs = ref<any[]>([]);
const groupSettings = ref<any>({
  general: { app_name: 'ProgressOS', project_name: 'ProgressOS', tagline: '', timezone: 'Asia/Jakarta' },
  appearance: { favicon_url: '' },
  notifications: { daily_review_enabled: false, weekly_review_enabled: false },
});
const connectionForm = ref({ name: 'Google Sheets', spreadsheet_id: '', credentials_json: '' });
const authForm = ref({ google_sso_enabled: false, client_id: '', client_secret: '' });
const mailForm = ref({ mailer: 'log', from_address: '', from_name: '', api_key: '', host: '', port: 587, username: '', password: '' });
const authConfig = ref<any>({ google_sso_enabled: false, client_id: '', has_client_secret: false });
const mailConfig = ref<any>({ mailer: 'log', from_address: '', from_name: '', has_api_key: false, host: '', port: 587, username: '', has_password: false });
const credentialFileName = ref('');
const saving = ref(false);
const loadError = ref('');
const googleHelpOpen = ref(false);
const googleSsoHelpOpen = ref(false);
const resendHelpOpen = ref(false);
const openGroups = ref({ general: true, appearance: false, auth: true, mail: true, google_oauth: false, sync_data: false, notifications: false, history: false, quote: false, ai: false });
const aiForm = ref({ provider: 'groq', model: 'claude-sonnet-4-6', api_key: '', groq_api_key: '' });
const aiSaving = ref(false);
const featureProviderSaving = ref(false);
const featureProviders = ref({ chat: 'groq', journal: 'groq', quote: 'groq' });
const quoteConfig = ref<{ enabled: boolean; themes: string[] }>({
  enabled: false, themes: ['motivation'],
});
const quoteForm = ref({ enabled: false, themes: ['motivation'] });
const quoteTagInput = ref('');
const quoteSaving = ref(false);
const showClientSecret = ref(false);
const showMailApiKey = ref(false);
const showSmtpPassword = ref(false);
const showGroqApiKey = ref(false);
const timezonePreview = computed(() => {
  try {
    return new Intl.DateTimeFormat('en', {
      dateStyle: 'full',
      timeStyle: 'medium',
      timeZone: groupSettings.value.general.timezone || 'Asia/Jakarta',
    }).format(new Date());
  } catch {
    return 'Invalid timezone';
  }
});

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
    const data: any = await api.get('/api/admin/configuration').then(unwrap);
    const config = data.configuration;
    modules.value = config.available_modules;
    frequencies.value = config.frequencies;
    connection.value = config.connection;
    syncs.value = config.syncs.length ? config.syncs : [blankSync()];
    runs.value = config.runs;
    groupSettings.value = { ...groupSettings.value, ...(config.groups || {}) };
    configuration.applyGroups(config.groups || {});
    connectionForm.value = {
      name: config.connection?.name || 'Google Sheets',
      spreadsheet_id: config.connection?.spreadsheet_id || '',
      credentials_json: '',
    };
    authConfig.value = config.auth_config || authConfig.value;
    authForm.value = { ...authForm.value, google_sso_enabled: authConfig.value.google_sso_enabled, client_id: authConfig.value.client_id, client_secret: '' };
    mailConfig.value = config.mail_config || mailConfig.value;
    mailForm.value = { ...mailForm.value, mailer: mailConfig.value.mailer, from_address: mailConfig.value.from_address, from_name: mailConfig.value.from_name, host: mailConfig.value.host, port: mailConfig.value.port, username: mailConfig.value.username, api_key: '', password: '' };
    // Load AI config
    const aiConfig = config.ai_config || {};
    aiForm.value = {
      provider: aiConfig.provider || 'groq',
      model: aiConfig.model || 'claude-sonnet-4-6',
      api_key: '',
      groq_api_key: aiConfig.groq_api_key_set ? '***already_set***' : '',
      groq_api_key_set: !!aiConfig.groq_api_key_set,
      api_key_set: !!aiConfig.api_key_set,
    };
    // Load quote config separately
    try {
      const qData: any = await api.get('/api/v1/quote/config').then(unwrap);
      if (qData.quote_config) {
        quoteConfig.value = { ...quoteConfig.value, ...qData.quote_config };
        quoteForm.value = { enabled: quoteConfig.value.enabled, themes: [...quoteConfig.value.themes] };
      }
    } catch {
      // non-critical
    }

    // Initialize feature providers from aiConfig
    if (aiConfig.value?.feature_providers) {
      featureProviders.value = { ...featureProviders.value, ...aiConfig.value.feature_providers };
    }
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
    const data: any = await api.put('/api/admin/configuration/backup-connection', connectionForm.value).then(unwrap);
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

async function saveSettings(showToast = true) {
  const data: any = await api.put('/api/admin/configuration/settings', groupSettings.value).then(unwrap);
  groupSettings.value = { ...groupSettings.value, ...(data.groups || {}) };
  configuration.applyGroups(data.groups || {});
  if (showToast) toast({ tone: 'success', title: 'Settings saved', message: 'General configuration values were updated.' });
}

async function verifyConnection() {
  try {
    const data: any = await api.post('/api/admin/configuration/backup-connection/verify').then(unwrap);
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
    ? api.patch(`/api/admin/configuration/backup-syncs/${sync.id}`, payload)
    : api.post('/api/admin/configuration/backup-syncs', payload);
  const data: any = await request.then(unwrap);
  Object.assign(sync, data.sync);
  if (showToast) toast({ tone: 'success', title: 'Sync saved', message: `${moduleLabel(sync.module)} backup is configured.` });
}

async function saveAuthConfig(showToast = true) {
  const data: any = await api.put('/api/admin/configuration/auth', authForm.value).then(unwrap);
  authConfig.value = data.auth_config;
  authForm.value.client_secret = '';
  if (showToast) toast({ tone: 'success', title: 'SSO settings saved', message: 'Google login settings were updated.' });
}

async function saveMailConfig(showToast = true) {
  const data: any = await api.put('/api/admin/configuration/mail', mailForm.value).then(unwrap);
  mailConfig.value = data.mail_config;
  mailForm.value.api_key = '';
  mailForm.value.password = '';
  if (showToast) toast({ tone: 'success', title: 'Email settings saved', message: 'Mail delivery settings were updated.' });
}

async function saveAll() {
  saving.value = true;
  try {
    await saveSettings(false);
    await saveAuthConfig(false);
    await saveMailConfig(false);
    await saveConnection(false);
    for (const sync of syncs.value) {
      await saveSync(sync, false);
    }
    toast({ tone: 'success', title: 'Configuration saved', message: 'All settings were updated.' });
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
  await api.delete(`/api/admin/configuration/backup-syncs/${sync.id}`);
  syncs.value.splice(index, 1);
  toast({ tone: 'success', title: 'Sync deleted', message: 'Backup schedule removed.' });
}

async function runNow(sync: any) {
  if (!sync.id) await saveSync(sync, false);
  const data: any = await api.post(`/api/admin/configuration/backup-syncs/${sync.id}/run`).then(unwrap);
  runs.value.unshift(data.run);
  toast({ tone: data.run.status === 'completed' ? 'success' : 'error', title: data.run.status === 'completed' ? 'Backup complete' : 'Backup failed', message: data.run.file_path || data.run.error_message });
  await load();
}

function addTheme() {
  const tag = quoteTagInput.value.trim().toLowerCase().replace(/,+$/, '').trim();
  if (!tag) return;
  if (!quoteForm.value.themes.includes(tag)) quoteForm.value.themes.push(tag);
  quoteTagInput.value = '';
}

function removeTheme(theme: string) {
  if (quoteForm.value.themes.length <= 1) return;
  quoteForm.value.themes = quoteForm.value.themes.filter((t) => t !== theme);
}

function onThemeKeydown(e: KeyboardEvent) {
  if (e.key === 'Enter' || e.key === ',') {
    e.preventDefault();
    addTheme();
  } else if (e.key === 'Backspace' && quoteTagInput.value === '' && quoteForm.value.themes.length > 1) {
    quoteForm.value.themes.pop();
  }
}

async function saveQuoteConfig() {
  quoteSaving.value = true;
  try {
    const data: any = await api.put('/api/admin/configuration/quote', quoteForm.value).then(unwrap);
    quoteConfig.value = { ...quoteConfig.value, ...data.quote_config };
    toast({ tone: 'success', title: 'Quote settings saved', message: quoteConfig.value.enabled ? 'Daily quote aktif.' : 'Daily quote dinonaktifkan.' });
  } catch (e: any) {
    toast({ tone: 'error', title: 'Gagal menyimpan', message: e?.response?.data?.message ?? 'Terjadi kesalahan.' });
  } finally {
    quoteSaving.value = false;
  }
}

async function saveAiConfig() {
  aiSaving.value = true;
  try {
    const payload: any = {
      provider: aiForm.value.provider,
      model: aiForm.value.model,
    };
    
    // Only send API key if provided (to avoid clearing existing key)
    if (aiForm.value.provider === 'groq' && aiForm.value.groq_api_key) {
      payload.groq_api_key = aiForm.value.groq_api_key;
    } else if (aiForm.value.provider === 'adacode' && aiForm.value.api_key) {
      payload.api_key = aiForm.value.api_key;
    }
    
    const res = await api.put('/api/admin/configuration/ai', payload);
    unwrap(res);
    configuration.applyGroups({ ai: { ...aiForm.value, groq_api_key: configuration.ai?.groq_api_key || '' } });
    await configuration.fetchUsage(aiForm.value.provider);
    toast({ tone: 'success', title: 'AI settings saved' });
  } catch (e: any) {
    toast({ tone: 'error', title: 'Failed to save', message: e?.response?.data?.message ?? e?.response?.data?.errors ?? 'Terjadi kesalahan.' });
  } finally {
    aiSaving.value = false;
  }
}

async function saveFeatureProviders() {
  featureProviderSaving.value = true;
  try {
    const res = await api.put('/api/admin/configuration/feature-providers', {
      feature_providers: featureProviders.value,
    });
    unwrap(res);
    toast({ tone: 'success', title: 'Provider settings saved' });
  } catch (e: any) {
    toast({ tone: 'error', title: 'Failed to save', message: e?.response?.data?.message ?? e?.response?.data?.errors ?? 'Terjadi kesalahan.' });
  } finally {
    featureProviderSaving.value = false;
  }
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

    <section class="card overflow-hidden p-0">
      <button type="button" class="flex w-full items-center justify-between gap-4 border-b border-slate-100 bg-slate-50/70 px-5 py-4 text-left" :aria-expanded="openGroups.general" @click="toggleGroup('general')">
        <span>
          <span class="block text-xs font-extrabold uppercase text-teal-700">General</span>
          <span class="mt-1 block text-lg font-extrabold text-slate-950">Project Identity</span>
          <span class="mt-1 block text-sm font-medium text-slate-500">Core labels used across the application shell and future exports.</span>
        </span>
        <span class="grid h-8 w-8 place-items-center rounded-xl border border-slate-200 bg-white text-slate-500 transition" :class="openGroups.general ? 'rotate-180' : ''">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6" /></svg>
        </span>
      </button>
      <div v-if="openGroups.general" class="divide-y divide-slate-100">
        <div class="grid gap-3 px-5 py-4 md:grid-cols-[16rem_1fr] md:items-center">
          <span class="font-extrabold text-slate-800">Application name</span>
          <input v-model="groupSettings.general.app_name" class="field" placeholder="ProgressOS" />
          <span class="font-extrabold text-slate-800">Project name</span>
          <input v-model="groupSettings.general.project_name" class="field" placeholder="ProgressOS" />
          <span class="font-extrabold text-slate-800">Tagline</span>
          <input v-model="groupSettings.general.tagline" class="field" placeholder="Personal operating system" />
          <span class="font-extrabold text-slate-800">Timezone</span>
          <div class="space-y-2">
            <select v-model="groupSettings.general.timezone" class="field">
              <option v-for="timezone in timezones" :key="timezone" :value="timezone">{{ timezone.replace('_', ' ') }}</option>
            </select>
            <p class="rounded-xl border border-teal-100 bg-teal-50 px-3 py-2 text-sm font-bold text-teal-900">{{ timezonePreview }}</p>
          </div>
        </div>
      </div>
    </section>

    <section class="card overflow-hidden p-0">
      <button type="button" class="flex w-full items-center justify-between gap-4 border-b border-slate-100 bg-slate-50/70 px-5 py-4 text-left" :aria-expanded="openGroups.appearance" @click="toggleGroup('appearance')">
        <span>
          <span class="block text-xs font-extrabold uppercase text-teal-700">Appearance</span>
          <span class="mt-1 block text-lg font-extrabold text-slate-950">Brand</span>
          <span class="mt-1 block text-sm font-medium text-slate-500">Visual preferences reserved for app-wide branding.</span>
        </span>
        <span class="grid h-8 w-8 place-items-center rounded-xl border border-slate-200 bg-white text-slate-500 transition" :class="openGroups.appearance ? 'rotate-180' : ''">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6" /></svg>
        </span>
      </button>
      <div v-if="openGroups.appearance" class="divide-y divide-slate-100">
        <div class="grid gap-3 px-5 py-4 md:grid-cols-[16rem_1fr] md:items-center">
          <span class="font-extrabold text-slate-800">Favicon URL</span>
          <input v-model="groupSettings.appearance.favicon_url" class="field" placeholder="https://example.com/favicon.png" />
        </div>
      </div>
    </section>

    <!-- ── Auth / SSO ── -->
    <section class="card overflow-visible p-0">
      <button type="button" class="flex w-full items-center justify-between gap-4 border-b border-slate-100 bg-slate-50/70 px-5 py-4 text-left" :aria-expanded="openGroups.auth" @click="toggleGroup('auth')">
        <span>
          <span class="block text-xs font-extrabold uppercase text-teal-700">Authentication</span>
          <span class="mt-1 block text-lg font-extrabold text-slate-950">Login & SSO</span>
          <span class="mt-1 block text-sm font-medium text-slate-500">Google OAuth credentials for one-click login.</span>
        </span>
        <span class="flex shrink-0 items-center gap-3">
          <span class="pill" :class="authConfig.google_sso_enabled && authConfig.has_client_secret ? 'pill-green' : 'pill-slate'">{{ authConfig.google_sso_enabled && authConfig.has_client_secret ? 'active' : 'not configured' }}</span>
          <span class="grid h-8 w-8 place-items-center rounded-xl border border-slate-200 bg-white text-slate-500 transition" :class="openGroups.auth ? 'rotate-180' : ''">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6" /></svg>
          </span>
        </span>
      </button>
      <div v-if="openGroups.auth">
        <div class="divide-y divide-slate-100">
          <div class="grid gap-3 px-5 py-4 md:grid-cols-[16rem_1fr] md:items-center">
            <span class="font-extrabold text-slate-800">Enable Google SSO</span>
            <label class="flex h-11 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 text-sm font-bold text-slate-700">
              <input v-model="authForm.google_sso_enabled" type="checkbox" class="accent-teal-700" />
              Show "Sign in with Google" button on the login page
            </label>
          </div>
          <div class="grid gap-3 px-5 py-4 md:grid-cols-[16rem_1fr] md:items-start">
            <div>
              <div class="flex items-center gap-2">
                <span class="font-extrabold text-slate-800">Google Client ID</span>
                <div class="relative">
                  <button type="button" class="grid h-7 w-7 place-items-center rounded-full border border-slate-200 bg-white text-xs font-black text-slate-500 hover:border-teal-200 hover:text-teal-700" aria-label="Cara mendapat Client ID" @click="googleSsoHelpOpen = !googleSsoHelpOpen">?</button>
                  <div v-if="googleSsoHelpOpen" class="absolute left-0 z-20 mt-2 w-[min(22rem,calc(100vw-2rem))] rounded-2xl border border-slate-200 bg-white p-4 text-sm font-medium leading-6 text-slate-600 shadow-2xl shadow-slate-950/10">
                    <p class="font-extrabold text-slate-900">How to get Client ID & Secret</p>
                    <ol class="mt-2 list-decimal space-y-1.5 pl-5">
                      <li>Open <span class="font-bold">console.cloud.google.com</span>, create or select a project.</li>
                      <li>Go to <span class="font-bold">APIs & Services → OAuth consent screen</span>, select External, fill in app name.</li>
                      <li>Go to <span class="font-bold">APIs & Services → Credentials → Create Credentials → OAuth 2.0 Client IDs</span>.</li>
                      <li>Select type <span class="font-bold">Web application</span>.</li>
                      <li>Under "Authorized redirect URIs" add: <span class="break-all font-mono font-bold">https://yourdomain.com/auth/google/callback</span></li>
                      <li>Copy <span class="font-bold">Client ID</span> and <span class="font-bold">Client Secret</span> here.</li>
                    </ol>
                  </div>
                </div>
              </div>
              <p class="text-xs font-semibold text-slate-500">Dari Google Cloud Console.</p>
            </div>
            <input v-model="authForm.client_id" class="field" placeholder="123456789-abc...apps.googleusercontent.com" />
          </div>
          <div class="grid gap-3 px-5 py-4 md:grid-cols-[16rem_1fr] md:items-center">
            <div>
              <span class="font-extrabold text-slate-800">Google Client Secret</span>
              <p class="text-xs font-semibold text-slate-500">{{ authConfig.has_client_secret ? 'Already saved. Fill in to replace.' : 'Not set.' }}</p>
            </div>
            <div class="relative">
              <input v-model="authForm.client_secret" :type="showClientSecret ? 'text' : 'password'" class="field pr-9" placeholder="GOCSPX-..." autocomplete="new-password" />
              <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 p-1 text-slate-400 hover:text-slate-600 dark:hover:text-zinc-300" @click="showClientSecret = !showClientSecret">
                <svg v-if="!showClientSecret" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg v-else class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
              </button>
            </div>
          </div>
        </div>
        <div class="flex justify-end border-t border-slate-100 bg-slate-50/70 px-5 py-4">
          <button class="btn btn-muted" type="button" @click="saveAuthConfig()">Save SSO settings</button>
        </div>
      </div>
    </section>

    <!-- ── Email / SMTP ── -->
    <section class="card overflow-visible p-0">
      <button type="button" class="flex w-full items-center justify-between gap-4 border-b border-slate-100 bg-slate-50/70 px-5 py-4 text-left" :aria-expanded="openGroups.mail" @click="toggleGroup('mail')">
        <span>
          <span class="block text-xs font-extrabold uppercase text-teal-700">Email</span>
          <span class="mt-1 block text-lg font-extrabold text-slate-950">Email Delivery</span>
          <span class="mt-1 block text-sm font-medium text-slate-500">Configuration for password reset and notifications.</span>
        </span>
        <span class="flex shrink-0 items-center gap-3">
          <span class="pill" :class="mailConfig.mailer !== 'log' && (mailConfig.has_api_key || mailConfig.host) ? 'pill-green' : 'pill-slate'">{{ mailConfig.mailer !== 'log' && (mailConfig.has_api_key || mailConfig.host) ? mailConfig.mailer : 'log only' }}</span>
          <span class="grid h-8 w-8 place-items-center rounded-xl border border-slate-200 bg-white text-slate-500 transition" :class="openGroups.mail ? 'rotate-180' : ''">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6" /></svg>
          </span>
        </span>
      </button>
      <div v-if="openGroups.mail">
        <div class="divide-y divide-slate-100">
          <div class="grid gap-3 px-5 py-4 md:grid-cols-[16rem_1fr] md:items-center">
            <span class="font-extrabold text-slate-800">Mailer</span>
            <select v-model="mailForm.mailer" class="field">
              <option value="log">log (development only)</option>
              <option value="resend">Resend (free 3,000/month)</option>
              <option value="smtp">SMTP</option>
            </select>
          </div>
          <div class="grid gap-3 px-5 py-4 md:grid-cols-[16rem_1fr] md:items-center">
            <span class="font-extrabold text-slate-800">From address</span>
            <input v-model="mailForm.from_address" class="field" type="email" placeholder="noreply@domain.com" />
          </div>
          <div class="grid gap-3 px-5 py-4 md:grid-cols-[16rem_1fr] md:items-center">
            <span class="font-extrabold text-slate-800">From name</span>
            <input v-model="mailForm.from_name" class="field" placeholder="ProgressOS" />
          </div>

          <!-- Resend fields -->
          <template v-if="mailForm.mailer === 'resend'">
            <div class="grid gap-3 px-5 py-4 md:grid-cols-[16rem_1fr] md:items-start">
              <div>
                <div class="flex items-center gap-2">
                  <span class="font-extrabold text-slate-800">Resend API Key</span>
                  <div class="relative">
                    <button type="button" class="grid h-7 w-7 place-items-center rounded-full border border-slate-200 bg-white text-xs font-black text-slate-500 hover:border-teal-200 hover:text-teal-700" aria-label="Cara mendapat Resend API key" @click="resendHelpOpen = !resendHelpOpen">?</button>
                    <div v-if="resendHelpOpen" class="absolute left-0 z-20 mt-2 w-[min(22rem,calc(100vw-2rem))] rounded-2xl border border-slate-200 bg-white p-4 text-sm font-medium leading-6 text-slate-600 shadow-2xl shadow-slate-950/10">
                      <p class="font-extrabold text-slate-900">Setup Resend (free, 3,000 emails/month)</p>
                      <ol class="mt-2 list-decimal space-y-1.5 pl-5">
                        <li>Sign up at <span class="font-bold">resend.com</span>.</li>
                        <li>Go to <span class="font-bold">Domains</span> → add your domain → verify DNS.</li>
                        <li>Go to <span class="font-bold">API Keys</span> → Create API Key.</li>
                        <li>Paste the API key here. Format: <span class="font-mono font-bold">re_xxxxxxxx</span></li>
                        <li>Set From address to an email from your verified domain.</li>
                      </ol>
                    </div>
                  </div>
                </div>
                <p class="text-xs font-semibold text-slate-500">{{ mailConfig.has_api_key ? 'Already saved. Fill in to replace.' : 'Not set.' }}</p>
              </div>
              <div class="relative">
                <input v-model="mailForm.api_key" :type="showMailApiKey ? 'text' : 'password'" class="field pr-9" placeholder="re_xxxxxxxxxxxx" autocomplete="new-password" />
                <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 p-1 text-slate-400 hover:text-slate-600 dark:hover:text-zinc-300" @click="showMailApiKey = !showMailApiKey">
                  <svg v-if="!showMailApiKey" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
                  <svg v-else class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                </button>
              </div>
            </div>
          </template>

          <!-- SMTP fields -->
          <template v-if="mailForm.mailer === 'smtp'">
            <div class="grid gap-3 px-5 py-4 md:grid-cols-[16rem_1fr] md:items-center">
              <span class="font-extrabold text-slate-800">SMTP Host</span>
              <input v-model="mailForm.host" class="field" placeholder="smtp.example.com" />
            </div>
            <div class="grid gap-3 px-5 py-4 md:grid-cols-[16rem_1fr] md:items-center">
              <span class="font-extrabold text-slate-800">SMTP Port</span>
              <input v-model.number="mailForm.port" class="field" type="number" placeholder="587" />
            </div>
            <div class="grid gap-3 px-5 py-4 md:grid-cols-[16rem_1fr] md:items-center">
              <span class="font-extrabold text-slate-800">Username</span>
              <input v-model="mailForm.username" class="field" placeholder="user@example.com" />
            </div>
            <div class="grid gap-3 px-5 py-4 md:grid-cols-[16rem_1fr] md:items-center">
              <div>
                <span class="font-extrabold text-slate-800">Password</span>
                <p class="text-xs font-semibold text-slate-500">{{ mailConfig.has_password ? 'Already saved. Fill in to replace.' : 'Not set.' }}</p>
              </div>
              <div class="relative">
                <input v-model="mailForm.password" :type="showSmtpPassword ? 'text' : 'password'" class="field pr-9" autocomplete="new-password" />
                <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 p-1 text-slate-400 hover:text-slate-600 dark:hover:text-zinc-300" @click="showSmtpPassword = !showSmtpPassword">
                  <svg v-if="!showSmtpPassword" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
                  <svg v-else class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                </button>
              </div>
            </div>
          </template>
        </div>
        <div class="flex justify-end border-t border-slate-100 bg-slate-50/70 px-5 py-4">
          <button class="btn btn-muted" type="button" @click="saveMailConfig()">Save email settings</button>
        </div>
      </div>
    </section>

    <!-- ── Google Sheets (Backup Destination) ── -->
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
      <button type="button" class="flex w-full items-center justify-between gap-4 border-b border-slate-100 bg-slate-50/70 px-5 py-4 text-left" :aria-expanded="openGroups.notifications" @click="toggleGroup('notifications')">
        <span>
          <span class="block text-xs font-extrabold uppercase text-teal-700">Notifications</span>
          <span class="mt-1 block text-lg font-extrabold text-slate-950">Review Reminders</span>
          <span class="mt-1 block text-sm font-medium text-slate-500">Stored now for future reminder delivery channels.</span>
        </span>
        <span class="grid h-8 w-8 place-items-center rounded-xl border border-slate-200 bg-white text-slate-500 transition" :class="openGroups.notifications ? 'rotate-180' : ''">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6" /></svg>
        </span>
      </button>
      <div v-if="openGroups.notifications" class="divide-y divide-slate-100">
        <div class="grid gap-3 px-5 py-4 md:grid-cols-[16rem_1fr] md:items-center">
          <span class="font-extrabold text-slate-800">Daily review</span>
          <label class="flex h-11 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 text-sm font-bold text-slate-700"><input v-model="groupSettings.notifications.daily_review_enabled" type="checkbox" class="accent-teal-700" />Enabled</label>
          <span class="font-extrabold text-slate-800">Weekly review</span>
          <label class="flex h-11 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 text-sm font-bold text-slate-700"><input v-model="groupSettings.notifications.weekly_review_enabled" type="checkbox" class="accent-teal-700" />Enabled</label>
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

    <!-- Daily Quote -->
    <section class="card overflow-hidden p-0">
      <button type="button" class="flex w-full items-center justify-between gap-4 border-b border-slate-100 bg-slate-50/70 px-5 py-4 text-left dark:border-zinc-800 dark:bg-zinc-800/40" :aria-expanded="openGroups.quote" @click="toggleGroup('quote')">
        <div>
          <p class="font-extrabold text-slate-900 dark:text-zinc-100">Daily Quote</p>
          <span class="mt-1 block text-sm font-medium text-slate-500">Quote harian dari AI berdasarkan tema pilihanmu. Tampil di sidebar.</span>
        </div>
        <span class="grid h-8 w-8 place-items-center rounded-xl border border-slate-200 bg-white text-slate-500 transition dark:border-zinc-700 dark:bg-zinc-900" :class="openGroups.quote ? 'rotate-180' : ''">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
        </span>
      </button>
      <div v-if="openGroups.quote" class="divide-y divide-slate-100 dark:divide-zinc-800 p-5 space-y-4">
        <!-- Enable toggle -->
        <div class="flex items-center justify-between gap-4">
          <div>
            <p class="text-sm font-extrabold text-slate-800 dark:text-zinc-200">Aktifkan daily quote</p>
            <p class="text-xs text-slate-400 dark:text-zinc-500">Muncul di sidebar setiap hari, beda-beda tiap hari</p>
          </div>
          <label class="relative inline-flex cursor-pointer items-center">
            <input v-model="quoteForm.enabled" type="checkbox" class="peer sr-only" />
            <div class="peer h-6 w-11 rounded-full bg-slate-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition-all after:content-[''] peer-checked:bg-teal-600 peer-checked:after:translate-x-full dark:bg-zinc-700"></div>
          </label>
        </div>

        <!-- Theme tag input -->
        <div class="pt-4">
          <p class="label mb-2">Tema quote <span class="font-normal text-slate-400">(ketik bebas, Enter untuk tambah)</span></p>
          <div class="flex flex-wrap items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800 min-h-[42px]">
            <span
              v-for="theme in quoteForm.themes"
              :key="theme"
              class="inline-flex items-center gap-1 rounded-full bg-teal-50 px-2.5 py-0.5 text-xs font-extrabold capitalize text-teal-700 dark:bg-teal-900/30 dark:text-teal-400"
            >
              {{ theme }}
              <button
                v-if="quoteForm.themes.length > 1"
                type="button"
                class="ml-0.5 text-teal-400 hover:text-teal-700 dark:hover:text-teal-300 leading-none"
                :title="`Hapus tema '${theme}'`"
                @click="removeTheme(theme)"
              >×</button>
            </span>
            <input
              v-model="quoteTagInput"
              type="text"
              placeholder="Ketik tema lalu Enter..."
              class="min-w-[140px] flex-1 bg-transparent text-xs font-medium text-slate-700 placeholder:text-slate-300 focus:outline-none dark:text-zinc-200 dark:placeholder:text-zinc-600"
              @keydown="onThemeKeydown"
              @blur="addTheme"
            />
          </div>
          <p class="mt-1 text-xs text-slate-400 dark:text-zinc-600">Contoh: motivation, stoic, romantic, introvert — apa saja boleh</p>
        </div>

        <div class="pt-2 flex justify-end">
          <button class="btn btn-primary" :disabled="quoteSaving" @click="saveQuoteConfig">
            {{ quoteSaving ? 'Menyimpan...' : 'Simpan Quote Settings' }}
          </button>
        </div>
      </div>
    </section>

    <!-- ── AI Provider ── -->
    <section class="card overflow-hidden p-0">
      <button type="button" class="flex w-full items-center justify-between gap-4 border-b border-slate-100 bg-slate-50/70 px-5 py-4 text-left" :aria-expanded="openGroups.ai" @click="toggleGroup('ai')">
        <div>
          <p class="font-extrabold text-slate-900 dark:text-zinc-100">AI Provider</p>
          <span class="mt-1 block text-sm font-medium text-slate-500 dark:text-zinc-400">Pilih provider untuk setiap fitur. Journaling bisa diubah ke AdaCode.ai.</span>
        </div>
        <span class="grid h-8 w-8 place-items-center rounded-xl border border-slate-200 bg-white text-slate-500 transition dark:border-zinc-700 dark:bg-zinc-900" :class="openGroups.ai ? 'rotate-180' : ''">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
        </span>
      </button>
      <div v-if="openGroups.ai" class="divide-y divide-slate-100 dark:divide-zinc-800 p-5 space-y-4">
        <!-- Feature Providers -->
        <div class="space-y-3">
          <p class="text-sm font-bold text-slate-700 dark:text-zinc-300">Provider per Fitur</p>
          
          <div class="grid gap-2 md:grid-cols-[16rem_1fr] md:items-center">
            <span class="text-sm font-semibold text-slate-600 dark:text-zinc-400">Chat</span>
            <select v-model="featureProviders.chat" class="field">
              <option value="groq">Groq</option>
              <option value="adacode">AdaCode.ai</option>
            </select>
          </div>

          <div class="grid gap-2 md:grid-cols-[16rem_1fr] md:items-center">
            <span class="text-sm font-semibold text-slate-600 dark:text-zinc-400">Journal</span>
            <select v-model="featureProviders.journal" class="field">
              <option value="groq">Groq</option>
              <option value="adacode">AdaCode.ai</option>
            </select>
          </div>

          <div class="grid gap-2 md:grid-cols-[16rem_1fr] md:items-center">
            <span class="text-sm font-semibold text-slate-600 dark:text-zinc-400">Daily Quote</span>
            <select v-model="featureProviders.quote" class="field">
              <option value="groq">Groq</option>
              <option value="adacode">AdaCode.ai</option>
            </select>
          </div>
        </div>

        <!-- Global Provider (legacy) -->
        <div class="pt-2">
          <p class="text-xs text-slate-400 dark:text-zinc-500 mb-2">Provider utama (fallback untuk fitur lama)</p>
          <div class="grid gap-3 md:grid-cols-[16rem_1fr] md:items-center">
            <span class="font-extrabold text-slate-800 dark:text-zinc-200">Provider</span>
            <select v-model="aiForm.provider" class="field">
              <option value="groq">Groq (default, untuk journaling & chat)</option>
              <option value="adacode">AdaCode.ai (Claude Sonnet, GPT-5.3, dll)</option>
            </select>
          </div>
        </div>

        <!-- Groq API Key -->
        <div v-if="aiForm.provider === 'groq'" class="grid gap-3 md:grid-cols-[16rem_1fr] md:items-center">
          <div>
            <span class="font-extrabold text-slate-800 dark:text-zinc-200">Groq API Key</span>
            <p class="text-xs font-semibold text-slate-500 dark:text-zinc-500">{{ aiForm.groq_api_key_set ? 'Already saved. Fill in to replace.' : 'Not set.' }}</p>
          </div>
          <div class="relative">
            <input
              v-model="aiForm.groq_api_key"
              :type="showGroqApiKey ? 'text' : 'password'"
              class="field pr-9"
              placeholder="gsk_xxxxxxxxxxxxxxxxxxxxxxxx"
              autocomplete="new-password"
            />
            <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 p-1 text-slate-400 hover:text-slate-600 dark:hover:text-zinc-300" @click="showGroqApiKey = !showGroqApiKey">
              <svg v-if="!showGroqApiKey" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
              <svg v-else class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
            </button>
          </div>
        </div>
        <p v-if="aiForm.provider === 'groq' && aiForm.groq_api_key_set" class="text-xs font-semibold text-teal-600 dark:text-teal-400 ml-3">
          ✓ API key sudah dikonfigurasi
        </p>
        <p v-if="aiForm.provider === 'groq' && !aiForm.groq_api_key_set" class="text-xs font-semibold text-slate-400 dark:text-zinc-500 ml-3">
          Daftar gratis di <span class="font-semibold text-teal-600">console.groq.com</span> → API Keys → Create API Key
        </p>

        <!-- AdaCode API Key -->
        <div v-if="aiForm.provider === 'adacode'" class="grid gap-3 md:grid-cols-[16rem_1fr] md:items-center">
          <div>
            <span class="font-extrabold text-slate-800 dark:text-zinc-200">AdaCode API Key</span>
            <p class="text-xs font-semibold text-slate-500 dark:text-zinc-500">{{ aiForm.api_key_set ? 'Already saved. Fill in to replace.' : 'Not set.' }}</p>
          </div>
          <input v-model="aiForm.api_key" class="field" type="password" placeholder="sk-ac-..." autocomplete="new-password" />
        </div>

        <!-- Model selector (AdaCode only) -->
        <div v-if="aiForm.provider === 'adacode'" class="grid gap-3 md:grid-cols-[16rem_1fr] md:items-center">
          <span class="font-extrabold text-slate-800 dark:text-zinc-200">Model</span>
          <select v-model="aiForm.model" class="field">
            <option value="claude-sonnet-4-6">Claude Sonnet 4.6 (recommended)</option>
            <option value="gpt-5.3">GPT-5.3</option>
            <option value="gemini-3-flash">Gemini 3 Flash</option>
            <option value="glm-4.7">GLM 4.7</option>
            <option value="claude-haiku-3-5">Claude Haiku 3.5</option>
            <option value="qwen3.6-flash">Qwen 3.6 Flash</option>
          </select>
        </div>

        <!-- Info box -->
        <div class="rounded-xl bg-blue-50 px-4 py-3 text-sm font-medium text-blue-700 dark:bg-blue-900/20 dark:text-blue-400">
          Setiap fitur bisa menggunakan provider berbeda. API key harus tersedia untuk provider yang dipilih per fitur.
        </div>

        <!-- Save button -->
        <div class="flex items-center justify-end gap-3 pt-2">
          <button class="btn btn-secondary" :disabled="featureProviderSaving" @click="saveFeatureProviders">
            {{ featureProviderSaving ? 'Saving...' : 'Save Provider Per Fitur' }}
          </button>
          <button class="btn btn-primary" :disabled="aiSaving" @click="saveAiConfig">
            {{ aiSaving ? 'Saving...' : 'Save AI Settings' }}
          </button>
        </div>
      </div>
    </section>
  </div>
</template>
