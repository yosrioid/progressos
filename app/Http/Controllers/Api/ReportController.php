<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReportBuilder;
use App\Support\ApiQuery;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function show(Request $request, ReportBuilder $builder, string $period)
    {
        abort_unless(in_array($period, ['weekly', 'monthly'], true), 404);

        return ApiResponse::item('report', $builder->build($request->user(), $period, $request->query('date')));
    }

    public function export(Request $request, ReportBuilder $builder, string $period): StreamedResponse
    {
        abort_unless(in_array($period, ['weekly', 'monthly'], true), 404);
        $report = $builder->build($request->user(), $period, $request->query('date'));

        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');
            $write = fn (array $row) => fputcsv($out, array_map([ApiQuery::class, 'csvSafe'], $row));
            $write(['metric', 'label', 'value']);
            $write(['period', '', $report['start'].' to '.$report['end']]);
            $write(['completed_work_logs', '', count($report['completed_work_logs'])]);
            $write(['open_blockers', '', count($report['open_blockers'])]);
            $write(['learning_minutes', '', $report['learning_totals']['minutes']]);
            foreach ($report['time_by_category'] as $category => $minutes) {
                $write(['work_minutes', $category, $minutes]);
            }
            fclose($out);
        }, "progressos-{$period}-{$report['start']}.csv", ['Content-Type' => 'text/csv']);
    }
}
