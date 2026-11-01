<?php

namespace App\Services;

use App\Enums\Outcome;
use App\Enums\VisitStatus;
use App\Models\ServiceSession;
use App\Models\User;
use App\Models\Visit;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * أداء الموظفين: عدد من خدمهم، متوسط مدة الخدمة، تأخر النداء (متوسط انتظار
 * عملائه قبل النداء)، وقت الخمول بين العملاء، ونتائج الصفقات؛ مع متوسط
 * الفريق والترتيب، واتجاه أداء الموظف مع الوقت.
 */
class PerformanceService
{
    /**
     * @return array{team: array, employees: list<array>, outcomes: array}
     */
    public function summary(CarbonInterface $from, CarbonInterface $to): array
    {
        [$from, $to] = $this->range($from, $to);

        $visits = $this->visits($from, $to);
        $idle = $this->idleByEmployee($from, $to);
        $names = User::query()->pluck('name', 'id');

        $rows = $visits->whereNotNull('employee_id')->groupBy('employee_id')
            ->map(fn (Collection $g, $id) => $this->metrics($g, $idle[$id] ?? null) + [
                'id' => (int) $id,
                'name' => $names[$id] ?? __('موظف محذوف'),
            ])
            ->values();

        $team = $this->teamAverage($rows);

        $ranked = $rows
            ->sortBy([['served', 'desc'], ['avg_service', 'asc']])
            ->values()
            ->map(function (array $r, int $i) use ($team) {
                $r['rank'] = $i + 1;
                $r['vs_team'] = [
                    'served' => $this->delta($r['served'], $team['served']),
                    'avg_service' => $this->delta($r['avg_service'], $team['avg_service']),
                    'avg_call_wait' => $this->delta($r['avg_call_wait'], $team['avg_call_wait']),
                    'avg_idle' => $this->delta($r['avg_idle'], $team['avg_idle']),
                ];

                return $r;
            });

        return [
            'team' => $team,
            'employees' => $ranked->all(),
            'outcomes' => $this->outcomes($visits),
        ];
    }

    /** أداء موظف واحد مقابل متوسط الفريق لكل يوم (أو أسبوع/شهر) */
    public function trend(User $user, CarbonInterface $from, CarbonInterface $to, string $group = 'day'): array
    {
        [$from, $to] = $this->range($from, $to);
        $visits = $this->visits($from, $to)->whereNotNull('employee_id');
        $idleDaily = $this->idleDaily($from, $to);

        $keyOf = fn ($d) => match ($group) {
            'week' => CarbonImmutable::parse($d)->startOfWeek(CarbonInterface::SUNDAY)->toDateString(),
            'month' => CarbonImmutable::parse($d)->format('Y-m'),
            default => CarbonImmutable::parse($d)->toDateString(),
        };

        $points = [];
        for ($c = $from; $c <= $to; $c = match ($group) {
            'week' => $c->addWeek()->startOfWeek(CarbonInterface::SUNDAY),
            'month' => $c->addMonthNoOverflow()->startOfMonth(),
            default => $c->addDay(),
        }) {
            $k = $keyOf($c);
            $points[$k] ??= ['key' => $k, 'label' => match ($group) {
                'week' => $c->startOfWeek(CarbonInterface::SUNDAY)->format('j/n'),
                'month' => $c->translatedFormat('M Y'),
                default => $c->format('j/n'),
            }];
        }

        $byBucket = $visits->groupBy(fn ($v) => $keyOf($v->called_at ?? $v->created_at));

        foreach ($points as $k => &$p) {
            $bucket = $byBucket->get($k, collect());
            $mine = $bucket->where('employee_id', $user->id);
            $servedBy = $bucket->where('status', VisitStatus::Completed)->groupBy('employee_id');

            // يوم بلا أي خدمة (عطلة) يُترك فارغًا بدل صفر حتى لا يهبط الخط
            $p['served'] = $servedBy->isEmpty() ? null : $mine->where('status', VisitStatus::Completed)->count();
            $p['team_served'] = $servedBy->isEmpty() ? null : round($servedBy->map->count()->avg(), 1);
            $p['avg_service'] = $this->avgMinutes($mine->where('status', VisitStatus::Completed)->pluck('service_seconds'));
            $p['team_avg_service'] = $this->avgMinutes($bucket->where('status', VisitStatus::Completed)->pluck('service_seconds'));
            $idle = collect($idleDaily)->filter(fn ($row) => $keyOf($row['date']) === $k);
            $p['avg_idle'] = $this->avgMinutes($idle->where('employee_id', $user->id)->flatMap(fn ($r) => $r['gaps']));
            $p['team_avg_idle'] = $this->avgMinutes($idle->flatMap(fn ($r) => $r['gaps']));
        }
        unset($p);

        return array_values($points);
    }

