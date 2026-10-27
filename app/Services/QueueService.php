<?php

namespace App\Services;

use App\Enums\Channel;
use App\Enums\CustomerType;
use App\Enums\MessageKey;
use App\Enums\VisitStatus;
use App\Exceptions\QueueException;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Desk;
use App\Models\DisplayCall;
use App\Models\QueueTicket;
use App\Models\ServiceSession;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitStatusLog;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * منطق الطابور كاملًا: تسجيل العميل الحضوري، أخذ العميل بقفل لحظي،
 * الاستدعاء، بدء وإنهاء الخدمة، التخطي، الإرجاع للطابور، والإلغاء،
 * وربط البائع بالمشتري في صفقة واحدة يُستدعى أطرافها معًا لنفس المكتب.
 * كل تغيير حالة يُسجَّل مع الوقت والموظف.
 */
class QueueService
{
    public function __construct(
        private readonly TicketNumberService $tickets,
        private readonly WhatsAppService $whatsapp,
    ) {}

    /**
     * تسجيل عميل حضوري من آيباد الاستقبال.
     *
     * @return array{0: Visit, 1: bool} الزيارة، وهل كانت لديه تذكرة نشطة اليوم مسبقًا
     */
    public function registerWalkIn(string $name, string $mobile, CustomerType $type, ?int $bankId, ?int $agentId = null): array
    {
        [$visit, $existing] = DB::transaction(function () use ($name, $mobile, $type, $bankId, $agentId) {
            $customer = Customer::query()->where('mobile', $mobile)->lockForUpdate()->first();

            if ($customer) {
                // منع إصدار رقمين لنفس العميل في نفس اليوم ما دام دوره لم ينتهِ
                $active = $customer->visits()
                    ->where('channel', Channel::InPerson)
                    ->whereIn('status', [VisitStatus::Waiting->value, ...VisitStatus::active()])
                    ->whereHas('ticket', fn ($q) => $q->whereDate('ticket_date', today()))
                    ->with('ticket')
                    ->latest('id')
                    ->first();

                if ($active) {
                    return [$active, true];
                }
            } else {
                $customer = Customer::create(['name' => $name, 'mobile' => $mobile]);
            }

            $visit = $customer->visits()->create([
                'channel' => Channel::InPerson,
                'customer_type' => $type,
                'bank_id' => $bankId,
                'agent_id' => $agentId,
                'status' => VisitStatus::Waiting,
                'is_returning' => $customer->visits_count > 0,
            ]);

            $this->tickets->issue($visit);
            $this->logStatus($visit, null, VisitStatus::Waiting, null, __('تسجيل من آيباد الاستقبال'));
            $customer->refreshVisitStats();

            return [$visit, false];
        });

        $visit->load('ticket', 'customer');

        if (! $existing) {
            $this->whatsapp->notify($visit, MessageKey::Registered);
        }

        return [$visit, $existing];
    }

    /**
     * أخذ عميل من قائمة الانتظار. التحديث المشروط (WHERE status = waiting) ذري
     * على مستوى قاعدة البيانات، فلا يمكن لموظفين أخذ نفس العميل في نفس اللحظة.
     * إذا كان العميل مربوطًا بطرف آخر في الانتظار يُستدعى الطرفان معًا لنفس المكتب.
     */
    public function take(Visit $visit, User $employee): Visit
    {
        $desk = $this->deskOf($employee);

        $called = DB::transaction(function () use ($visit, $employee, $desk) {
            // قفل سجل الموظف لمنع أخذ عميلين معًا بنقرة مزدوجة
            User::query()->whereKey($employee->id)->lockForUpdate()->first();

            if ($this->activeQuery($employee)->exists()) {
                throw new QueueException(__('لديك عميل حالي. أنهِ خدمته أو أرجعه للطابور قبل أخذ عميل جديد.'));
            }

            $now = now();
            $batch = (string) Str::uuid();
            $first = $this->callVisit($visit, $employee, $desk, $batch, $now);

            if (! $first) {
                throw QueueException::alreadyTaken();
            }

            $called = collect([$first]);

            if ($first->deal_id) {
                $this->waitingPartners($first)->each(function (Visit $partner) use ($employee, $desk, $batch, $now, $called) {
                    if ($v = $this->callVisit($partner, $employee, $desk, $batch, $now, __('استدعاء مع الطرف الآخر'))) {
                        $called->push($v);
                    }
                });
            }

            AuditLog::record('visit.taken', $first, __(':user أخذ :tickets إلى مكتب :desk', [
                'user' => $employee->name,
                'tickets' => $called->map(fn ($v) => $v->ticketNo() ?? '#'.$v->id)->implode(' + '),
                'desk' => $desk->number,
            ]));

            return $called;
        });

        $called->each(fn (Visit $v) => $this->whatsapp->notify($v, MessageKey::Called));
        $this->notifyNearTurn();

        return $called->first();
    }

