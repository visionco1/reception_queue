<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PerformanceService;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReportController extends Controller
{
    public function index(Request $request, ReportService $reports, PerformanceService $performance)
    {
        [$from, $to, $preset, $group] = $this->period($request);

        return view('admin.reports', [
            'report' => $reports->build($from, $to, $group),
            'performance' => $performance->summary($from, $to),
            'preset' => $preset,
        ]);
    }

    /** أداء موظف مع الوقت مقابل متوسط الفريق */
    public function employee(Request $request, User $user, PerformanceService $performance)
    {
        [$from, $to, $preset, $group] = $this->period($request);
        $summary = $performance->summary($from, $to);

        return view('admin.employee-report', [
            'user' => $user,
            'row' => collect($summary['employees'])->firstWhere('id', $user->id),
            'team' => $summary['team'],
            'count' => count($summary['employees']),
            'trend' => $performance->trend($user, $from, $to, $group),
            'from' => $from,
            'to' => $to,
            'preset' => $preset,
            'employees' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** @return array{0: Carbon, 1: Carbon, 2: ?string, 3: string} */
    public static function period(Request $request): array
    {
        $preset = $request->query('preset');
        [$from, $to] = match ($preset) {
            'today' => [today(), today()],
            'week' => [today()->startOfWeek(Carbon::SUNDAY), today()],
            'month' => [today()->startOfMonth(), today()],
            'last_month' => [today()->subMonthNoOverflow()->startOfMonth(), today()->subMonthNoOverflow()->endOfMonth()],
            default => [
                self::date($request->query('from'), today()->subDays(29)),
                self::date($request->query('to'), today()),
            ],
        };

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        $group = in_array($request->query('group'), ['day', 'week', 'month'], true)
            ? $request->query('group')
            : ($from->diffInDays($to) > 62 ? 'month' : 'day');

        return [$from, $to, $preset, $group];
    }

    private static function date(?string $value, Carbon $fallback): Carbon
    {
        try {
            return $value ? Carbon::parse($value) : $fallback;
        } catch (\Throwable) {
            return $fallback;
        }
    }
}
