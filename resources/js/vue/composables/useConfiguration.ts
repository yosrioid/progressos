import { ref, computed, reactive } from 'vue';
import { api, unwrap } from '../api';
import { confirmAction, toast } from '../feedback';
import { timezones, useConfigurationStore } from '../stores/configuration';

const configuration = useConfigurationStore();

export function useConfiguration() {
    const loading = ref(true);
    const saving = ref(false);
    const loadError = ref('');
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
    const openGroups = reactive({ general: true, appearance: false, auth: true, mail: true, google_oauth: false, sync_data: false, notifications: false, history: false, quote: false, ai: false });

    // AI state
    const aiForm = ref({
        provider: 'groq',
        model: 'claude-sonnet-4-6',
        api_key: '',
        groq_api_key: '',
        groq_api_key_set: false,
        api_key_set: false,
        provider_keys: {} as Record<string, string>,
        provider_keys_set: {} as Record<string, boolean>,
    });
    const showProviderApiKey = ref<Record<string, boolean>>({});

    interface ProviderMeta {
        id: string;
        label: string;
        models: { id: string; label: string }[];
    }
    const availableProviders = ref<ProviderMeta[]>([]);
    const aiSaving = ref(false);
    const featureProviderSaving = ref(false);
    const featureProviders = ref({ chat: 'groq', journal: 'groq', quote: 'groq' });
    const quoteConfig = ref<{ enabled: boolean; themes: string[] }>({ enabled: false, themes: ['motivation'] });
    const quoteForm = ref({ enabled: false, themes: ['motivation'] });
    const quoteTagInput = ref('');
    const quoteSaving = ref(false);

    const timezonePreview = computed(() => {
        try {
            return new Intl.DateTimeFormat('en', { dateStyle: 'full', timeStyle: 'medium', timeZone: groupSettings.value.general.timezone || 'Asia/Jakarta' }).format(new Date());
        } catch {
            return 'Invalid timezone';
        }
    });

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
            const aiConfig = config.ai_config || {};
            aiForm.value = {
                provider: aiConfig.provider || 'groq',
                model: aiConfig.model || 'claude-sonnet-4-6',
                api_key: '',
                groq_api_key: aiConfig.groq_api_key_set ? '***already_set***' : '',
                groq_api_key_set: !!aiConfig.groq_api_key_set,
                api_key_set: !!aiConfig.api_key_set,
                provider_keys: { ...(aiConfig.provider_keys || {}) },
                provider_keys_set: { ...(aiConfig.provider_keys_set || {}) },
            };
            if (Array.isArray(aiConfig.providers)) {
                availableProviders.value = aiConfig.providers as ProviderMeta[];
            }
            try {
                const qData: any = await api.get('/api/v1/quote/config').then(unwrap);
                if (qData.quote_config) {
                    quoteConfig.value = { ...quoteConfig.value, ...qData.quote_config };
                    quoteForm.value = { enabled: quoteConfig.value.enabled, themes: [...quoteConfig.value.themes] };
                }
            } catch {
                // non-critical
            }
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

    function toggleGroup(key: keyof typeof openGroups) {
        openGroups[key] = !openGroups[key];
    }

    function moduleLabel(value: string) {
        return value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
    }

    function blankSync() {
        return { id: null, module: 'work_logs', frequency: 'daily', destination_sheet_name: 'work_logs', enabled: true };
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

    function onProviderChange() {
        const provider = availableProviders.value.find((p) => p.id === aiForm.value.provider);
        if (provider && provider.models.length > 0) {
            aiForm.value.model = provider.models[0].id;
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

    async function saveQuoteConfig() {
        quoteSaving.value = true;
        try {
            await api.put('/api/v1/quote/config', quoteForm.value).then(unwrap);
            quoteConfig.value = { ...quoteConfig.value, ...quoteForm.value };
            toast({ tone: 'success', title: 'Quote config saved', message: 'Quote configuration was updated.' });
        } catch (error: any) {
            toast({ tone: 'error', title: 'Quote save failed', message: error.response?.data?.message || 'Check the configuration.' });
        } finally {
            quoteSaving.value = false;
        }
    }

    return {
        loading, saving, loadError, modules, frequencies, connection, syncs, runs,
        groupSettings, connectionForm, authForm, mailForm, authConfig, mailConfig,
        credentialFileName, openGroups, aiForm, showProviderApiKey, availableProviders,
        aiSaving, featureProviderSaving, featureProviders, quoteConfig, quoteForm,
        quoteTagInput, quoteSaving, timezonePreview, configuration,
        load, toggleGroup, moduleLabel, selectCredentialFile, saveConnection,
        saveSettings, verifyConnection, addSync, saveSync, saveAuthConfig, saveMailConfig,
        saveAll, removeSync, runNow, addTheme, removeTheme, onProviderChange,
        saveAiConfig, saveFeatureProviders, saveQuoteConfig, blankSync,
    };
}