    /** إعادة نداء العميل (وأطراف صفقته الذين معه) على الشاشة */
    public function recall(Visit $visit, User $employee): Visit
    {
        $group = $this->group($visit, $employee);

        DB::transaction(function () use ($group, $employee) {
            $batch = (string) Str::uuid();
            foreach ($group as $member) {
                $ticket = $member->ticket;
                if (! $ticket) {
                    continue;
                }
                $ticket->increment('recall_count');
                DisplayCall::create([
                    'visit_id' => $member->id,
                    'ticket_no' => $ticket->ticket_no,
                    'desk_label' => $ticket->desk_label ?? $member->desk?->number ?? '',
                    'employee_name' => $employee->name,
                    'kind' => 'recall',
                    'batch' => $batch,
                ]);
            }
            AuditLog::record('visit.recalled', $group->first(), __('إعادة نداء :tickets', [
                'tickets' => $group->map(fn ($v) => $v->ticketNo())->filter()->implode(' + '),
            ]));
        });

        return $group->first()->refresh();
    }

    /** العميل حضر إلى المكتب وبدأت الخدمة (للعميل وأطراف صفقته الذين معه) */
    public function startService(Visit $visit, User $employee): Visit
    {
        $group = $this->group($visit, $employee);

        DB::transaction(function () use ($group, $employee) {
            foreach ($group->where('status', VisitStatus::Called) as $member) {
                $member->update(['status' => VisitStatus::InService, 'service_started_at' => now()]);
                $this->logStatus($member, VisitStatus::Called, VisitStatus::InService, $employee);
            }
        });

        return $visit->refresh();
    }

    /** إنهاء الخدمة للعميل وأطراف صفقته الذين معه: تُحسب المدة وتُرسل رسالة الشكر */
    public function complete(Visit $visit, User $employee, ?string $notes = null): Visit
    {
        $group = $this->group($visit, $employee);

        DB::transaction(function () use ($group, $employee, $notes) {
            $now = now();
            foreach ($group as $member) {
                $from = $member->status;
                $session = $this->closeSession($member, 'completed', $now);

                $member->update([
                    'status' => VisitStatus::Completed,
                    'completed_at' => $now,
                    'service_seconds' => $session ? (int) $session->started_at->diffInSeconds($now) : null,
                    'notes' => $notes !== null && trim($notes) !== '' ? trim($notes) : $member->notes,
                ]);
                $this->logStatus($member, $from, VisitStatus::Completed, $employee);
                AuditLog::record('visit.completed', $member, __(':user أنهى خدمة :ticket', [
                    'user' => $employee->name, 'ticket' => $member->ticketNo() ?? '#'.$member->id,
                ]));
            }
        });

        $group->each(fn (Visit $v) => $this->whatsapp->notify($v->refresh(), MessageKey::Thanks));

        return $visit->refresh();
    }

    /** لم يحضر العميل بعد النداء (لهذا الطرف فقط) */
    public function noShow(Visit $visit, User $employee): Visit
    {
        $this->assertOwnActive($visit, $employee);

        DB::transaction(function () use ($visit, $employee) {
            $from = $visit->status;
            $this->closeSession($visit, 'no_show', now());
            $visit->update(['status' => VisitStatus::NoShow, 'completed_at' => now()]);
            $this->logStatus($visit, $from, VisitStatus::NoShow, $employee);
            AuditLog::record('visit.no_show', $visit, __('تخطي :ticket (لم يحضر)', ['ticket' => $visit->ticketNo() ?? '#'.$visit->id]));
        });

        return $visit->refresh();
    }

