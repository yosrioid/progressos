<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\HabitRequest;
use App\Models\Habit;
use App\Models\HabitLog;
use App\Services\NotificationService;
use App\Support\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HabitController extends Controller
{
    public function index(Request $request)
    {
        $habits = $request->user()->habits()
            ->where('archived', false)
            ->orderBy('order')
            ->orderBy('name')
            ->get();

        $today = Carbon::today()->toDateString();
        $weekStart = Carbon::now()->startOfWeek()->toDateString();
        $weekEnd = Carbon::now()->endOfWeek()->toDateString();

        // Load logs for today + this week
        $recentLogs = $request->user()->habitLogs()
            ->whereIn('habit_id', $habits->pluck('id'))
            ->whereDate('date', '>=', $weekStart)
            ->orderByDesc('date')
            ->get()
            ->groupBy('habit_id');

        // Load heatmap data (last 90 days) for each habit
        $heatmapFrom = Carbon::now()->subDays(89)->toDateString();
        $heatmapLogs = $request->user()->habitLogs()
            ->whereIn('habit_id', $habits->pluck('id'))
            ->whereDate('date', '>=', $heatmapFrom)
            ->get(['habit_id', 'date'])
            ->groupBy('habit_id')
            ->map(fn ($logs) => $logs->pluck('date')->map(fn ($d) => $d->toDateString())->toArray());

        // Load all logs (for streak) in one query — sorted desc per habit
        $allLogsGrouped = $request->user()->habitLogs()
            ->whereIn('habit_id', $habits->pluck('id'))
            ->orderByDesc('date')
            ->get(['habit_id', 'date'])
            ->groupBy('habit_id')
            ->map(fn ($logs) => $logs->pluck('date')->map(fn ($d) => $d->toDateString())->toArray());

        $data = $habits->map(function (Habit $habit) use ($today, $recentLogs, $heatmapLogs, $allLogsGrouped) {
            $logs = $recentLogs[$habit->id] ?? collect();
            $todayLog = $logs->firstWhere(fn ($l) => $l->date->toDateString() === $today);
            $todayDone = $todayLog !== null;
            $weekDates = $logs->pluck('date')->map(fn ($d) => $d->toDateString())->toArray();
            $heatmap = $heatmapLogs[$habit->id] ?? [];
            $allDates = $allLogsGrouped[$habit->id] ?? [];

            // Compute streak (no extra query — allDates already loaded)
            $streak = 0;
            $check = $today;
            foreach ($allDates as $date) {
                if ($date === $check) {
                    $streak++;
                    $check = Carbon::parse($check)->subDay()->toDateString();
                } else {
                    break;
                }
            }

            return [
                'id' => $habit->id,
                'name' => $habit->name,
                'description' => $habit->description,
                'color' => $habit->color,
                'icon' => $habit->icon,
                'frequency' => $habit->frequency,
                'target_days' => $habit->target_days,
                'active' => $habit->active,
                'order' => $habit->order,
                'today_done' => $todayDone,
                'today_notes' => $todayLog?->notes,
                'streak' => $streak,
                'week_dates' => $weekDates,
                'heatmap' => $heatmap,
                'total_logs' => count($allDates),
            ];
        });

        return ApiResponse::ok(['habits' => $data, 'today' => $today]);
    }

    public function store(HabitRequest $request)
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;
        $habit = Habit::create($data);

        return ApiResponse::item('habit', $habit, 201, 'Habit created.');
    }

    public function update(HabitRequest $request, Habit $habit)
    {
        $this->authorize('update', $habit);
        $habit->update($request->validated());

        return ApiResponse::item('habit', $habit, 200, 'Habit updated.');
    }

    public function destroy(Habit $habit)
    {
        $this->authorize('delete', $habit);
        $habit->delete();

        return response()->noContent();
    }

    public function log(Request $request, Habit $habit)
    {
        $this->authorize('update', $habit);
        $data = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string'],
        ]);
        $date = $data['date'] ?? Carbon::today()->toDateString();

        $log = HabitLog::firstOrCreate(
            ['habit_id' => $habit->id, 'date' => $date],
            ['user_id' => $request->user()->id, 'notes' => $data['notes'] ?? null]
        );

        if ($log->wasRecentlyCreated) {
            $allDates = HabitLog::where('habit_id', $habit->id)
                ->orderByDesc('date')
                ->pluck('date')
                ->map(fn ($d) => $d->toDateString())
                ->toArray();

            $streak = 0;
            $check = Carbon::today()->toDateString();
            foreach ($allDates as $logDate) {
                if ($logDate === $check) {
                    $streak++;
                    $check = Carbon::parse($check)->subDay()->toDateString();
                } else {
                    break;
                }
            }

            if ($streak > 0) {
                app(NotificationService::class)->notifyHabitStreak($request->user(), $habit->id, $habit->name, $streak);
            }
        }

        return ApiResponse::ok(['logged' => true, 'date' => $date, 'log_id' => $log->id, 'streak' => $streak ?? 0]);
    }

    public function unlog(Request $request, Habit $habit)
    {
        $this->authorize('update', $habit);
        $date = $request->query('date', Carbon::today()->toDateString());

        HabitLog::where('habit_id', $habit->id)->whereDate('date', $date)->delete();

        return ApiResponse::ok(['unlogged' => true, 'date' => $date]);
    }

    public function reorder(Request $request)
    {
        $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']]);

        DB::transaction(function () use ($request) {
            foreach ($request->ids as $order => $id) {
                Habit::where('id', $id)->where('user_id', $request->user()->id)->update(['order' => $order]);
            }
        });

        return ApiResponse::ok(['reordered' => true]);
    }
}
