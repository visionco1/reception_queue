<?php

namespace App\Http\Controllers\Display;

use App\Enums\CustomerType;
use App\Enums\VisitStatus;
use App\Http\Controllers\Controller;
use App\Models\Desk;
use App\Models\DisplayCall;
use App\Models\Visit;
use App\Services\QueueService;
use App\Support\Settings;
use App\Support\WorkHours;

/** شاشة العرض في صالة الانتظار: الخمول، تنبيه النداء الجديد، والنشاط مع جدول النداءات */
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
        $limit = max(1, min(8, (int) setting('display_recent_count', 5)));

        $calls = DisplayCall::query()
            ->where('created_at', '>=', today())
            ->with('visit.customer')
            ->latest('id')
            ->limit(40)
            ->get();

        $current = $calls->first();
        // أطراف الصفقة الذين نودوا معًا لنفس المكتب يظهرون معًا
        $group = $current && $current->batch
            ? $calls->where('batch', $current->batch)->sortBy('id')->values()
            : collect($current ? [$current] : []);
        $groupTickets = $group->pluck('ticket_no')->all();

        // النداءات السابقة بدون تكرار الأرقام الحالية
        $recent = $calls
            ->reject(fn ($c) => in_array($c->ticket_no, $groupTickets, true))
            ->unique('ticket_no')
            ->take($limit)
            ->values();

        $waiting = $queue->waitingQuery()->get(['visits.customer_type']);

        return response()->json([
            'version' => $settings->version(),
            'server_time' => now()->toIso8601String(),
            'mode' => setting('display_mode') === 'offices' ? 'offices' : 'current',
            // خارج الدوام تبقى الشاشة على الخمول
            'open' => WorkHours::isOpen(),
            'alert_seconds' => max(3, (int) setting('display_alert_seconds', 10)),
            'idle_minutes' => max(1, (int) setting('display_idle_minutes', 10)),
            'sound' => (bool) setting('display_sound'),
            'volume' => max(0, min(100, (int) setting('display_volume', 80))),
            'voice' => (bool) setting('display_voice'),
            'ticker' => $this->ticker(),
            'current' => $current ? [
                'id' => $group->max('id'),
                'tickets' => $groupTickets,
                'desk' => $current->desk_label,
                'names' => $group->map(fn ($c) => $c->visit?->customer?->shortName())->filter()->values(),
                'recall' => $current->kind === 'recall',
                'at' => $current->created_at->toIso8601String(),
            ] : null,
            'recent' => $recent->map(fn (DisplayCall $c) => [
                'ticket_no' => $c->ticket_no,
                'desk' => $c->desk_label,
                'at' => $c->created_at->toIso8601String(),
            ]),
            'waiting' => [
                'total' => $waiting->count(),
                'buyer' => $waiting->where('customer_type', CustomerType::Buyer)->count(),
                'seller' => $waiting->where('customer_type', CustomerType::Seller)->count(),
                'minutes' => $queue->estimateMinutes($waiting->count()),
            ],
            'offices' => $this->offices(),
        ])->header('Cache-Control', 'no-store');
    }

    /** لوحة المكاتب: الرقم الحالي في كل مكتب */
    private function offices(): array
    {
        $active = Visit::query()
            ->whereIn('status', VisitStatus::active())
            ->whereNotNull('desk_id')
            ->with('ticket')
            ->orderBy('called_at')
            ->get()
            ->groupBy('desk_id');

        return Desk::query()->active()->ordered()->get()->map(fn (Desk $d) => [
            'number' => $d->number,
            'tickets' => $active->get($d->id, collect())->map(fn ($v) => $v->ticket?->ticket_no)->filter()->values(),
        ])->values()->all();
    }

    private function ticker(): array
    {
        return collect(preg_split('/\r\n|\r|\n/', (string) setting('ticker_messages', '')))
            ->map(fn ($l) => trim($l))
            ->filter()
            ->values()
            ->all();
    }
}
