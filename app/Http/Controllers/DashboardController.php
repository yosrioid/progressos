<?php

namespace App\Http\Controllers;

use App\Services\DashboardData;
use App\Services\MilestoneProgressSync;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardData $dashboard, MilestoneProgressSync $milestones)
    {
        return Inertia::render('Dashboard', $dashboard->for($request->user(), $milestones));
    }
}
