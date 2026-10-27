<?php

namespace App\Support;

use App\Enums\Channel;
use App\Enums\CustomerType;
use App\Enums\VisitStatus;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** مرشحات قائمة الزيارات — مشتركة بين العرض والتصدير */
class VisitFilters
{
    public static function values(Request $request): array
    {
        return [
            'from' => $request->query('from', today()->subDays(6)->toDateString()),
            'to' => $request->query('to', today()->toDateString()),
            'channel' => $request->query('channel'),
            'type' => $request->query('type'),
            'status' => $request->query('status'),
            'bank' => $request->query('bank'),
            'agent' => $request->query('agent'),
            'employee' => $request->query('employee'),
            'q' => trim((string) $request->query('q', '')),
        ];
    }

    public static function query(array $f): Builder
    {
        $query = Visit::query()->with('customer', 'ticket', 'bank', 'agent', 'clientType', 'employee', 'desk', 'deal.visits.customer', 'deal.visits.ticket', 'deal.visits.clientType');

        try {
            $from = Carbon::parse($f['from'])->startOfDay();
            $to = Carbon::parse($f['to'])->endOfDay();
            $query->whereBetween('visits.created_at', [$from, $to]);
        } catch (\Throwable) {
            // تاريخ غير صالح: نتجاهل المرشح
        }

        if ($f['channel'] && Channel::tryFrom($f['channel'])) {
            $query->where('channel', $f['channel']);
        }
        if ($f['type'] && CustomerType::tryFrom($f['type'])) {
            $query->where('customer_type', $f['type']);
        }
        if ($f['status'] && VisitStatus::tryFrom($f['status'])) {
            $query->where('status', $f['status']);
        }
        if ($f['bank']) {
            $query->where('bank_id', (int) $f['bank']);
        }
        if ($f['agent'] ?? null) {
            $query->where('agent_id', (int) $f['agent']);
        }
        if ($f['employee']) {
            $query->where('employee_id', (int) $f['employee']);
        }
        if ($f['q'] !== '') {
            $q = $f['q'];
            $digits = preg_replace('/\D+/', '', Phone::toAsciiDigits($q));
            $query->where(function ($w) use ($q, $digits) {
                $w->whereHas('customer', function ($c) use ($q, $digits) {
                    $c->where('name', 'like', "%{$q}%");
                    if (strlen($digits) >= 4) {
                        $c->orWhere('mobile', 'like', '%'.ltrim($digits, '0').'%');
                    }
                })->orWhereHas('ticket', fn ($t) => $t->where('ticket_no', strtoupper($q)));
            });
        }

        return $query->latest('visits.id');
    }
}
