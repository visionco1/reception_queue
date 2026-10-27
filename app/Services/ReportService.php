<?php

namespace App\Services;

use App\Enums\Channel;
use App\Enums\CustomerType;
use App\Enums\VisitStatus;
use App\Models\Bank;
use App\Models\BankAgent;
use App\Models\ClientType;
use App\Models\Customer;
use App\Models\User;
use App\Models\Visit;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * التقارير الأساسية. تُحسب في PHP على حقول مختصرة من الزيارات حتى تعمل
 * بنفس الشكل على MySQL و SQLite دون دوال تاريخ خاصة بكل محرك.
 */
class ReportService
{
    public function build(CarbonInterface $from, CarbonInterface $to, string $group = 'day'): array
    {
        $from = CarbonImmutable::parse($from)->startOfDay();
        $to = CarbonImmutable::parse($to)->endOfDay();

        /** @var Collection<int, Visit> $visits */
        $visits = Visit::query()
            ->whereBetween('created_at', [$from, $to])
            ->get(['id', 'customer_id', 'channel', 'customer_type', 'client_type_id', 'bank_id', 'agent_id', 'deal_id', 'status', 'employee_id',
                'wait_seconds', 'service_seconds', 'is_returning', 'created_at']);

        $inPerson = $visits->where('channel', Channel::InPerson);
        $completed = $visits->where('status', VisitStatus::Completed);

        $waits = $inPerson->whereNotNull('wait_seconds')->pluck('wait_seconds');
        $services = $completed->where('channel', Channel::InPerson)->whereNotNull('service_seconds')->pluck('service_seconds');

        return [
            'from' => $from,
            'to' => $to,
            'group' => $group,
            'totals' => [
                'visits' => $visits->count(),
                'customers' => $visits->pluck('customer_id')->unique()->count(),
                'in_person' => $inPerson->count(),
                'remote' => $visits->where('channel', Channel::Remote)->count(),
                'buyers' => $visits->where('customer_type', CustomerType::Buyer)->count(),
                'sellers' => $visits->where('customer_type', CustomerType::Seller)->count(),
                'others' => $visits->where('customer_type', CustomerType::Other)->count(),
                'deals' => $visits->pluck('deal_id')->filter()->unique()->count(),
                'completed' => $completed->count(),
                'no_show' => $visits->where('status', VisitStatus::NoShow)->count(),
                'cancelled' => $visits->where('status', VisitStatus::Cancelled)->count(),
                'avg_wait' => $waits->isNotEmpty() ? (int) round($waits->avg()) : null,
                'max_wait' => $waits->isNotEmpty() ? (int) $waits->max() : null,
                'avg_service' => $services->isNotEmpty() ? (int) round($services->avg()) : null,
            ],
            'series' => $this->series($visits, $from, $to, $group),
            'banks' => $this->banks($visits),
            'agents' => $this->agents($visits),
            'other_types' => $this->otherTypes($visits),
            'employees' => $this->employees($visits),
            'hours' => $this->hours($inPerson),
            'repeat' => $this->repeat($visits, $from, $to),
        ];
    }

    /** عدد العملاء يوميًا / أسبوعيًا / شهريًا مع فصل الحضوري وعن بعد */
    private function series(Collection $visits, CarbonImmutable $from, CarbonImmutable $to, string $group): array
    {
        $keyOf = fn (CarbonInterface $d) => match ($group) {
            'week' => $d->startOfWeek(CarbonInterface::SUNDAY)->toDateString(),
            'month' => $d->format('Y-m'),
            default => $d->toDateString(),
        };

        $buckets = [];
        $cursor = $from;
        while ($cursor <= $to) {
            $key = $keyOf($cursor);
            $buckets[$key] ??= [
                'key' => $key,
                'label' => match ($group) {
                    'week' => __('أسبوع :date', ['date' => $cursor->startOfWeek(CarbonInterface::SUNDAY)->translatedFormat('j M')]),
                    'month' => $cursor->translatedFormat('F Y'),
                    default => $cursor->translatedFormat('D j M'),
                },
                'short' => match ($group) {
                    'week' => $cursor->startOfWeek(CarbonInterface::SUNDAY)->format('j/n'),
                    'month' => $cursor->translatedFormat('M'),
                    default => $cursor->format('j/n'),
                },
                'in_person' => 0,
                'remote' => 0,
                'total' => 0,
            ];
            $cursor = match ($group) {
                'week' => $cursor->addWeek()->startOfWeek(CarbonInterface::SUNDAY),
                'month' => $cursor->addMonthNoOverflow()->startOfMonth(),
                default => $cursor->addDay(),
            };
        }

        foreach ($visits as $v) {
            $key = $keyOf(CarbonImmutable::parse($v->created_at));
            if (! isset($buckets[$key])) {
                continue;
            }
            $buckets[$key][$v->channel === Channel::InPerson ? 'in_person' : 'remote']++;
            $buckets[$key]['total']++;
        }

        return array_values($buckets);
    }

