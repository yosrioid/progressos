<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { unwrap } from '../api';
import { api } from '../api';
import { toast } from '../feedback';
import { useConfiguration } from '../composables/useConfiguration';
import SettingsSection from '../components/configuration/SettingsSection.vue';
import { timezones } from '../stores/configuration';

const {
    loading, saving, loadError, modules, frequencies, connection, syncs, runs,
    groupSettings, connectionForm, authForm, mailForm, authConfig, mailConfig,
    credentialFileName, openGroups, aiForm, showProviderApiKey, availableProviders,
    aiSaving, featureProviderSaving, featureProviders, quoteConfig, quoteForm,
    quoteTagInput, quoteSaving, timezonePreview,
    load, toggleGroup, moduleLabel, selectCredentialFile, saveConnection,
    saveSettings, verifyConnection, addSync, saveSync, saveAuthConfig, saveMailConfig,
    saveAll, removeSync, runNow, addTheme, removeTheme, onProviderChange,
    saveFeatureProviders,
} = useConfiguration();

const showClientSecret = ref(false);
const showMailApiKey = ref(false);
const showSmtpPassword = ref(false);
const showGroqApiKey = ref(false);

const googleHelpOpen = ref(false);
const googleSsoHelpOpen = ref(false);
const resendHelpOpen = ref(false);

const activeProviderKeyValue = computed(() => {
    const p = aiForm.value.provider;
    if (p === 'groq') return aiForm.value.groq_api_key || '';
    if (p === 'adacode') return aiForm.value.api_key || '';
    return aiForm.value.provider_keys[p] || '';
});

const activeProviderKeySet = computed(() => {
    const p = aiForm.value.provider;
    if (p === 'groq') return !!aiForm.value.groq_api_key_set;
    if (p === 'adacode') return !!aiForm.value.api_key_set;
    return !!aiForm.value.provider_keys_set[p];
});

const providerPlaceholder = computed(() => {
    const p = aiForm.value.provider;
    if (p === 'groq') return 'gsk_xxxxxxxxxxxxxxxxxxxxxxxx';
    if (p === 'adacode') return 'sk-ac-...';
    const provider = availableProviders.value.find((x) => x.id === p);
    return provider?.id ? `${provider.id} API key` : '';
});

const providerSignUpUrl = computed(() => {
    const p = aiForm.value.provider;
    if (p === 'groq') return 'console.groq.com';
    return '';
});

function onProviderKeyInput(e: Event) {
    const target = e.target as HTMLInputElement;
    const value = target.value;
    const p = aiForm.value.provider;
    if (p === 'groq') aiForm.value.groq_api_key = value;
    else if (p === 'adacode') aiForm.value.api_key = value;
    else aiForm.value.provider_keys = { ...aiForm.value.provider_keys, [p]: value };
}

function onThemeKeydown(e: KeyboardEvent) {
    if (e.key === 'Enter' || e.key === ',') {
        e.preventDefault();
        addTheme();
    } else if (e.key === 'Backspace' && quoteTagInput.value === '' && quoteForm.value.themes.length > 1) {
        quoteForm.value.themes.pop();
    }
}

