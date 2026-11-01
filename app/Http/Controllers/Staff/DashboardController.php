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
            'clientTypes' => \App\Models\ClientType::query()->active()->ordered()->get(['id', 'name']),
            'outcomes' => \App\Enums\Outcome::options(),
            'vias' => \App\Enums\ViaDetail::options(),
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

    /** قائمة انتظار اليوم: كل أرقام اليوم بحالاتها، مع حذف من لم يكمل طلبه */
    public function today(Request $request)
    {
        $status = $request->query('status', 'all');
        $mine = $request->boolean('mine');
        $q = trim((string) $request->query('q', ''));
        $user = $request->user();

        $base = Visit::query()
            ->whereHas('ticket', fn ($t) => $t->whereDate('ticket_date', today()));

        $visits = (clone $base)
            ->with('customer', 'ticket', 'bank', 'agent', 'clientType', 'employee', 'desk', 'assignedDesk')
            ->when($mine, fn ($w) => $w->where(fn ($x) => $x->where('employee_id', $user->id)->orWhere('created_by', $user->id)))
            ->when($status === 'active', fn ($w) => $w->whereIn('status', VisitStatus::active()))
            ->when(in_array($status, ['waiting', 'completed', 'no_show', 'cancelled'], true), fn ($w) => $w->where('status', $status))
            ->when($q !== '', function ($w) use ($q) {
                $digits = preg_replace('/\D+/', '', Phone::toAsciiDigits($q));
                $w->where(fn ($x) => $x->whereHas('customer', fn ($c) => $c->where('name', 'like', "%{$q}%")
                    ->when(strlen($digits) >= 3, fn ($c2) => $c2->orWhere('mobile', 'like', '%'.ltrim($digits, '0').'%')))
                    ->orWhereHas('ticket', fn ($t) => $t->where('ticket_no', 'like', '%'.strtoupper($q).'%')));
            })
            ->join('queue_tickets', 'queue_tickets.visit_id', '=', 'visits.id')
            ->orderBy('queue_tickets.queued_at')
            ->orderBy('queue_tickets.id')
            ->select('visits.*')
            ->get();

        $all = (clone $base)->get(['status']);

        return view('staff.today', [
            'visits' => $visits,
            'status' => $status,
            'mine' => $mine,
            'q' => $q,
            'counts' => [
                'all' => $all->count(),
                'waiting' => $all->where('status', VisitStatus::Waiting)->count(),
                'active' => $all->whereIn('status', [VisitStatus::Called, VisitStatus::InService])->count(),
                'completed' => $all->where('status', VisitStatus::Completed)->count(),
                'no_show' => $all->where('status', VisitStatus::NoShow)->count(),
                'cancelled' => $all->where('status', VisitStatus::Cancelled)->count(),
            ],
        ]);
    }

    private function buildState(User $user, QueueService $queue): array
    {
        $user->loadMissing('currentDesk', 'desk');
        $desk = $user->workingDesk();

        $waiting = $queue->waitingQuery()->with('customer', 'ticket', 'bank', 'agent', 'assignedDesk')->get();
        $current = $user->activeVisit()?->load('customer', 'ticket', 'bank', 'agent', 'sessions');

        // أطراف الصفقات لكل من في الانتظار (لإظهار «مرتبط بـ...» في القائمة)
        $dealIds = $waiting->pluck('deal_id')->filter()->unique();
        $dealMembers = $dealIds->isEmpty() ? collect() : Visit::query()->whereIn('deal_id', $dealIds)
            ->with('ticket', 'customer')->get()->groupBy('deal_id');
        $dealWith = fn (Visit $v) => $v->deal_id
            ? $dealMembers->get($v->deal_id, collect())->reject(fn ($m) => $m->id === $v->id)
                ->map(fn ($m) => $m->ticket?->ticket_no ?? $m->customer->shortName())->values()
            : [];

        // من يعمل على كل مكتب الآن (لقائمة التحويل)
        $staffAt = User::query()->where('is_active', true)->whereNotNull('current_desk_id')
            ->get(['id', 'name', 'current_desk_id'])->groupBy('current_desk_id');
        $desks = Desk::query()->active()->ordered()->get()->map(fn (Desk $d) => [
            'id' => $d->id,
            'number' => $d->number,
            'staff' => $staffAt->get($d->id, collect())->pluck('name')->implode('، '),
        ])->values();

        $today = Visit::query()->whereNotNull('called_at')->where('called_at', '>=', today());
        $servedToday = (clone $today)->where('employee_id', $user->id)->where('status', VisitStatus::Completed)->count();
        $avgWait = (clone $today)->whereNotNull('wait_seconds')->avg('wait_seconds');
        $avgService = (clone $today)->where('status', VisitStatus::Completed)->whereNotNull('service_seconds')->avg('service_seconds');

        // من حُوِّل لمكتبي يظهر أولًا، ثم الباقي بالأقدمية
        $rows = $waiting->sortBy(fn (Visit $v) => $desk && $v->assigned_desk_id === $desk->id ? 0 : 1)->values();
        $oldest = $waiting->first();

        return [
            'server_time' => now()->toIso8601String(),
            'desk' => $desk ? ['id' => $desk->id, 'number' => $desk->number] : null,
            'desks' => $desks,
            'stats' => [
                'waiting' => $waiting->count(),
                'served_today' => $servedToday,
                'avg_wait' => $avgWait ? (int) round($avgWait) : null,
                'avg_service' => $avgService ? (int) round($avgService) : null,
            ],
            'oldest' => $oldest ? ['ticket_no' => $oldest->ticket->ticket_no, 'since' => $oldest->ticket->issued_at->toIso8601String()] : null,
            'waiting' => $rows->map(fn (Visit $v) => [
                'id' => $v->id,
                'ticket_no' => $v->ticket->ticket_no,
                'name' => $v->customer->name,
                'phone' => Phone::mask($v->customer->mobile),
                'type' => $v->customer_type->value,
                'type_label' => $v->typeLabel(),
                'via_kind' => $v->bank_id ? 'bank' : 'other',
                'via' => $v->viaLabel(),
                'agent' => $v->agent?->name,
                'deal_with' => $dealWith($v),
                'issued_at' => $v->ticket->issued_at->toIso8601String(),
                'remote' => $v->channel === Channel::Remote,
                'requeued' => $v->ticket->no_show_count > 0,
                'assigned_desk' => $v->assignedDesk?->number,
                'assigned_to_me' => $desk && $v->assigned_desk_id === $desk->id,
                'returning' => $v->is_returning,
            ])->values(),
            'current' => $current ? [
                'id' => $current->id,
                'ticket_no' => $current->ticket?->ticket_no,
                'name' => $current->customer->name,
                'mobile' => Phone::format($current->customer->mobile),
                'type' => $current->customer_type->value,
                'type_label' => $current->typeLabel(),
                'via' => $current->viaLabel(),
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
                'no_shows' => $current->ticket?->no_show_count ?? 0,
                'remote' => $current->channel === Channel::Remote,
                'returning' => $current->is_returning,
                'visits_count' => $current->customer->visits_count,
                'customer_url' => route('staff.customers.show', $current->customer_id),
            ] : null,
            'counts' => [
                'all' => $waiting->count(),
                'buyer' => $waiting->where('customer_type', CustomerType::Buyer)->count(),
                'seller' => $waiting->where('customer_type', CustomerType::Seller)->count(),
                'bank' => $waiting->whereNotNull('bank_id')->count(),
                'other' => $waiting->whereNull('bank_id')->count(),
            ],
        ];
    }
}
