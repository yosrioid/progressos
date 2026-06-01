<?php

namespace App\Services;

use App\Models\ReportSnapshot;
use App\Models\User;
use Carbon\CarbonImmutable;

class ReportSnapshotService
{
    public function __construct(private readonly ReportBuilder $reports) {}

    public function store(User $user, string $period, ?string $date = null): ReportSnapshot
    {
        $report = $this->reports->build($user, $period, $date);

        return ReportSnapshot::updateOrCreate(
            ['user_id' => $user->id, 'period_type' => $period, 'period_start' => CarbonImmutable::parse($report['start'])],
            ['period_end' => CarbonImmutable::parse($report['end']), 'payload' => $report],
        );
    }
}
