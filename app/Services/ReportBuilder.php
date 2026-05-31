<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportBuilder
{
    public function build(User $user, string $period, ?string $anchor = null): array
    {
        $date = CarbonImmutable::parse($anchor ?: now($user->timezone));
        $start = $period === 'monthly' ? $date->startOfMonth() : $date->startOfWeek();
        $end = $period === 'monthly' ? $date->endOfMonth() : $date->endOfWeek();
        $previousStart = $period === 'monthly' ? $start->subMonth() : $start->subWeek();
        $previousEnd = $period === 'monthly' ? $end->subMonth() : $end->subWeek();

        $workLogs = $user->workLogs()->with('tags')->whereBetween('date', [$start, $end])->get();
        $previousWorkLogs = $user->workLogs()->whereBetween('date', [$previousStart, $previousEnd])->get();
        $learning = $user->learningEntries()->whereBetween('date', [$start, $end])->get();
        $previousLearning = $user->learningEntries()->whereBetween('date', [$previousStart, $previousEnd])->get();
        $progress = $user->dailyProgressEntries()->with('tags')->whereBetween('date', [$start, $end])->get();

        return [
            'period' => $period,
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'completed_work_logs' => $workLogs->where('status', 'done')->values(),
            'open_blockers' => $workLogs->where('status', 'blocked')->values(),
            'time_by_category' => $this->sumBy($workLogs, 'category', 'actual_duration'),
            'learning_totals' => [
                'minutes' => $learning->sum('duration_minutes'),
                'hours' => round($learning->sum('duration_minutes') / 60, 1),
                'by_category' => $this->sumBy($learning, 'category', 'duration_minutes'),
            ],
            'key_achievements' => $progress->flatMap(fn ($entry) => $entry->completed_items ?: [])->filter()->values()->take(12),
            'most_active_projects' => $workLogs->groupBy('project_name')->map->count()->sortDesc()->take(8),
            'notable_tags' => $workLogs->flatMap->tags->merge($progress->flatMap->tags)->pluck('name')->countBy()->sortDesc()->take(12),
            'trends' => [
                'completed_work_delta' => $workLogs->where('status', 'done')->count() - $previousWorkLogs->where('status', 'done')->count(),
                'learning_minutes_delta' => $learning->sum('duration_minutes') - $previousLearning->sum('duration_minutes'),
                'logged_minutes_delta' => $workLogs->sum('actual_duration') - $previousWorkLogs->sum('actual_duration'),
            ],
        ];
    }

    private function sumBy(Collection $records, string $groupBy, string $sum): Collection
    {
        return $records->groupBy($groupBy)->map(fn ($items) => $items->sum($sum))->sortDesc();
    }
}
