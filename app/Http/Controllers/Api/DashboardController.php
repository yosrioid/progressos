<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardData;
use App\Services\MilestoneProgressSync;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardData $dashboard, MilestoneProgressSync $milestones)
    {
        return response()->json($dashboard->for($request->user(), $milestones));
    }
}
