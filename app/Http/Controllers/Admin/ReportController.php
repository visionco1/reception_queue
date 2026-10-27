<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReportController extends Controller
{
    public function index(Request $request, ReportService $reports)
    {
        $preset = $request->query('preset');
        [$from, $to] = match ($preset) {
            'today' => [today(), today()],
            'week' => [today()->startOfWeek(Carbon::SUNDAY), today()],
            'month' => [today()->startOfMonth(), today()],
            'last_month' => [today()->subMonthNoOverflow()->startOfMonth(), today()->subMonthNoOverflow()->endOfMonth()],
            default => [
                $this->date($request->query('from'), today()->subDays(29)),
                $this->date($request->query('to'), today()),
            ],
        };

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        $group = in_array($request->query('group'), ['day', 'week', 'month'], true)
            ? $request->query('group')
            : ($from->diffInDays($to) > 62 ? 'month' : 'day');

        return view('admin.reports', [
            'report' => $reports->build($from, $to, $group),
            'preset' => $preset,
        ]);
    }

    private function date(?string $value, Carbon $fallback): Carbon
    {
        try {
            return $value ? Carbon::parse($value) : $fallback;
        } catch (\Throwable) {
            return $fallback;
        }
    }
}