    private function metrics(Collection $g, ?array $idle): array
    {
        $completed = $g->where('status', VisitStatus::Completed);

        return [
            'served' => $completed->count(),
            'avg_service' => $this->avg($completed->pluck('service_seconds')),
            'avg_call_wait' => $this->avg($g->pluck('wait_seconds')),
            'avg_idle' => $idle && $idle['gaps'] ? (int) round(array_sum($idle['gaps']) / count($idle['gaps'])) : null,
            'idle_total' => $idle ? (int) array_sum($idle['gaps']) : 0,
            'no_show' => $g->where('status', VisitStatus::NoShow)->count(),
            'missing_docs' => $completed->where('outcome', Outcome::MissingDocs)->count(),
            'cancelled' => $completed->where('outcome', Outcome::Cancelled)->count(),
        ];
    }

    private function teamAverage(Collection $rows): array
    {
        $avg = fn (string $key) => ($vals = $rows->pluck($key)->filter(fn ($v) => $v !== null))->isNotEmpty()
            ? (int) round($vals->avg()) : null;

        return [
            'employees' => $rows->count(),
            'served' => $rows->isNotEmpty() ? round($rows->avg('served'), 1) : 0,
            'avg_service' => $avg('avg_service'),
            'avg_call_wait' => $avg('avg_call_wait'),
            'avg_idle' => $avg('avg_idle'),
        ];
    }

    /**
     * نتائج الصفقات: الصفقة المربوطة (بائع + مشتري) تُعد مرة واحدة، والعميل غير المربوط صفقة بمفرده.
     */
    private function outcomes(Collection $visits): array
    {
        $transactions = $visits->where('status', VisitStatus::Completed)
            ->groupBy(fn ($v) => $v->deal_id ? 'd'.$v->deal_id : 'v'.$v->id)
            ->map(fn (Collection $g) => $g->first()->outcome ?? Outcome::Completed);

        $counts = [];
        foreach (Outcome::cases() as $o) {
            $counts[$o->value] = ['label' => $o->label(), 'count' => $transactions->filter(fn ($x) => $x === $o)->count()];
        }

        return ['total' => $transactions->count(), 'items' => $counts];
    }

    /** فترات الخمول لكل موظف: من إنهاء عميل إلى أخذ التالي في نفس اليوم */
    private function idleByEmployee(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $out = [];
        foreach ($this->idleDaily($from, $to) as $row) {
            $out[$row['employee_id']]['gaps'] = array_merge($out[$row['employee_id']]['gaps'] ?? [], $row['gaps']);
        }

        return $out;
    }

    /** @return list<array{employee_id:int, date:string, gaps:list<int>}> */
    private function idleDaily(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $sessions = ServiceSession::query()
            ->whereBetween('started_at', [$from, $to])
            ->whereNotNull('ended_at')
            ->orderBy('employee_id')->orderBy('started_at')
            ->get(['employee_id', 'started_at', 'ended_at']);

        $rows = [];
        foreach ($sessions->groupBy(fn ($s) => $s->employee_id.'|'.$s->started_at->toDateString()) as $key => $day) {
            [$employee, $date] = explode('|', $key);

            // دمج الجلسات المتداخلة (أطراف صفقة واحدة يُخدمون معًا)
            $merged = [];
            foreach ($day as $s) {
                $start = $s->started_at->getTimestamp();
                $end = max($start, $s->ended_at->getTimestamp());
                if ($merged && $start <= $merged[count($merged) - 1][1]) {
                    $merged[count($merged) - 1][1] = max($merged[count($merged) - 1][1], $end);
                } else {
                    $merged[] = [$start, $end];
                }
            }

            $gaps = [];
            for ($i = 1; $i < count($merged); $i++) {
                $gaps[] = $merged[$i][0] - $merged[$i - 1][1];
            }

            $rows[] = ['employee_id' => (int) $employee, 'date' => $date, 'gaps' => $gaps];
        }

        return $rows;
    }

    private function visits(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        // تُنسب الزيارة لليوم الذي استُدعي فيه العميل
        return Visit::query()
            ->whereNotNull('called_at')
            ->whereBetween('called_at', [$from, $to])
            ->get(['id', 'employee_id', 'deal_id', 'status', 'outcome', 'wait_seconds', 'service_seconds', 'called_at', 'created_at']);
    }

    private function range(CarbonInterface $from, CarbonInterface $to): array
    {
        return [CarbonImmutable::parse($from)->startOfDay(), CarbonImmutable::parse($to)->endOfDay()];
    }

    private function avg(Collection $values): ?int
    {
        $values = $values->filter(fn ($v) => $v !== null);

        return $values->isNotEmpty() ? (int) round($values->avg()) : null;
    }

    private function avgMinutes(Collection $seconds): ?float
    {
        $avg = $this->avg($seconds);

        return $avg === null ? null : round($avg / 60, 1);
    }

    /** الفرق عن متوسط الفريق بالنسبة المئوية (+ أعلى، - أقل) */
    private function delta(int|float|null $value, int|float|null $team): ?int
    {
        if ($value === null || ! $team) {
            return null;
        }

        return (int) round(($value - $team) / $team * 100);
    }
}
