<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReportBuilder;
use App\Services\ReportSnapshotService;
use App\Support\ApiQuery;
use App\Support\ApiResponse;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function show(Request $request, ReportBuilder $builder, string $period)
    {
        $this->ensureValidPeriod($period);

        return ApiResponse::item('report', $builder->build($request->user(), $period, $request->query('date')));
    }

    public function snapshots(Request $request, string $period)
    {
        $this->ensureValidPeriod($period);

        return ApiResponse::collection('snapshots', $request->user()->reportSnapshots()
            ->where('period_type', $period)
            ->latest('period_start')
            ->take(24)
            ->get());
    }

    public function storeSnapshot(Request $request, ReportSnapshotService $snapshots, string $period)
    {
        $this->ensureValidPeriod($period);

        return ApiResponse::item('snapshot', $snapshots->store($request->user(), $period, $request->query('date'), $request->input('reflection')), 201, 'Report snapshot saved.');
    }

    public function exportPdf(Request $request, ReportBuilder $builder, string $period)
    {
        $this->ensureValidPeriod($period);
        $report = $builder->build($request->user(), $period, $request->query('date'));
        $user = $request->user();

        $pdf = Pdf::loadView('reports.pdf', compact('report', 'user', 'period'))
            ->setPaper('a4', 'portrait');

        $filename = "progressos-{$period}-{$report['start']}.pdf";

        return $pdf->download($filename);
    }

    public function export(Request $request, ReportBuilder $builder, string $period): StreamedResponse
    {
        $this->ensureValidPeriod($period);
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

    private function ensureValidPeriod(string $period): void
    {
        abort_unless(in_array($period, ['weekly', 'monthly'], true), 404, 'Unsupported report period.');
    }
}
