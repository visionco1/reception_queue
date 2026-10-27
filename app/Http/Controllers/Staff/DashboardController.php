<?php

namespace App\Http\Controllers\Staff;

use App\Enums\Channel;
use App\Enums\ClientSource;
use App\Enums\CustomerType;
use App\Enums\VisitStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Bank;
use App\Models\Desk;
use App\Models\User;
use App\Models\Visit;
use App\Services\QueueService;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request, QueueService $queue)
    {
        return view('staff.dashboard', [
            'initial' => $this->buildState($request->user(), $queue),
            'desks' => Desk::query()->active()->ordered()->get(),
            'banks' => Bank::query()->active()->ordered()->get(['id', 'name']),
            'agentsByBank' => \App\Models\BankAgent::query()->active()->ordered()->get(['id', 'bank_id', 'name'])
                ->groupBy('bank_id')->map(fn ($g) => $g->map(fn ($a) => ['id' => (string) $a->id, 'name' => $a->name])->values()),
            'sources' => ClientSource::options(),
            'types' => CustomerType::options(),
        ]);
    }

    public function state(Request $request, QueueService $queue)
    {
        return response()->json($this->buildState($request->user(), $queue))->header('Cache-Control', 'no-store');
    }

    public function setDesk(Request $request)
    {
        $data = $request->validate(['desk_id' => ['required', 'exists:desks,id']]);
        $desk = Desk::query()->active()->findOrFail($data['desk_id']);

        $user = $request->user();
        $user->update(['current_desk_id' => $desk->id]);
        AuditLog::record('staff.desk_selected', $user, __(':name يعمل على مكتب :desk', ['name' => $user->name, 'desk' => $desk->number]));

        return back()->with('status', __('تم اختيار مكتب :desk', ['desk' => $desk->number]));
    }

    /** عملائي اليوم / سجل الخدمات */
    public function today(Request $request)
    {
        $date = Carbon::parse($request->query('date', today()->toDateString()));
        $user = $request->user();

        $visits = Visit::query()
            ->with('customer', 'ticket', 'bank', 'agent', 'clientType', 'deal.visits.customer', 'deal.visits.ticket')
            ->where(fn ($q) => $q->where('employee_id', $user->id)->orWhere('created_by', $user->id))
            ->where(function ($q) use ($date) {
                $q->whereBetween('called_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])
                    ->orWhere(fn ($r) => $r->where('channel', Channel::Remote)->whereBetween('created_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()]));
            })
            ->orderByRaw('coalesce(called_at, created_at) desc')
            ->get();

        $served = $visits->where('channel', Channel::InPerson)->where('status', VisitStatus::Completed);

        return view('staff.today', [
            'date' => $date,
            'visits' => $visits,
            'summary' => [
                'served' => $served->count(),
                'remote' => $visits->where('channel', Channel::Remote)->count(),
                'no_show' => $visits->where('status', VisitStatus::NoShow)->count(),
                'avg_service' => $served->whereNotNull('service_seconds')->avg('service_seconds'),
            ],
        ]);
    }

    private function buildState(User $user, QueueService $queue): array
    {
        $user->loadMissing('currentDesk', 'desk');
        $desk = $user->workingDesk();

        $waiting = $queue->waitingQuery()->with('customer', 'ticket', 'bank', 'agent')->get();
        $current = $user->activeVisit()?->load('customer', 'ticket', 'bank', 'agent', 'sessions');

        // أطراف الصفقات لكل من في الانتظار (لإظهار «مرتبط بـ...» في القائمة)
        $dealIds = $waiting->pluck('deal_id')->filter()->unique();
        $dealMembers = $dealIds->isEmpty() ? collect() : Visit::query()->whereIn('deal_id', $dealIds)
            ->with('ticket', 'customer')->get()->groupBy('deal_id');
        $dealWith = fn (Visit $v) => $v->deal_id
            ? $dealMembers->get($v->deal_id, collect())->reject(fn ($m) => $m->id === $v->id)
                ->map(fn ($m) => $m->ticket?->ticket_no ?? $m->customer->shortName())->values()
            : [];

        $busy = Visit::query()
            ->whereIn('status', VisitStatus::active())
            ->with('employee', 'ticket', 'desk')
            ->get()
            ->map(fn (Visit $v) => [
                'desk' => $v->desk?->number,
                'employee' => $v->employee?->name,
                'ticket_no' => $v->ticket?->ticket_no,
                'status' => $v->status->value,
                'mine' => $v->employee_id === $user->id,
            ])
            ->sortBy('desk', SORT_NATURAL)
            ->values();

        $servedToday = Visit::query()
            ->where('employee_id', $user->id)
            ->where('status', VisitStatus::Completed)
            ->where('channel', Channel::InPerson)
            ->where('completed_at', '>=', today())
            ->count();

        return [
            'server_time' => now()->toIso8601String(),
            'desk' => $desk ? ['id' => $desk->id, 'number' => $desk->number] : null,
            'waiting' => $waiting->map(fn (Visit $v) => [
                'id' => $v->id,
                'ticket_no' => $v->ticket->ticket_no,
                'name' => $v->customer->shortName(),
                'full_name' => $v->customer->name,
                'type' => $v->customer_type->value,
                'type_label' => $v->customer_type->label(),
                'bank' => $v->bank?->name,
                'agent' => $v->agent?->name,
                'deal_with' => $dealWith($v),
                'issued_at' => $v->ticket->issued_at->toIso8601String(),
                'returning' => $v->is_returning,
                'visits_count' => $v->customer->visits_count,
            ])->values(),
            'current' => $current ? [
                'id' => $current->id,
                'ticket_no' => $current->ticket?->ticket_no,
                'name' => $current->customer->name,
                'mobile' => Phone::format($current->customer->mobile),
                'type' => $current->customer_type->value,
                'type_label' => $current->customer_type->label(),
                'bank' => $current->bank?->name,
                'agent' => $current->agent?->name,
                'agent_id' => $current->agent_id ? (string) $current->agent_id : '',
                'bank_id' => $current->bank_id ? (string) $current->bank_id : '',
                'linkable' => $current->customer_type->isParty(),
                'linked' => $current->partners()->map(fn (Visit $p) => [
                    'id' => $p->id,
                    'ticket_no' => $p->ticket?->ticket_no,
                    'name' => $p->customer->name,
                    'type' => $p->customer_type->value,
                    'type_label' => $p->typeLabel(),
                    'status' => $p->status->value,
                    'status_label' => $p->status->label(),
                    'mine' => $p->employee_id === $user->id && in_array($p->status->value, VisitStatus::active(), true),
                    'where' => $p->employee_id && $p->employee_id !== $user->id && in_array($p->status->value, VisitStatus::active(), true)
                        ? __('مع :name في مكتب :desk', ['name' => $p->employee?->name, 'desk' => $p->desk?->number])
                        : null,
                ])->values(),
                'status' => $current->status->value,
                'status_label' => $current->status->label(),
                'started_at' => $current->sessions->whereNull('ended_at')->last()?->started_at?->toIso8601String(),
                'wait' => $current->wait_seconds,
                'recalls' => $current->ticket?->recall_count ?? 0,
                'returning' => $current->is_returning,
                'visits_count' => $current->customer->visits_count,
                'customer_url' => route('staff.customers.show', $current->customer_id),
            ] : null,
            'counts' => [
                'waiting' => $waiting->count(),
                'buyer' => $waiting->where('customer_type', CustomerType::Buyer)->count(),
                'seller' => $waiting->where('customer_type', CustomerType::Seller)->count(),
                'served_today' => $servedToday,
            ],
            'busy' => $busy,
        ];
    }
}