    /** إرجاع العميل إلى قائمة الانتظار ليأخذه موظف آخر (لهذا الطرف فقط) */
    public function release(Visit $visit, User $employee): Visit
    {
        $this->assertOwnActive($visit, $employee);

        DB::transaction(function () use ($visit, $employee) {
            $from = $visit->status;
            $this->closeSession($visit, 'released', now());
            $visit->ticket?->update(['called_at' => null, 'desk_label' => null]);
            $visit->update([
                'status' => VisitStatus::Waiting,
                'employee_id' => null,
                'desk_id' => null,
                'called_at' => null,
                'service_started_at' => null,
                'wait_seconds' => null,
            ]);
            $this->logStatus($visit, $from, VisitStatus::Waiting, $employee, __('أُرجع إلى الطابور'));
            AuditLog::record('visit.released', $visit, __(':user أرجع :ticket إلى الطابور', [
                'user' => $employee->name, 'ticket' => $visit->ticketNo() ?? '#'.$visit->id,
            ]));
        });

        return $visit->refresh();
    }

    /** إلغاء زيارة في الانتظار (غادر العميل أو تسجيل خاطئ) */
    public function cancel(Visit $visit, User $user, ?string $reason = null): Visit
    {
        $updated = DB::transaction(function () use ($visit, $user, $reason) {
            $changed = Visit::query()->whereKey($visit->id)
                ->where('status', VisitStatus::Waiting->value)
                ->update(['status' => VisitStatus::Cancelled->value, 'completed_at' => now(), 'updated_at' => now()]);

            if ($changed === 0) {
                throw new QueueException(__('لا يمكن إلغاء هذا العميل لأنه لم يعد في قائمة الانتظار.'), 409);
            }

            $this->logStatus($visit, VisitStatus::Waiting, VisitStatus::Cancelled, $user, $reason);
            AuditLog::record('visit.cancelled', $visit, __('إلغاء :ticket', ['ticket' => $visit->ticketNo() ?? '#'.$visit->id]), array_filter(['reason' => $reason]));

            return $visit->refresh();
        });

        $this->notifyNearTurn();

        return $updated;
    }

    /**
     * العملاء المرشحون للربط بعميل الموظف الحالي: زيارات اليوم من بائعين ومشترين
     * غير مرتبطين بصفقة. المقترح أولًا: نفس الوكيل والنوع المقابل، ثم نفس البنك.
     */
    public function linkCandidates(Visit $visit): Collection
    {
        $opposite = $visit->customer_type->opposite();

        return Visit::query()
            ->with('customer', 'ticket', 'agent', 'bank', 'employee', 'desk')
            ->whereKeyNot($visit->id)
            ->whereNull('deal_id')
            ->whereIn('customer_type', array_map(fn ($t) => $t->value, CustomerType::queueTypes()))
            ->where('status', '!=', VisitStatus::Cancelled->value)
            ->where('created_at', '>=', today())
            ->get()
            ->sortBy(fn (Visit $v) => [
                ($visit->agent_id && $v->agent_id === $visit->agent_id && $v->customer_type === $opposite) ? 0 : 1,
                $v->customer_type === $opposite ? 0 : 1,
                ($visit->bank_id && $v->bank_id === $visit->bank_id) ? 0 : 1,
                $v->status === VisitStatus::Waiting ? 0 : 1,
                $v->id,
            ])
            ->values();
    }