async function saveAiConfig() {
    aiSaving.value = true;
    try {
        const payload: any = {
            provider: aiForm.value.provider,
            model: aiForm.value.model,
        };
        if (aiForm.value.provider === 'groq' && aiForm.value.groq_api_key) {
            payload.groq_api_key = aiForm.value.groq_api_key;
        } else if (aiForm.value.provider === 'adacode' && aiForm.value.api_key) {
            payload.api_key = aiForm.value.api_key;
        }
        const dynamic = Object.entries(aiForm.value.provider_keys || {})
            .filter(([, v]) => v && !v.startsWith('***'))
            .reduce<Record<string, string>>((acc, [k, v]) => ({ ...acc, [k]: v }), {});
        if (Object.keys(dynamic).length > 0) payload.provider_keys = dynamic;

        const res = await api.put('/api/admin/configuration/ai', payload);
        unwrap(res);
        toast({ tone: 'success', title: 'AI settings saved' });
    } catch (e: any) {
        toast({ tone: 'error', title: 'Failed to save', message: e?.response?.data?.message ?? e?.response?.data?.errors ?? 'Terjadi kesalahan.' });
    } finally {
        aiSaving.value = false;
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

        <!-- General -->
        <SettingsSection
            title="General"
            subtitle="Core labels used across the application shell and future exports."
            :expanded="openGroups.general"
            @toggle="toggleGroup('general')"
        >
            <template #heading>Project Identity</template>
            <div class="divide-y divide-slate-100">
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
        </SettingsSection>

        <!-- Appearance -->
        <SettingsSection
            title="Appearance"
            subtitle="Visual preferences reserved for app-wide branding."
            :expanded="openGroups.appearance"
            @toggle="toggleGroup('appearance')"
        >
            <template #heading>Brand</template>
            <div class="divide-y divide-slate-100">
                <div class="grid gap-3 px-5 py-4 md:grid-cols-[16rem_1fr] md:items-center">
                    <span class="font-extrabold text-slate-800">Favicon URL</span>
                    <input v-model="groupSettings.appearance.favicon_url" class="field" placeholder="https://example.com/favicon.png" />
                </div>
            </div>
        </SettingsSection>

        <!-- Auth/SSO -->
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

        <!-- Email -->
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

        <!-- Google Sheets Backup -->
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
                    <div class="flex items-center gap-2">
                        <h3 class="font-extrabold">Service Account</h3>
                        <div class="relative">
                            <button type="button" class="grid h-7 w-7 place-items-center rounded-full border border-slate-200 bg-white text-xs font-black text-slate-500 hover:border-teal-200 hover:text-teal-700" aria-label="How to get Google service account JSON" @click="googleHelpOpen = !googleHelpOpen">?</button>
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
                <div class="divide-y divide-slate-100">
                    <div class="grid gap-3 px-5 py-4 md:grid-cols-[16rem_1fr] md:items-center">
                        <span class="font-extrabold text-slate-800">Connection name</span>
                        <input v-model="connectionForm.name" class="field" placeholder="Google Sheets" />
                    </div>
                    <div class="grid gap-3 px-5 py-4 md:grid-cols-[16rem_1fr] md:items-center">
                        <span class="font-extrabold text-slate-800">Spreadsheet ID</span>
                        <input v-model="connectionForm.spreadsheet_id" class="field" placeholder="1AbC..." />
                    </div>
                    <div class="grid gap-3 px-5 py-4 md:grid-cols-[16rem_1fr]">
                        <span class="font-extrabold text-slate-800">Service account JSON</span>
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

        <!-- Notifications -->
        <SettingsSection
            title="Notifications"
            subtitle="Stored now for future reminder delivery channels."
            :expanded="openGroups.notifications"
            @toggle="toggleGroup('notifications')"
        >
            <template #heading>Review Reminders</template>
            <div class="divide-y divide-slate-100">
                <div class="grid gap-3 px-5 py-4 md:grid-cols-[16rem_1fr] md:items-center">
                    <span class="font-extrabold text-slate-800">Daily review</span>
                    <label class="flex h-11 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 text-sm font-bold text-slate-700"><input v-model="groupSettings.notifications.daily_review_enabled" type="checkbox" class="accent-teal-700" />Enabled</label>
                    <span class="font-extrabold text-slate-800">Weekly review</span>
                    <label class="flex h-11 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 text-sm font-bold text-slate-700"><input v-model="groupSettings.notifications.weekly_review_enabled" type="checkbox" class="accent-teal-700" />Enabled</label>
                </div>
            </div>
        </SettingsSection>

        <!-- Sync Data -->
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

        <!-- Backup History -->
        <SettingsSection
            title="Audit"
            subtitle="Recent backup runs and generated spreadsheet-compatible CSV files."
            :expanded="openGroups.history"
            @toggle="toggleGroup('history')"
        >
            <template #heading>Backup History</template>
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
        </SettingsSection>

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

        <!-- AI Provider -->
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
                <div class="space-y-3">
                    <p class="text-sm font-bold text-slate-700 dark:text-zinc-300">Provider per Fitur</p>
                    <div class="grid gap-2 md:grid-cols-[16rem_1fr] md:items-center">
                        <span class="text-sm font-semibold text-slate-600 dark:text-zinc-400">Chat</span>
                        <select v-model="featureProviders.chat" class="field">
                            <option v-for="p in availableProviders" :key="p.id" :value="p.id">{{ p.label }}</option>
                        </select>
                    </div>
                    <div class="grid gap-2 md:grid-cols-[16rem_1fr] md:items-center">
                        <span class="text-sm font-semibold text-slate-600 dark:text-zinc-400">Journal</span>
                        <select v-model="featureProviders.journal" class="field">
                            <option v-for="p in availableProviders" :key="p.id" :value="p.id">{{ p.label }}</option>
                        </select>
                    </div>
                    <div class="grid gap-2 md:grid-cols-[16rem_1fr] md:items-center">
                        <span class="text-sm font-semibold text-slate-600 dark:text-zinc-400">Daily Quote</span>
                        <select v-model="featureProviders.quote" class="field">
                            <option v-for="p in availableProviders" :key="p.id" :value="p.id">{{ p.label }}</option>
                        </select>
                    </div>
                </div>
                <div class="pt-2">
                    <p class="text-xs text-slate-400 dark:text-zinc-500 mb-2">Provider utama (fallback untuk fitur lama)</p>
                    <div class="grid gap-3 md:grid-cols-[16rem_1fr] md:items-center">
                        <span class="font-extrabold text-slate-800 dark:text-zinc-200">Provider</span>
                        <select v-model="aiForm.provider" class="field" @change="onProviderChange">
                            <option v-for="p in availableProviders" :key="p.id" :value="p.id">{{ p.label }} ({{ p.models.length }} model)</option>
                        </select>
                    </div>
                </div>
                <div
                    v-if="aiForm.provider"
                    class="grid gap-3 md:grid-cols-[16rem_1fr] md:items-center"
                >
                    <div>
                        <span class="font-extrabold text-slate-800 dark:text-zinc-200">
                            {{ availableProviders.find(p => p.id === aiForm.provider)?.label ?? aiForm.provider }} API Key
                        </span>
                        <p class="text-xs font-semibold text-slate-500 dark:text-zinc-500">
                            {{ activeProviderKeySet ? 'Already saved. Fill in to replace.' : 'Not set.' }}
                        </p>
                    </div>
                    <div class="relative">
                        <input
                            :value="activeProviderKeyValue"
                            @input="onProviderKeyInput"
                            :type="showProviderApiKey[aiForm.provider] ? 'text' : 'password'"
                            class="field pr-9"
                            :placeholder="providerPlaceholder"
                            autocomplete="new-password"
                        />
                        <button
                            type="button"
                            class="absolute right-2 top-1/2 -translate-y-1/2 p-1 text-slate-400 hover:text-slate-600 dark:hover:text-zinc-300"
                            @click="showProviderApiKey[aiForm.provider] = !showProviderApiKey[aiForm.provider]"
                        >
                            <svg v-if="!showProviderApiKey[aiForm.provider]" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg v-else class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                        </button>
                    </div>
                </div>
                <p v-if="activeProviderKeySet" class="text-xs font-semibold text-teal-600 dark:text-teal-400 ml-3">
                    ✓ API key sudah dikonfigurasi
                </p>
                <p v-if="!activeProviderKeySet && providerSignUpUrl" class="text-xs font-semibold text-slate-400 dark:text-zinc-500 ml-3">
                    Daftar di <span class="font-semibold text-teal-600">{{ providerSignUpUrl }}</span>
                </p>
                <div
                    v-if="(availableProviders.find(p => p.id === aiForm.provider)?.models.length ?? 0) > 1"
                    class="grid gap-3 md:grid-cols-[16rem_1fr] md:items-center"
                >
                    <span class="font-extrabold text-slate-800 dark:text-zinc-200">Model</span>
                    <select v-model="aiForm.model" class="field">
                        <option
                            v-for="m in (availableProviders.find(p => p.id === aiForm.provider)?.models ?? [])"
                            :key="m.id"
                            :value="m.id"
                        >{{ m.label }}</option>
                    </select>
                </div>
                <div class="rounded-xl bg-blue-50 px-4 py-3 text-sm font-medium text-blue-700 dark:bg-blue-900/20 dark:text-blue-400">
                    Setiap fitur bisa menggunakan provider berbeda. API key harus tersedia untuk provider yang dipilih per fitur.
                </div>
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