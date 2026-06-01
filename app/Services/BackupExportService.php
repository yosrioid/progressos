<?php

namespace App\Services;

use App\Models\BackupRun;
use App\Models\BackupSync;
use App\Models\DailyProgressEntry;
use App\Models\LearningEntry;
use App\Models\Milestone;
use App\Models\ReportSnapshot;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkLog;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Throwable;

class BackupExportService
{
    public function __construct(private readonly GoogleSheetsBackupService $sheets) {}

    public function run(BackupSync $sync): BackupRun
    {
        $run = BackupRun::query()->create([
            'backup_sync_id' => $sync->id,
            'user_id' => $sync->user_id,
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $rows = $this->rowsFor($sync->user, $sync->module);
            $path = $this->writeCsv($sync, $rows);
            $connection = $sync->connection;
            if (! $connection) {
                throw new \InvalidArgumentException('Backup connection is required before syncing to Google Sheets.');
            }
            $destination = $this->sheets->append($connection, $sync->destination_sheet_name, $rows);
            $sync->update([
                'last_run_at' => now(),
                'next_run_at' => $this->nextRunAt($sync->frequency),
            ]);
            $run->update([
                'status' => 'completed',
                'finished_at' => now(),
                'rows_exported' => max(count($rows) - 1, 0),
                'file_path' => "{$destination} | local CSV: {$path}",
            ]);
        } catch (Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error_message' => $exception->getMessage(),
            ]);
        }

        $freshRun = $run->fresh();

        return $freshRun instanceof BackupRun ? $freshRun : $run;
    }

    public function runDue(): int
    {
        $count = 0;
        BackupSync::query()
            ->where('enabled', true)
            ->where(fn (Builder $query) => $query->whereNull('next_run_at')->orWhere('next_run_at', '<=', now()))
            ->with('user')
            ->chunkById(50, function ($syncs) use (&$count) {
                foreach ($syncs as $sync) {
                    $this->run($sync);
                    $count++;
                }
            });

        return $count;
    }

    public function nextRunAt(string $frequency): CarbonImmutable
    {
        return match ($frequency) {
            'weekly' => CarbonImmutable::now()->addWeek()->startOfDay(),
            'monthly' => CarbonImmutable::now()->addMonth()->startOfDay(),
            default => CarbonImmutable::now()->addDay()->startOfDay(),
        };
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function rowsFor(User $user, string $module): array
    {
        return match ($module) {
            'daily_progress' => $this->dailyProgressRows($user),
            'work_logs' => $this->workLogRows($user),
            'tasks' => $this->taskRows($user),
            'learning' => $this->learningRows($user),
            'milestones' => $this->milestoneRows($user),
            'reports' => $this->reportRows($user),
            default => [['id']],
        };
    }

    private function writeCsv(BackupSync $sync, array $rows): string
    {
        $directory = "backups/user-{$sync->user_id}";
        $path = $directory.'/'.now()->format('Ymd-His')."-{$sync->module}.csv";
        $stream = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($stream, array_map(fn ($value) => is_array($value) ? json_encode($value) : $value, $row));
        }
        rewind($stream);
        Storage::put($path, stream_get_contents($stream));
        fclose($stream);

        return $path;
    }

    private function dailyProgressRows(User $user): array
    {
        $rows = [['id', 'date', 'title', 'in_progress', 'todo', 'blockers', 'notes', 'completed_items', 'mood', 'archived']];
        foreach (DailyProgressEntry::ownedBy($user)->latest('date')->limit(1000)->get() as $entry) {
            $rows[] = [$entry->id, $this->dateString($entry->date), $entry->title, $entry->in_progress, $entry->todo, $entry->blockers, $entry->notes, $entry->completed_items, $entry->mood, $entry->archived ? 'yes' : 'no'];
        }

        return $rows;
    }

    private function workLogRows(User $user): array
    {
        $rows = [['id', 'date', 'project_name', 'ticket_code', 'title', 'category', 'status', 'priority', 'actual_duration']];
        foreach (WorkLog::ownedBy($user)->latest('date')->limit(1000)->get() as $log) {
            $rows[] = [$log->id, $this->dateString($log->date), $log->project_name, $log->ticket_code, $log->title, $log->category, $log->status, $log->priority, $log->actual_duration];
        }

        return $rows;
    }

    private function taskRows(User $user): array
    {
        $rows = [['id', 'title', 'status', 'priority', 'due_date', 'completed_at', 'notes']];
        foreach (Task::ownedBy($user)->latest()->limit(1000)->get() as $task) {
            $rows[] = [$task->id, $task->title, $task->status, $task->priority, $this->dateString($task->due_date), $this->dateTimeString($task->completed_at), $task->notes];
        }

        return $rows;
    }

    private function learningRows(User $user): array
    {
        $rows = [['id', 'date', 'topic', 'category', 'source_type', 'duration_minutes', 'takeaway', 'next_action', 'rating']];
        foreach (LearningEntry::ownedBy($user)->latest('date')->limit(1000)->get() as $entry) {
            $rows[] = [$entry->id, $this->dateString($entry->date), $entry->topic, $entry->category, $entry->source_type, $entry->duration_minutes, $entry->takeaway, $entry->next_action, $entry->rating];
        }

        return $rows;
    }

    private function milestoneRows(User $user): array
    {
        $rows = [['id', 'title', 'category', 'target_type', 'target_value', 'current_value', 'start_date', 'end_date', 'status']];
        foreach (Milestone::ownedBy($user)->latest()->limit(1000)->get() as $milestone) {
            $rows[] = [$milestone->id, $milestone->title, $milestone->category, $milestone->target_type, $milestone->target_value, $milestone->current_value, $this->dateString($milestone->start_date), $this->dateString($milestone->end_date), $milestone->status];
        }

        return $rows;
    }

    private function reportRows(User $user): array
    {
        $rows = [['id', 'period_type', 'period_start', 'period_end', 'created_at']];
        foreach (ReportSnapshot::ownedBy($user)->latest('period_start')->limit(1000)->get() as $snapshot) {
            $rows[] = [$snapshot->id, $snapshot->period_type, $this->dateString($snapshot->period_start), $this->dateString($snapshot->period_end), $this->dateTimeString($snapshot->created_at)];
        }

        return $rows;
    }

    private function dateString(mixed $value): ?string
    {
        return $value instanceof Carbon ? $value->toDateString() : null;
    }

    private function dateTimeString(mixed $value): ?string
    {
        return $value instanceof Carbon ? $value->toDateTimeString() : null;
    }
}