    /**
     * ربط عميل الموظف الحالي بطرف آخر في صفقة واحدة. إذا كان الطرف الآخر
     * في الانتظار يُستدعى فورًا لنفس المكتب ليُخدما معًا.
     *
     * @return array{0: Visit, 1: bool} الطرف الآخر، وهل استُدعي الآن
     */
    public function link(Visit $visit, Visit $other, User $employee): array
    {
        $this->assertOwnActive($visit, $employee);
        $desk = $this->deskOf($employee);
        $other->refresh();

        if ($other->is($visit)) {
            throw new QueueException(__('اختر عميلًا آخر للربط.'));
        }
        if (! $visit->customer_type->isParty() || ! $other->customer_type->isParty()) {
            throw new QueueException(__('الربط للبائعين والمشترين فقط.'));
        }
        if ($other->status === VisitStatus::Cancelled) {
            throw new QueueException(__('لا يمكن الربط بزيارة ملغاة.'));
        }
        if ($other->deal_id && $other->deal_id === $visit->deal_id) {
            throw new QueueException(__('هذا العميل مرتبط بالفعل بنفس الصفقة.'), 409);
        }
        if ($other->deal_id) {
            throw new QueueException(__('هذا العميل مرتبط بصفقة أخرى. فك ربطه أولًا.'), 409);
        }

        [$partner, $called] = DB::transaction(function () use ($visit, $other, $employee, $desk) {
            $deal = $visit->deal ?? Deal::create([
                'bank_id' => $visit->bank_id ?? $other->bank_id,
                'agent_id' => $visit->agent_id ?? $other->agent_id,
                'created_by' => $employee->id,
            ]);

            if (! $visit->deal_id) {
                $visit->update(['deal_id' => $deal->id]);
            }

            $attached = Visit::query()->whereKey($other->id)->whereNull('deal_id')
                ->update(['deal_id' => $deal->id, 'updated_at' => now()]);
            if ($attached === 0) {
                throw new QueueException(__('ربط موظف آخر هذا العميل للتو. حدّث القائمة واختر مرة أخرى.'), 409);
            }

            // الوكيل المعروف لأحد الطرفين ينتقل لمن لم يحدد وكيلًا من نفس البنك
            if ($deal->agent_id) {
                Visit::query()->where('deal_id', $deal->id)->whereNull('agent_id')
                    ->where(fn ($q) => $q->whereNull('bank_id')->orWhere('bank_id', $deal->bank_id))
                    ->update(['agent_id' => $deal->agent_id]);
            }

            $other->refresh();
            $called = null;
            if ($other->status === VisitStatus::Waiting && $other->isInPerson()) {
                $batch = (string) Str::uuid();
                $called = $this->callVisit($other, $employee, $desk, $batch, now(), __('استدعاء مع الطرف الآخر'));

                // من لم يصل بعد من أطراف الصفقة يظهر مع الطرف الجديد على الشاشة لنفس المكتب
                if ($called) {
                    $this->activeQuery($employee)->where('deal_id', $deal->id)->whereKeyNot($called->id)
                        ->where('status', VisitStatus::Called->value)->with('ticket')->get()
                        ->each(fn (Visit $m) => $m->ticket && DisplayCall::create([
                            'visit_id' => $m->id,
                            'ticket_no' => $m->ticket->ticket_no,
                            'desk_label' => $desk->number,
                            'employee_name' => $employee->name,
                            'kind' => 'call',
                            'batch' => $batch,
                        ]));
                }
            }

            AuditLog::record('deal.linked', $visit, __(':user ربط :a مع :b', [
                'user' => $employee->name,
                'a' => $visit->ticketNo() ?? $visit->customer->name,
                'b' => $other->ticketNo() ?? $other->customer->name,
            ]), ['deal' => $deal->id]);

            return [$called ?? $other, (bool) $called];
        });

        if ($called) {
            $this->whatsapp->notify($partner, MessageKey::Called);
            $this->notifyNearTurn();
        }

        return [$partner->load('customer', 'ticket'), $called];
    }

    /** فك ربط زيارة من صفقتها. إذا بقي طرف واحد تُحذف الصفقة */
    public function unlink(Visit $visit, User $user): void
    {
        if (! $visit->deal_id) {
            return;
        }

        DB::transaction(function () use ($visit, $user) {
            $dealId = $visit->deal_id;
            $visit->update(['deal_id' => null]);

            $remaining = Visit::query()->where('deal_id', $dealId)->count();
            if ($remaining < 2) {
                Visit::query()->where('deal_id', $dealId)->update(['deal_id' => null]);
                Deal::query()->whereKey($dealId)->delete();
            }

            AuditLog::record('deal.unlinked', $visit, __(':user فك ربط :ticket', [
                'user' => $user->name, 'ticket' => $visit->ticketNo() ?? $visit->customer->name,
            ]), ['deal' => $dealId]);
        });
    }

    /** قائمة الانتظار المشتركة لليوم مرتبة حسب وقت التسجيل */
    public function waitingQuery()
    {
        return Visit::query()
            ->where('visits.status', VisitStatus::Waiting->value)
            ->where('visits.channel', Channel::InPerson->value)
            ->join('queue_tickets', 'queue_tickets.visit_id', '=', 'visits.id')
            ->whereDate('queue_tickets.ticket_date', today())
            ->orderBy('queue_tickets.issued_at')
            ->orderBy('queue_tickets.id')
            ->select('visits.*');
    }

    /**
     * رسالة "اقترب دورك" الاختيارية: تُرسل لمن أمامه عدد محدد من العملاء
     * (لا تُرسل أكثر من مرة لنفس التذكرة).
     */
    public function notifyNearTurn(): void
    {
        if (! $this->whatsapp->messageEnabled(MessageKey::NearTurn)) {
            return;
        }

        $ahead = max(1, (int) setting('wa_near_turn_threshold', 1));
        $visit = $this->waitingQuery()->with('ticket', 'customer')->skip($ahead)->first();

        if (! $visit || ! $visit->ticket || $visit->ticket->near_notified_at) {
            return;
        }

        $claimed = QueueTicket::query()->whereKey($visit->ticket->id)->whereNull('near_notified_at')
            ->update(['near_notified_at' => now()]);

        if ($claimed) {
            $this->whatsapp->notify($visit, MessageKey::NearTurn, ['ahead' => $ahead]);
        }
    }

