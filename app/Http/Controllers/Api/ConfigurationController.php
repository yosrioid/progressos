<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BackupRun;
use App\Models\Configuration;
use App\Services\BackupExportService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ConfigurationController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        return ApiResponse::ok([
            'configuration' => [
                'available_modules' => Configuration::SYNC_MODULES,
                'frequencies' => Configuration::SYNC_FREQUENCIES,
                'groups' => $this->groups($request),
                'sections' => [
                    'general' => true,
                    'appearance' => true,
                    'google_oauth' => true,
                    'sync_data' => true,
                    'notifications' => true,
                    'history' => true,
                ],
                'connection' => $this->connectionPayload($this->googleConnection($request)),
                'syncs' => collect($this->syncs($request))->map(fn (array $sync) => $this->syncPayload($sync))->values(),
                'runs' => BackupRun::ownedBy($user)->latest()->limit(12)->get()->map(fn (BackupRun $run) => $this->runPayload($run)),
            ],
        ]);
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
                Configuration::setValue($request->user(), $group, 'settings', array_replace($this->defaultGroupSettings($group), $data[$group]));
            }
        }

        return ApiResponse::ok(['groups' => $this->groups($request)], 'Configuration settings saved.');
    }

    public function updateConnection(Request $request)
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'spreadsheet_id' => ['nullable', 'string', 'max:255'],
            'credentials_json' => ['nullable', 'string'],
        ]);

        $connection = $this->googleConnection($request);
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

        Configuration::setValue($request->user(), 'sync', 'google_sheets', $connection, true);

        return ApiResponse::item('connection', $this->connectionPayload($connection), 200, 'Backup connection saved.');
    }

    public function verifyConnection(Request $request)
    {
        $connection = $this->googleConnection($request);
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
        Configuration::setValue($request->user(), 'sync', 'google_sheets', $connection, true);

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
        $syncs = $this->syncs($request);
        $sync = [
            ...$data,
            'id' => (string) Str::uuid(),
            'next_run_at' => $exports->nextRunAt($data['frequency'])->toISOString(),
            'last_run_at' => null,
        ];
        $syncs[] = $sync;
        $this->saveSyncs($request, $syncs);

        return ApiResponse::item('sync', $this->syncPayload($sync), 201, 'Backup sync created.');
    }

    public function updateSync(Request $request, string $sync, BackupExportService $exports)
    {
        $data = $this->validateSync($request);
        $syncs = $this->syncs($request);
        $index = $this->syncIndex($syncs, $sync);
        abort_if($index === null, 404);

        $syncs[$index] = [
            ...$syncs[$index],
            ...$data,
            'next_run_at' => $syncs[$index]['next_run_at'] ?? $exports->nextRunAt($data['frequency'])->toISOString(),
        ];
        $this->saveSyncs($request, $syncs);

        return ApiResponse::item('sync', $this->syncPayload($syncs[$index]), 200, 'Backup sync updated.');
    }

    public function destroySync(Request $request, string $sync)
    {
        $syncs = $this->syncs($request);
        $index = $this->syncIndex($syncs, $sync);
        abort_if($index === null, 404);
        array_splice($syncs, $index, 1);
        $this->saveSyncs($request, $syncs);

        return response()->noContent();
    }

    public function runSync(Request $request, string $sync, BackupExportService $exports)
    {
        $syncs = $this->syncs($request);
        $index = $this->syncIndex($syncs, $sync);
        abort_if($index === null, 404);

        $run = $exports->run($request->user(), $syncs[$index], $this->googleConnection($request));
        $syncs[$index]['last_run_at'] = now()->toISOString();
        $syncs[$index]['next_run_at'] = $exports->nextRunAt($syncs[$index]['frequency'] ?? 'daily')->toISOString();
        $this->saveSyncs($request, $syncs);

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

    private function groups(Request $request): array
    {
        return collect(Configuration::GROUPS)
            ->mapWithKeys(function (string $group) use ($request) {
                $defaults = $this->defaultGroupSettings($group);
                $stored = Configuration::getValue($request->user(), $group, 'settings', []);

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

    private function googleConnection(Request $request): ?array
    {
        $connection = Configuration::getValue($request->user(), 'sync', 'google_sheets');

        return is_array($connection) ? $connection : null;
    }

    private function syncs(Request $request): array
    {
        $syncs = Configuration::getValue($request->user(), 'sync', 'backup_schedules', []);

        return is_array($syncs) ? array_values($syncs) : [];
    }

    private function saveSyncs(Request $request, array $syncs): void
    {
        Configuration::setValue($request->user(), 'sync', 'backup_schedules', array_values($syncs));
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

    private function iso(mixed $value): ?string
    {
        return $value instanceof Carbon ? $value->toISOString() : null;
    }
}
