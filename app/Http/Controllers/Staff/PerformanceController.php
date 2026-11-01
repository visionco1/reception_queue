<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Controller;
use App\Services\PerformanceService;
use Illuminate\Http\Request;

/** تقرير الموظف: أداؤه مقابل متوسط الفريق وترتيبه، واتجاهه مع الوقت */
class PerformanceController extends Controller
{
    public function index(Request $request, PerformanceService $performance)
    {
        if ($request->user()->isManager()) {
            return redirect()->route('admin.reports', $request->query());
        }

        [$from, $to, $preset, $group] = ReportController::period($request);
        $summary = $performance->summary($from, $to);
        $user = $request->user();

        return view('staff.performance', [
            'user' => $user,
            'row' => collect($summary['employees'])->firstWhere('id', $user->id),
            'team' => $summary['team'],
            'count' => count($summary['employees']),
            'trend' => $performance->trend($user, $from, $to, $group),
            'from' => $from,
            'to' => $to,
            'preset' => $preset,
        ]);
    }
}