    public function logStatus(Visit $visit, ?VisitStatus $from, VisitStatus $to, ?User $user, ?string $note = null): void
    {
        VisitStatusLog::create([
            'visit_id' => $visit->id,
            'from_status' => $from,
            'to_status' => $to,
            'user_id' => $user?->id,
            'note' => $note,
        ]);
    }

    /** العملاء الذين مع الموظف الآن (عميل واحد، أو أطراف صفقة واحدة) */
    public function activeQuery(User $employee)
    {
        return Visit::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', VisitStatus::active());
    }

    /**
     * استدعاء زيارة واحدة إلى مكتب الموظف. يعيد null إذا لم تعد في الانتظار
     * (أخذها موظف آخر). يجب استدعاؤها داخل Transaction.
     */
    private function callVisit(Visit $visit, User $employee, Desk $desk, string $batch, Carbon $now, ?string $note = null): ?Visit
    {
        $taken = Visit::query()
            ->whereKey($visit->id)
            ->where('status', VisitStatus::Waiting->value)
            ->update([
                'status' => VisitStatus::Called->value,
                'employee_id' => $employee->id,
                'desk_id' => $desk->id,
                'called_at' => $now,
                'updated_at' => $now,
            ]);

        if ($taken === 0) {
            return null;
        }

        $visit = Visit::query()->with('ticket', 'customer')->findOrFail($visit->id);
        $ticket = $visit->ticket;

        $ticket?->update(['called_at' => $now, 'desk_label' => $desk->number]);
        $visit->update(['wait_seconds' => $ticket ? (int) $ticket->issued_at->diffInSeconds($now) : 0]);

        // تبدأ مدة الخدمة لحظة أخذ العميل
        ServiceSession::create([
            'visit_id' => $visit->id,
            'employee_id' => $employee->id,
            'desk_id' => $desk->id,
            'started_at' => $now,
        ]);

        $this->logStatus($visit, VisitStatus::Waiting, VisitStatus::Called, $employee,
            trim(__('مكتب :desk', ['desk' => $desk->number]).' '.($note ? '- '.$note : '')));

        if ($ticket) {
            DisplayCall::create([
                'visit_id' => $visit->id,
                'ticket_no' => $ticket->ticket_no,
                'desk_label' => $desk->number,
                'employee_name' => $employee->name,
                'kind' => 'call',
                'batch' => $batch,
            ]);
        }

        return $visit;
    }

    /** أطراف الصفقة الذين ما زالوا في انتظار اليوم */
    private function waitingPartners(Visit $visit): Collection
    {
        return $this->waitingQuery()->where('visits.deal_id', $visit->deal_id)->whereKeyNot($visit->id)->get();
    }

    /** العميل وأطراف صفقته الذين مع نفس الموظف الآن */
    private function group(Visit $visit, User $employee): Collection
    {
        $this->assertOwnActive($visit, $employee);

        if (! $visit->deal_id) {
            return collect([$visit->load('ticket', 'desk')]);
        }

        return $this->activeQuery($employee)
            ->where('deal_id', $visit->deal_id)
            ->with('ticket', 'desk')
            ->orderBy('called_at')
            ->orderBy('id')
            ->get();
    }

    private function deskOf(User $employee): Desk
    {
        $desk = $employee->workingDesk();
        if (! $desk) {
            throw new QueueException(__('اختر رقم مكتبك أولًا قبل أخذ العملاء.'));
        }

        return $desk;
    }

    private function assertOwnActive(Visit $visit, User $employee): void
    {
        $visit->refresh();

        if ($visit->employee_id !== $employee->id || ! in_array($visit->status->value, VisitStatus::active(), true)) {
            throw new QueueException(__('هذا العميل ليس عميلك الحالي، ربما تغيرت حالته.'), 409);
        }
    }

    private function closeSession(Visit $visit, string $status, $at): ?ServiceSession
    {
        $session = $visit->sessions()->whereNull('ended_at')->latest('id')->first();
        $session?->update(['ended_at' => $at, 'end_status' => $status]);

        return $session;
    }
}
