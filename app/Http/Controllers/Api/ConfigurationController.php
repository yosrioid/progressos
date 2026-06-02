<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BackupConnection;
use App\Models\BackupRun;
use App\Models\BackupSync;
use App\Services\BackupExportService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class ConfigurationController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        return ApiResponse::ok([
            'configuration' => [
                'available_modules' => BackupSync::MODULES,
                'frequencies' => BackupSync::FREQUENCIES,
                'sections' => [
                    'google_oauth' => true,
                    'sync_data' => true,
                    'history' => true,
                ],
                'connection' => $this->connectionPayload(BackupConnection::ownedBy($user)->first()),
                'syncs' => BackupSync::ownedBy($user)->with('connection')->latest()->get()->map(fn (BackupSync $sync) => $this->syncPayload($sync)),
                'runs' => BackupRun::ownedBy($user)->with('sync')->latest()->limit(12)->get()->map(fn (BackupRun $run) => $this->runPayload($run)),
            ],
        ]);
    }

    public function updateConnection(Request $request)
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'spreadsheet_id' => ['nullable', 'string', 'max:255'],
            'credentials_json' => ['nullable', 'string'],
        ]);

        $credentials = null;
        if (! empty($data['credentials_json'])) {
            $credentials = json_decode($data['credentials_json'], true);
            if (! is_array($credentials)) {
                return ApiResponse::ok(['errors' => ['credentials_json' => ['Credential JSON is not valid.']]], 'Credential JSON is not valid.', 422);
            }
        }

        $connection = BackupConnection::firstOrNew(['user_id' => $request->user()->id, 'provider' => 'google_sheets']);
        $connection->fill([
            'name' => $data['name'] ?? 'Google Sheets',
            'spreadsheet_id' => $data['spreadsheet_id'] ?? $connection->spreadsheet_id,
            'status' => 'draft',
        ]);
        if ($credentials) {
            $connection->setAttribute('credentials', $credentials);
        }
        $connection->save();

        return ApiResponse::item('connection', $this->connectionPayload($connection), 200, 'Backup connection saved.');
    }

    public function verifyConnection(Request $request)
    {
        $connection = BackupConnection::ownedBy($request->user())->first();
        if (! $connection) {
            return ApiResponse::ok([], 'Add a connection before testing it.', 422);
        }

        $credentials = $connection->credentials ?: [];
        $valid = filled($connection->spreadsheet_id)
            && filled($credentials['client_email'] ?? null)
            && filled($credentials['private_key'] ?? null)
            && filled($credentials['project_id'] ?? null);

        $connection->update([
            'status' => $valid ? 'verified' : 'error',
            'last_verified_at' => $valid ? now() : null,
        ]);

        return ApiResponse::item(
            'connection',
            $this->connectionPayload($connection->fresh()),
            $valid ? 200 : 422,
            $valid ? 'Connection settings look valid.' : 'Spreadsheet ID, client_email, private_key, and project_id are required.'
        );
    }

    public function storeSync(Request $request, BackupExportService $exports)
    {
        $data = $this->validateSync($request);
        $connection = BackupConnection::ownedBy($request->user())->first();

        $sync = BackupSync::query()->create([
            ...$data,
            'user_id' => $request->user()->id,
            'backup_connection_id' => $connection?->id,
            'next_run_at' => $exports->nextRunAt($data['frequency']),
        ]);

        return ApiResponse::item('sync', $this->syncPayload($sync->fresh('connection')), 201, 'Backup sync created.');
    }

    public function updateSync(Request $request, BackupSync $sync, BackupExportService $exports)
    {
        abort_unless($sync->user_id === $request->user()->id, 403);
        $data = $this->validateSync($request);
        $sync->update([
            ...$data,
            'next_run_at' => $sync->next_run_at ?? $exports->nextRunAt($data['frequency']),
        ]);

        return ApiResponse::item('sync', $this->syncPayload($sync->fresh('connection')), 200, 'Backup sync updated.');
    }

    public function destroySync(Request $request, BackupSync $sync)
    {
        abort_unless($sync->user_id === $request->user()->id, 403);
        $sync->delete();

        return response()->noContent();
    }

    public function runSync(Request $request, BackupSync $sync, BackupExportService $exports)
    {
        abort_unless($sync->user_id === $request->user()->id, 403);
        $run = $exports->run($sync->load('user'));

        return ApiResponse::item('run', $this->runPayload($run), 201, $run->status === 'completed' ? 'Backup completed.' : 'Backup failed.');
    }

    private function validateSync(Request $request): array
    {
        return $request->validate([
            'module' => ['required', Rule::in(BackupSync::MODULES)],
            'frequency' => ['required', Rule::in(BackupSync::FREQUENCIES)],
            'destination_sheet_name' => ['required', 'string', 'max:80'],
            'enabled' => ['sometimes', 'boolean'],
            'filters' => ['nullable', 'array'],
        ]);
    }

    private function connectionPayload(?BackupConnection $connection): ?array
    {
        if (! $connection) {
            return null;
        }

        return [
            'id' => $connection->id,
            'provider' => $connection->provider,
            'name' => $connection->name,
            'spreadsheet_id' => $connection->spreadsheet_id,
            'status' => $connection->status,
            'has_credentials' => filled($connection->credentials),
            'service_account_email' => $connection->credentials['client_email'] ?? null,
            'last_verified_at' => $this->iso($connection->last_verified_at),
        ];
    }

    private function syncPayload(BackupSync $sync): array
    {
        return [
            'id' => $sync->id,
            'module' => $sync->module,
            'frequency' => $sync->frequency,
            'destination_sheet_name' => $sync->destination_sheet_name,
            'enabled' => $sync->enabled,
            'last_run_at' => $this->iso($sync->last_run_at),
            'next_run_at' => $this->iso($sync->next_run_at),
        ];
    }

    private function runPayload(BackupRun $run): array
    {
        return [
            'id' => $run->id,
            'sync_id' => $run->backup_sync_id,
            'module' => $run->sync instanceof BackupSync ? $run->sync->module : null,
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
