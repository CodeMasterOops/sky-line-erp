<?php

namespace App\Http\Controllers\Api\Admin\Gym;

use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Services\Gym\GymReportService;

class GymReportController extends Controller
{
    public function __construct(private readonly GymReportService $reports) {}

    #[Permissions('gym_report', group: 'gym_report', desc: 'Gym Reports')]
    public function membershipSummary(Request $request)
    {
        return response()->json([
            'data' => $this->reports->membershipSummary($request->query('from'), $request->query('to')),
        ]);
    }

    #[Permissions('gym_report', group: 'gym_report', desc: 'Gym Reports')]
    public function renewals(Request $request)
    {
        return response()->json([
            'data' => $this->reports->renewals($request->query('from'), $request->query('to')),
        ]);
    }

    #[Permissions('gym_report', group: 'gym_report', desc: 'Gym Reports')]
    public function revenueByPlan(Request $request)
    {
        return response()->json([
            'data' => $this->reports->revenueByPlan($request->query('from'), $request->query('to')),
        ]);
    }

    #[Permissions('gym_report', group: 'gym_report', desc: 'Gym Reports')]
    public function attendance(Request $request)
    {
        return response()->json([
            'data' => $this->reports->attendance($request->query('from'), $request->query('to')),
        ]);
    }
}
