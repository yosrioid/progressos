<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BackupRun;
use App\Models\Configuration;
use App\Services\AiProviderManager;
use App\Services\AiQuotaService;
use App\Services\BackupExportService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ConfigurationController extends Controller
{
    public function __construct(
        protected AiQuotaService $quotaService
    ) {}

    public function show(Request $request)
    {
        return ApiResponse::ok([
            'configuration' => [
                'app_version' => config('app.version'),
                'available_modules' => Configuration::SYNC_MODULES,
                'frequencies' => Configuration::SYNC_FREQUENCIES,
                'groups' => $this->groups(),
                'auth_config' => $this->authConfigPayload(),
                'mail_config' => $this->mailConfigPayload(),
                'connection' => $this->connectionPayload($this->googleConnection()),
                'syncs' => collect($this->syncs())->map(fn (array $sync) => $this->syncPayload($sync))->values(),
                'runs' => BackupRun::latest()->limit(12)->get()->map(fn (BackupRun $run) => $this->runPayload($run)),
                'ai_config' => $this->aiConfigPayload(),
            ],
        ]);
    }

    public function updateAuthConfig(Request $request)
    {
        $data = $request->validate([
            'google_sso_enabled' => ['sometimes', 'boolean'],
            'client_id' => ['nullable', 'string', 'max:500'],
            'client_secret' => ['nullable', 'string', 'max:500'],
        ]);

        $existing = Configuration::getValue(null, 'auth', 'google_oauth', []);
        $config = array_merge(is_array($existing) ? $existing : [], [
            'enabled' => $data['google_sso_enabled'] ?? ($existing['enabled'] ?? false),
            'client_id' => $data['client_id'] ?? ($existing['client_id'] ?? ''),
        ]);
        if (filled($data['client_secret'] ?? null)) {
            $config['client_secret'] = $data['client_secret'];
        }

        Configuration::setValue(null, 'auth', 'google_oauth', $config, encrypted: true);

        return ApiResponse::ok(['auth_config' => $this->authConfigPayload()], 'Google SSO settings saved.');
    }

    public function updateMailConfig(Request $request)
    {
        $data = $request->validate([
            'mailer' => ['required', 'in:log,resend,smtp'],
            'from_address' => ['nullable', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:120'],
            'api_key' => ['nullable', 'string', 'max:500'],
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
        ]);

        $existing = Configuration::getValue(null, 'mail', 'smtp', []);
        $config = array_merge(is_array($existing) ? $existing : [], [
            'mailer' => $data['mailer'],
            'from_address' => $data['from_address'] ?? ($existing['from_address'] ?? ''),
            'from_name' => $data['from_name'] ?? ($existing['from_name'] ?? ''),
        ]);
        if (filled($data['api_key'] ?? null)) {
            $config['api_key'] = $data['api_key'];
        }
        if (filled($data['host'] ?? null)) {
            $config['host'] = $data['host'];
            $config['port'] = $data['port'] ?? 587;
            $config['username'] = $data['username'] ?? '';
            $config['password'] = $data['password'] ?? ($existing['password'] ?? '');
        }

        Configuration::setValue(null, 'mail', 'smtp', $config, encrypted: true);

        return ApiResponse::ok(['mail_config' => $this->mailConfigPayload()], 'Email settings saved.');
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'general.app_name' => ['nullable', 'string', 'max:80'],
            'general.project_name' => ['nullable', 'string', 'max:120'],
            'general.tagline' => ['nullable', 'string', 'max:180'],
            'general.timezone' => ['nullable', 'string', 'max:80'],
            'appearance.theme' => ['nullable', 'in:system,light,dark'],
            'appearance.favicon_url' => ['nullable', 'string', 'max:500'],
            'notifications.daily_review_enabled' => ['sometimes', 'boolean'],
            'notifications.weekly_review_enabled' => ['sometimes', 'boolean'],
        ]);

        foreach (['general', 'appearance', 'notifications'] as $group) {
            if (array_key_exists($group, $data)) {
                Configuration::setValue(null, $group, 'settings', array_replace($this->defaultGroupSettings($group), $data[$group]));
            }
        }

        return ApiResponse::ok(['groups' => $this->groups()], 'Configuration settings saved.');
    }

    public function updateConnection(Request $request)
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'spreadsheet_id' => ['nullable', 'string', 'max:255'],
            'credentials_json' => ['nullable', 'string'],
        ]);

        $connection = $this->googleConnection();
        $credentials = $connection['credentials'] ?? null;
        if (! empty($data['credentials_json'])) {
            $credentials = json_decode($data['credentials_json'], true);
            if (! is_array($credentials)) {
                return ApiResponse::ok(['errors' => ['credentials_json' => ['Credential JSON is not valid.']]], 'Credential JSON is not valid.', 422);
            }
        }

        $connection = [
            'provider' => 'google_sheets',
            'name' => $data['name'] ?? $connection['name'] ?? 'Google Sheets',
            'spreadsheet_id' => $data['spreadsheet_id'] ?? $connection['spreadsheet_id'] ?? null,
            'credentials' => $credentials,
            'status' => 'draft',
            'last_verified_at' => null,
        ];

        Configuration::setValue(null, 'sync', 'google_sheets', $connection, true);

        return ApiResponse::item('connection', $this->connectionPayload($connection), 200, 'Backup connection saved.');
    }

    public function verifyConnection(Request $request)
    {
        $connection = $this->googleConnection();
        if (! $connection) {
            return ApiResponse::ok([], 'Add a connection before testing it.', 422);
        }

        $credentials = $connection['credentials'] ?? [];
        $valid = filled($connection['spreadsheet_id'] ?? null)
            && filled($credentials['client_email'] ?? null)
            && filled($credentials['private_key'] ?? null)
            && filled($credentials['project_id'] ?? null);

        $connection['status'] = $valid ? 'verified' : 'error';
        $connection['last_verified_at'] = $valid ? now()->toISOString() : null;
        Configuration::setValue(null, 'sync', 'google_sheets', $connection, true);

        return ApiResponse::item(
            'connection',
            $this->connectionPayload($connection),
            $valid ? 200 : 422,
            $valid ? 'Connection settings look valid.' : 'Spreadsheet ID, client_email, private_key, and project_id are required.'
        );
    }

    public function storeSync(Request $request, BackupExportService $exports)
    {
        $data = $this->validateSync($request);
        $syncs = $this->syncs();
        $sync = [
            ...$data,
            'id' => (string) Str::uuid(),
            'next_run_at' => $exports->nextRunAt($data['frequency'])->toISOString(),
            'last_run_at' => null,
        ];
        $syncs[] = $sync;
        $this->saveSyncs($syncs);

        return ApiResponse::item('sync', $this->syncPayload($sync), 201, 'Backup sync created.');
    }

    public function updateSync(Request $request, string $sync, BackupExportService $exports)
    {
        $data = $this->validateSync($request);
        $syncs = $this->syncs();
        $index = $this->syncIndex($syncs, $sync);
        abort_if($index === null, 404);

        $syncs[$index] = [
            ...$syncs[$index],
            ...$data,
            'next_run_at' => $syncs[$index]['next_run_at'] ?? $exports->nextRunAt($data['frequency'])->toISOString(),
        ];
        $this->saveSyncs($syncs);

        return ApiResponse::item('sync', $this->syncPayload($syncs[$index]), 200, 'Backup sync updated.');
    }

    public function destroySync(Request $request, string $sync)
    {
        $syncs = $this->syncs();
        $index = $this->syncIndex($syncs, $sync);
        abort_if($index === null, 404);
        array_splice($syncs, $index, 1);
        $this->saveSyncs($syncs);

        return response()->noContent();
    }

    public function runSync(Request $request, string $sync, BackupExportService $exports)
    {
        $syncs = $this->syncs();
        $index = $this->syncIndex($syncs, $sync);
        abort_if($index === null, 404);

        $run = $exports->run($request->user(), $syncs[$index], $this->googleConnection()); // admin user initiates the run
        $syncs[$index]['last_run_at'] = now()->toISOString();
        $syncs[$index]['next_run_at'] = $exports->nextRunAt($syncs[$index]['frequency'] ?? 'daily')->toISOString();
        $this->saveSyncs($syncs);

        return ApiResponse::item('run', $this->runPayload($run), 201, $run->status === 'completed' ? 'Backup completed.' : 'Backup failed.');
    }

    private function validateSync(Request $request): array
    {
        return $request->validate([
            'module' => ['required', Rule::in(Configuration::SYNC_MODULES)],
            'frequency' => ['required', Rule::in(Configuration::SYNC_FREQUENCIES)],
            'destination_sheet_name' => ['required', 'string', 'max:80'],
            'enabled' => ['sometimes', 'boolean'],
            'filters' => ['nullable', 'array'],
        ]);
    }

    private function groups(): array
    {
        return collect(Configuration::GROUPS)
            ->mapWithKeys(function (string $group) {
                $defaults = $this->defaultGroupSettings($group);
                $stored = Configuration::getValue(null, $group, 'settings', []);

                return [$group => array_replace($defaults, is_array($stored) ? $stored : [])];
            })
            ->all();
    }

    private function defaultGroupSettings(string $group): array
    {
        return match ($group) {
            'general' => [
                'app_name' => 'ProgressOS',
                'project_name' => 'ProgressOS',
                'tagline' => 'Personal operating system for progress, work, learning, and review.',
                'timezone' => 'Asia/Jakarta',
            ],
            'appearance' => [
                'theme' => 'system',
                'favicon_url' => '',
            ],
            'notifications' => [
                'daily_review_enabled' => false,
                'weekly_review_enabled' => false,
            ],
            default => [],
        };
    }

    private function googleConnection(): ?array
    {
        $connection = Configuration::getValue(null, 'sync', 'google_sheets');

        return is_array($connection) ? $connection : null;
    }

    private function syncs(): array
    {
        $syncs = Configuration::getValue(null, 'sync', 'backup_schedules', []);

        return is_array($syncs) ? array_values($syncs) : [];
    }

    private function saveSyncs(array $syncs): void
    {
        Configuration::setValue(null, 'sync', 'backup_schedules', array_values($syncs));
    }

    private function syncIndex(array $syncs, string $id): ?int
    {
        foreach ($syncs as $index => $sync) {
            if (($sync['id'] ?? null) === $id) {
                return $index;
            }
        }

        return null;
    }

    private function connectionPayload(?array $connection): ?array
    {
        if (! $connection) {
            return null;
        }

        return [
            'provider' => $connection['provider'] ?? 'google_sheets',
            'name' => $connection['name'] ?? 'Google Sheets',
            'spreadsheet_id' => $connection['spreadsheet_id'] ?? null,
            'status' => $connection['status'] ?? 'draft',
            'has_credentials' => filled($connection['credentials'] ?? null),
            'service_account_email' => $connection['credentials']['client_email'] ?? null,
            'last_verified_at' => $connection['last_verified_at'] ?? null,
        ];
    }

    private function syncPayload(array $sync): array
    {
        return [
            'id' => $sync['id'] ?? null,
            'module' => $sync['module'] ?? 'work_logs',
            'frequency' => $sync['frequency'] ?? 'daily',
            'destination_sheet_name' => $sync['destination_sheet_name'] ?? $sync['module'] ?? 'work_logs',
            'enabled' => (bool) ($sync['enabled'] ?? true),
            'last_run_at' => $sync['last_run_at'] ?? null,
            'next_run_at' => $sync['next_run_at'] ?? null,
        ];
    }

    private function runPayload(BackupRun $run): array
    {
        return [
            'id' => $run->id,
            'sync_id' => $run->sync_id,
            'module' => $run->module,
            'status' => $run->status,
            'rows_exported' => $run->rows_exported,
            'file_path' => $run->file_path,
            'error_message' => $run->error_message,
            'created_at' => $this->iso($run->created_at),
        ];
    }

    private function authConfigPayload(): array
    {
        $config = Configuration::getValue(null, 'auth', 'google_oauth', []);
        $config = is_array($config) ? $config : [];

        return [
            'google_sso_enabled' => (bool) ($config['enabled'] ?? false),
            'client_id' => $config['client_id'] ?? '',
            'has_client_secret' => filled($config['client_secret'] ?? null),
        ];
    }

    private function mailConfigPayload(): array
    {
        $config = Configuration::getValue(null, 'mail', 'smtp', []);
        $config = is_array($config) ? $config : [];

        return [
            'mailer' => $config['mailer'] ?? 'log',
            'from_address' => $config['from_address'] ?? '',
            'from_name' => $config['from_name'] ?? '',
            'has_api_key' => filled($config['api_key'] ?? null),
            'host' => $config['host'] ?? '',
            'port' => $config['port'] ?? 587,
            'username' => $config['username'] ?? '',
            'has_password' => filled($config['password'] ?? null),
        ];
    }

    private function iso(mixed $value): ?string
    {
        return $value instanceof Carbon ? $value->toISOString() : null;
    }

    public function getAiConfig(Request $request)
    {
        $user = $request->user();
        $provider = $request->query('provider', 'groq');

        // Get usage for this provider
        $usage = app(AiProviderManager::class)->getUsage($user, $provider);

        return ApiResponse::ok([
            'usage' => $usage,
        ]);
    }

    public function saveAiConfig(Request $request)
    {
        $user = $request->user();

        // Allowlists are derived from config/ai.php so adding a new
        // provider there automatically opens up validation here.
        $providerRule = 'in:'.implode(',', array_keys((array) config('ai.providers', [])));
        $groqAllowedModels = (array) config('ai.providers.groq.allowed_models', []);
        $adacodeAllowedModels = (array) config('ai.providers.adacode.allowed_models', []);
        $modelAllowed = array_merge($groqAllowedModels, $adacodeAllowedModels);
        // Fail fast on misconfiguration: if neither provider in config/ai.php
        // declares an allowed_models list, accepting "any string|max:100"
        // would let admins persist a model id we don't actually support.
        // Better to 500 the request so the missing-config bug surfaces now.
        if ($modelAllowed === []) {
            abort(500, 'AI model allowlist is not configured. Check config/ai.php.');
        }
        $modelRule = 'in:'.implode(',', $modelAllowed);

        $data = $request->validate([
            'provider' => ['required', $providerRule],
            'groq_api_key' => ['nullable', 'string', 'max:500'],
            'api_key' => ['nullable', 'string', 'max:500'],
            'model' => ['nullable', $modelRule],
            // Dynamic per-provider keys. The keys of this map must
            // match a provider id from config/ai.php — anything else
            // is rejected below to keep the storage well-formed.
            'provider_keys' => ['nullable', 'array'],
            'provider_keys.*' => ['nullable', 'string', 'max:500'],
        ]);

        // Store global AI provider config (for system-wide settings)
        $globalAiConfig = Configuration::getValue(null, 'ai', 'provider_config', []);
        $globalAiConfig = is_array($globalAiConfig) ? $globalAiConfig : [];
        $globalAiConfig['provider'] = $data['provider'];

        if ($data['provider'] === 'groq' && filled($data['groq_api_key'])) {
            $globalAiConfig['groq_api_key'] = $data['groq_api_key'];
        }

        if ($data['provider'] === 'adacode' && filled($data['api_key'])) {
            $globalAiConfig['api_key'] = $data['api_key'];
        }

        // Persist any dynamic provider keys the admin submitted.
        // We only accept provider ids that are known to the
        // AiProviderManager so the storage can't be poisoned with
        // junk keys (the legacy `groq_api_key` / `api_key` fields
        // continue to work unchanged).
        $existingProviderKeys = is_array($globalAiConfig['provider_keys'] ?? null)
            ? $globalAiConfig['provider_keys']
            : [];
        $newProviderKeys = is_array($data['provider_keys'] ?? null) ? $data['provider_keys'] : [];
        $allowedProviderIds = array_keys((array) config('ai.providers', []));
        foreach ($newProviderKeys as $providerId => $key) {
            if (! in_array($providerId, $allowedProviderIds, true)) {
                return ApiResponse::ok(
                    ['errors' => ['provider_keys' => ["Unknown AI provider '{$providerId}'."]]],
                    'Unknown AI provider.',
                    422,
                );
            }
            if (filled($key)) {
                $existingProviderKeys[$providerId] = $key;
            }
        }
        $globalAiConfig['provider_keys'] = $existingProviderKeys;

        if (filled($data['model'])) {
            // Double-check the model is allowed for the chosen provider. The
            // 'modelRule' above allowed ANY model in the union, which is
            // convenient when the admin hasn't picked a provider yet, but
            // for storage we want provider-specific validation so a Groq
            // admin can't accidentally write a Claude model into the
            // provider_config and confuse later requests.
            if (! app(AiProviderManager::class)->isAllowedModel($data['provider'], $data['model'])) {
                return ApiResponse::ok(
                    [
                        'errors' => [
                            'model' => [
                                "Model '{$data['model']}' is not allowed for provider '{$data['provider']}'.",
                            ],
                        ],
                    ],
                    'Model not allowed for selected provider.',
                    422,
                );
            }
            $globalAiConfig['model'] = $data['model'];
        }

        Configuration::setValue(null, 'ai', 'provider_config', $globalAiConfig, encrypted: true);

        $aiConfigPayload = $this->aiConfigPayload();

        return ApiResponse::ok($aiConfigPayload, 'AI settings saved.');
    }

    public function saveFeatureProviders(Request $request)
    {
        // Allowlist derived from config/ai.php — adding a provider there
        // automatically extends this validator's accepted values.
        $providerRule = 'in:'.implode(',', array_keys((array) config('ai.providers', [])));

        $data = $request->validate([
            'feature_providers' => ['required', 'array'],
            'feature_providers.chat' => ['required', $providerRule],
            'feature_providers.journal' => ['required', $providerRule],
            'feature_providers.quote' => ['required', $providerRule],
        ]);

        $featureProviders = $data['feature_providers'];

        // Persist the complete map so AiProviderManager::resolveProvider() can
        // resolve 'chat', 'journal' and 'quote' from the same source.
        $globalAiConfig = Configuration::getValue(null, 'ai', 'provider_config', []);
        $globalAiConfig = is_array($globalAiConfig) ? $globalAiConfig : [];
        $globalAiConfig['feature_providers'] = $featureProviders;
        // Keep the legacy 'provider' field aligned with the chat feature so
        // older code paths that read $aiConfig['provider'] continue to work.
        $globalAiConfig['provider'] = $featureProviders['chat'];
        Configuration::setValue(null, 'ai', 'provider_config', $globalAiConfig, encrypted: true);

        // Mirror the quote provider into the quote config bucket. The quote
        // pipeline reads its provider from quote/groq.provider, so we keep
        // that side-channel updated for back-compat.
        $quoteConfig = Configuration::getValue(null, 'quote', 'groq', []);
        $quoteConfig = is_array($quoteConfig) ? $quoteConfig : [];
        $quoteConfig['provider'] = $featureProviders['quote'];
        Configuration::setValue(null, 'quote', 'groq', $quoteConfig);

        return ApiResponse::ok($this->aiConfigPayload(), 'Feature providers saved.');
    }

    private function aiConfigPayload(): array
    {
        $aiConfig = Configuration::getValue(null, 'ai', 'provider_config', []);
        $aiConfig = is_array($aiConfig) ? $aiConfig : [];

        $quoteConfig = Configuration::getValue(null, 'quote', 'groq', []);
        $quoteConfig = is_array($quoteConfig) ? $quoteConfig : [];

        // Resolve the active provider per feature from the single source of truth.
        $featureProviders = is_array($aiConfig['feature_providers'] ?? null)
            ? $aiConfig['feature_providers']
            : [];
        $defaultProvider = $aiConfig['provider'] ?? 'groq';
        $chatProvider = $featureProviders['chat'] ?? $defaultProvider;
        $journalProvider = $featureProviders['journal'] ?? $defaultProvider;
        $quoteProvider = $featureProviders['quote'] ?? $quoteConfig['provider'] ?? $defaultProvider;

        // Pick the model for the default (chat) provider so the payload never
        // lies about model/provider alignment.
        $model = $this->resolveModelForProvider($chatProvider, $aiConfig);

        // Build the provider registry payload so the frontend can render a
        // dropdown instead of letting admins type free-text model names.
        // Keys here match config/ai.php, so this stays in sync with the
        // allowlists enforced by saveAiConfig/saveFeatureProviders.
        $providerRegistry = [];
        foreach ((array) config('ai.providers', []) as $key => $cfg) {
            $allowed = (array) ($cfg['allowed_models'] ?? []);
            $models = array_map(
                fn (string $model) => ['id' => $model, 'label' => $model],
                $allowed
            );
            $providerRegistry[] = [
                'id' => $key,
                'label' => $cfg['name'] ?? ucfirst((string) $key),
                'models' => $models,
            ];
        }

        // Surface which provider keys the admin has set. The backend
        // stores actual keys encrypted; the UI only needs a "set / not set"
        // hint so it can render the "Already saved" label.
        $providerKeysSet = [];
        if (! empty($aiConfig['groq_api_key'] ?? null)) {
            $providerKeysSet['groq'] = true;
        }
        if (! empty($aiConfig['api_key'] ?? null)) {
            $providerKeysSet['adacode'] = true;
        }
        foreach ((array) ($aiConfig['provider_keys'] ?? []) as $providerId => $_) {
            $providerKeysSet[(string) $providerId] = true;
        }

        return [
            'provider' => $chatProvider,
            'model' => $model,
            'groq_api_key_set' => ! empty($aiConfig['groq_api_key'] ?? null),
            'api_key_set' => ! empty($aiConfig['api_key'] ?? null),
            'feature_providers' => [
                'chat' => $chatProvider,
                'journal' => $journalProvider,
                'quote' => $quoteProvider,
            ],
            'providers' => $providerRegistry,
            'provider_keys_set' => $providerKeysSet,
        ];
    }

    /**
     * Return the model name appropriate for the active provider. AdaCode users
     * get the configured AdaCode model (or the AdaCode default), Groq users
     * get the Groq default. Previously this defaulted to 'claude-sonnet-4-6'
     * regardless of provider, which misled users into thinking their Groq
     * request was using a Claude model.
     */
    private function resolveModelForProvider(string $provider, array $aiConfig): string
    {
        if ($provider === 'adacode') {
            return $aiConfig['model'] ?? config('ai.providers.adacode.chat_model', 'claude-sonnet-4-6');
        }

        // Groq: honour the env-backed config default. Allow admin to override
        // via provider_config.groq_model if they have set one.
        return $aiConfig['groq_model'] ?? config('ai.providers.groq.chat_model', 'llama-3.1-8b-instant');
    }

    public function checkQuota(Request $request)
    {
        $user = $request->user();

        // Resolve which provider to inspect: 'chat' feature first (admin-set
        // feature_providers[chat]), then legacy global provider, then default.
        $globalAiConfig = Configuration::getValue(null, 'ai', 'provider_config', []);
        $globalAiConfig = is_array($globalAiConfig) ? $globalAiConfig : [];
        $provider = $globalAiConfig['feature_providers']['chat']
            ?? $globalAiConfig['provider']
            ?? 'groq';

        $quotaInfo = $this->quotaService->checkQuota($user, $provider);

        // If using AdaCode, optionally overlay real quota from the provider.
        if ($provider === 'adacode') {
            $apiKey = $globalAiConfig['api_key'] ?? '';
            $externalQuota = $this->quotaService->fetchExternalQuota($apiKey);
            if ($externalQuota) {
                $quotaInfo['external_usage'] = $externalQuota['usage'];
                $quotaInfo['external_limit'] = $externalQuota['limit'];
                $quotaInfo['external_reset_at'] = $externalQuota['reset_at'];
            }
        }

        return ApiResponse::ok([
            'quota' => $quotaInfo,
            'provider' => $provider,
        ]);
    }
}
