<?php

namespace App\Http\Controllers\Staff;

use App\Enums\VisitStatus;
use App\Http\Controllers\Controller;
use App\Models\Visit;
use App\Support\Phone;
use App\Services\QueueService;
use Illuminate\Http\Request;

/** أزرار الطابور في لوحة الموظف (طلبات JSON) */
class QueueActionController extends Controller
{
    public function __construct(private readonly QueueService $queue) {}

    public function take(Request $request, Visit $visit)
    {
        $user = $request->user();
        $visit = $this->queue->take($visit, $user);
        $with = $visit->partners()->filter(fn (Visit $p) => $p->employee_id === $user->id && $p->status === VisitStatus::Called);

        return response()->json(['message' => $with->isEmpty()
            ? __('تم استدعاء :ticket إلى مكتب :desk', ['ticket' => $visit->ticket?->ticket_no, 'desk' => $visit->ticket?->desk_label])
            : __('تم استدعاء :ticket مع الطرف الآخر :others إلى مكتب :desk', [
                'ticket' => $visit->ticket?->ticket_no,
                'others' => $with->map(fn ($p) => $p->ticketNo())->implode(' و '),
                'desk' => $visit->ticket?->desk_label,
            ])]);
    }

    public function recall(Request $request, Visit $visit)
    {
        $visit = $this->queue->recall($visit, $request->user());

        return response()->json(['message' => __('أُعيد نداء :ticket على الشاشة', ['ticket' => $visit->ticketNo()])]);
    }

    public function start(Request $request, Visit $visit)
    {
        $this->queue->startService($visit, $request->user());

        return response()->json(['message' => __('بدأت الخدمة')]);
    }

    public function complete(Request $request, Visit $visit)
    {
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:2000']]);
        $this->queue->complete($visit, $request->user(), $data['notes'] ?? null);

        return response()->json(['message' => __('انتهت الخدمة. يمكنك أخذ العميل التالي.')]);
    }

    public function noShow(Request $request, Visit $visit)
    {
        $this->queue->noShow($visit, $request->user());

        return response()->json(['message' => __('سُجّل العميل: لم يحضر')]);
    }

    public function release(Request $request, Visit $visit)
    {
        $this->queue->release($visit, $request->user());

        return response()->json(['message' => __('أُرجع العميل إلى قائمة الانتظار')]);
    }

    public function cancel(Request $request, Visit $visit)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);
        $this->queue->cancel($visit, $request->user(), $data['reason'] ?? null);

        return response()->json(['message' => __('أُلغي العميل من قائمة الانتظار')]);
    }

    /** المرشحون لربطهم بعميلي الحالي (بائع ↔ مشتري) */
    public function candidates(Request $request, Visit $visit)
    {
        abort_unless($visit->employee_id === $request->user()->id, 403);

        return response()->json([
            'candidates' => $this->queue->linkCandidates($visit)->map(fn (Visit $v) => [
                'id' => $v->id,
                'ticket_no' => $v->ticket?->ticket_no,
                'name' => $v->customer->name,
                'mobile' => Phone::format($v->customer->mobile),
                'type' => $v->customer_type->value,
                'type_label' => $v->typeLabel(),
                'agent' => $v->agent?->name,
                'bank' => $v->bank?->name,
                'channel' => $v->channel->label(),
                'status' => $v->status->value,
                'status_label' => $v->status->label(),
                'with' => $v->employee_id && in_array($v->status->value, VisitStatus::active(), true)
                    ? __('مع :name', ['name' => $v->employee?->name]) : null,
                'suggested' => $visit->agent_id && $v->agent_id === $visit->agent_id
                    && $v->customer_type === $visit->customer_type->opposite(),
            ])->values(),
        ]);
    }

    /** ربط عميلي الحالي بالطرف الآخر؛ إن كان ينتظر يُستدعى لمكتبي فورًا */
    public function link(Request $request, Visit $visit)
    {
        $data = $request->validate(['other_id' => ['required', 'integer', 'exists:visits,id']]);
        [$partner, $called] = $this->queue->link($visit, Visit::findOrFail($data['other_id']), $request->user());

        $label = $partner->ticketNo() ?? $partner->customer->name;

        return response()->json(['message' => $called
            ? __('رُبط :who واستُدعي إلى مكتبك الآن', ['who' => $label])
            : __('رُبط :who بالصفقة', ['who' => $label])]);
    }

    /** فك ربط طرف من الصفقة (لأطراف الصفقة الذين مع الموظف، أو للمشرف) */
    public function unlink(Request $request, Visit $visit)
    {
        $user = $request->user();
        $allowed = $user->isManager() || ($visit->deal_id && Visit::query()
            ->where('deal_id', $visit->deal_id)
            ->where('employee_id', $user->id)
            ->whereIn('status', VisitStatus::active())
            ->exists());
        abort_unless($allowed, 403, __('يمكن فك الربط من الموظف الذي يخدم الصفقة.'));

        $this->queue->unlink($visit, $user);

        return response()->json(['message' => __('فُك ربط :who', ['who' => $visit->ticketNo() ?? $visit->customer->name])]);
    }

    /** تصحيح وكيل البنك لعميلي الحالي (إذا اختار العميل وكيلًا خطأ على الآيباد) */
    public function agent(Request $request, Visit $visit)
    {
        abort_unless($visit->employee_id === $request->user()->id && in_array($visit->status->value, VisitStatus::active(), true), 403);
        $data = $request->validate([
            'agent_id' => ['nullable', \Illuminate\Validation\Rule::exists('bank_agents', 'id')->where('bank_id', $visit->bank_id ?? 0)],
        ], ['agent_id.exists' => __('الوكيل لا يتبع بنك العميل')]);

        $old = $visit->agent?->name;
        $visit->update(['agent_id' => $data['agent_id'] ?? null]);
        \App\Models\AuditLog::record('visit.agent_changed', $visit, __('تغيير وكيل البنك'), ['agent' => [$old, $visit->fresh()->agent?->name]]);

        return response()->json(['message' => __('حُفظ وكيل البنك')]);
    }
}
