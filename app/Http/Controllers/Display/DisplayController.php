<?php

namespace App\Http\Controllers\Display;

use App\Http\Controllers\Controller;
use App\Models\DisplayCall;
use App\Services\QueueService;
use App\Support\Settings;

/** شاشة الانتظار الرئيسية (تلفزيون الصالة) */
class DisplayController extends Controller
{
    public function show(string $token, Settings $settings)
    {
        return view('display.show', [
            'token' => $token,
            'version' => $settings->version(),
        ]);
    }

    public function feed(string $token, Settings $settings, QueueService $queue)
    {
        $limit = max(1, min(10, (int) setting('display_recent_count', 5)));
        $showEmployee = (bool) setting('display_show_employee');

        $calls = DisplayCall::query()
            ->where('created_at', '>=', today())
            ->latest('id')
            ->limit(40)
            ->get();

        $map = fn (DisplayCall $c) => [
            'id' => $c->id,
            'ticket_no' => $c->ticket_no,
            'desk' => $c->desk_label,
            'employee' => $showEmployee ? $c->employee_name : null,
            'recall' => $c->kind === 'recall',
            'at' => $c->created_at->toIso8601String(),
        ];

        $current = $calls->first();
        // أطراف الصفقة الذين نودوا معًا لنفس المكتب يظهرون معًا
        $group = $current && $current->batch
            ? $calls->where('batch', $current->batch)->sortBy('id')->values()
            : collect($current ? [$current] : []);
        $groupTickets = $group->pluck('ticket_no')->all();

        // آخر الأرقام المستدعاة بدون تكرار الأرقام الحالية
        $recent = $calls
            ->reject(fn ($c) => in_array($c->ticket_no, $groupTickets, true))
            ->unique('ticket_no')
            ->take($limit)
            ->values();

        return response()->json([
            'version' => $settings->version(),
            'current' => $current ? $map($current) + [
                'id' => $group->max('id'),
                'tickets' => $groupTickets,
            ] : null,
            'recent' => $recent->map($map),
            'waiting' => $queue->waitingQuery()->count(),
        ])->header('Cache-Control', 'no-store');
    }
}