    private function banks(Collection $visits): array
    {
        $names = Bank::query()->pluck('name', 'id');

        return $visits->groupBy(fn ($v) => $v->bank_id ?? 0)
            ->map(fn ($g, $id) => [
                'name' => $id ? ($names[$id] ?? __('بنك محذوف')) : __('بدون بنك'),
                'count' => $g->count(),
                'buyers' => $g->where('customer_type', CustomerType::Buyer)->count(),
                'sellers' => $g->where('customer_type', CustomerType::Seller)->count(),
            ])
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    /** حسب وكيل البنك: البائعون والمشترون والصفقات المربوطة */
    private function agents(Collection $visits): array
    {
        $agents = BankAgent::query()->with('bank:id,name')->get()->keyBy('id');

        return $visits->whereNotNull('agent_id')->groupBy('agent_id')
            ->map(fn ($g, $id) => [
                'name' => $agents[$id]?->name ?? __('وكيل محذوف'),
                'bank' => $agents[$id]?->bank?->name,
                'sellers' => $g->where('customer_type', CustomerType::Seller)->count(),
                'buyers' => $g->where('customer_type', CustomerType::Buyer)->count(),
                'deals' => $g->pluck('deal_id')->filter()->unique()->count(),
                'count' => $g->count(),
            ])
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    private function otherTypes(Collection $visits): array
    {
        $names = ClientType::query()->pluck('name', 'id');

        return $visits->where('customer_type', CustomerType::Other)->groupBy(fn ($v) => $v->client_type_id ?? 0)
            ->map(fn ($g, $id) => ['name' => $id ? ($names[$id] ?? __('نوع محذوف')) : __('غير محدد'), 'count' => $g->count()])
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    private function employees(Collection $visits): array
    {
        $users = User::query()->pluck('name', 'id');

        return $visits->whereNotNull('employee_id')->groupBy('employee_id')
            ->map(function ($g, $id) use ($users) {
                $served = $g->where('channel', Channel::InPerson)->where('status', VisitStatus::Completed);
                $service = $served->whereNotNull('service_seconds')->pluck('service_seconds');

                return [
                    'name' => $users[$id] ?? __('موظف محذوف'),
                    'served' => $served->count(),
                    'remote' => $g->where('channel', Channel::Remote)->count(),
                    'no_show' => $g->where('status', VisitStatus::NoShow)->count(),
                    'total' => $served->count() + $g->where('channel', Channel::Remote)->count(),
                    'avg_service' => $service->isNotEmpty() ? (int) round($service->avg()) : null,
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    /** أوقات الذروة: عدد تسجيلات الحضوري حسب ساعة اليوم */
    private function hours(Collection $inPerson): array
    {
        $counts = array_fill(0, 24, 0);
        foreach ($inPerson as $v) {
            $counts[(int) CarbonImmutable::parse($v->created_at)->format('G')]++;
        }

        $nonZero = array_keys(array_filter($counts));
        $start = $nonZero ? min(min($nonZero), 8) : 8;
        $end = $nonZero ? max(max($nonZero), 17) : 17;

        $rows = [];
        for ($h = $start; $h <= $end; $h++) {
            $rows[] = ['hour' => $h, 'label' => $this->hourLabel($h), 'count' => $counts[$h]];
        }

        return $rows;
    }

    private function hourLabel(int $h): string
    {
        $suffix = $h < 12 ? __('ص') : __('م');
        $h12 = $h % 12 === 0 ? 12 : $h % 12;

        return $h12.' '.$suffix;
    }

    /** العملاء المتكررون: من لهم أكثر من زيارة في سجلهم الكامل */
    private function repeat(Collection $visits, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $ids = $visits->pluck('customer_id')->unique();
        $repeatCustomers = $ids->isEmpty() ? 0 : Customer::query()
            ->whereIn('id', Visit::query()->select('customer_id')->whereBetween('created_at', [$from, $to]))
            ->where('visits_count', '>', 1)
            ->count();

        return [
            'customers' => $repeatCustomers,
            'share' => $ids->count() ? round($repeatCustomers / $ids->count() * 100) : 0,
            'returning_visits' => $visits->where('is_returning', true)->count(),
        ];
    }
}
