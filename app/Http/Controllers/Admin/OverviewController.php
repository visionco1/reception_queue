<?php

namespace App\Http\Controllers\Admin;

use App\Enums\VisitStatus;
use App\Http\Controllers\Controller;
use App\Models\DisplayCall;
use App\Models\Visit;
use App\Models\WhatsappLog;
use App\Services\QueueService;
use App\Services\ReportService;

class OverviewController extends Controller
{
    public function index(ReportService $reports, QueueService $queue)
    {
        $today = $reports->build(today(), today());

        return view('admin.overview', [
            'today' => $today,
            'waiting' => $queue->waitingQuery()->with('customer', 'ticket')->get(),
            'active' => Visit::query()->whereIn('status', VisitStatus::active())->with('employee', 'ticket', 'desk', 'customer')->get(),
            'calls' => DisplayCall::query()->where('created_at', '>=', today())->latest('id')->limit(8)->get(),
            'waFailed' => WhatsappLog::query()->where('status', 'failed')->where('created_at', '>=', today()->subDays(7))->count(),
        ]);
    }
}
