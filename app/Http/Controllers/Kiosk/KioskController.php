<?php

namespace App\Http\Controllers\Kiosk;

use App\Enums\CustomerType;
use App\Enums\MessageKey;
use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\BankAgent;
use App\Rules\Mobile;
use App\Services\QueueService;
use App\Services\WhatsApp\WhatsAppService;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** آيباد الاستقبال: العميل يسجل بنفسه ويأخذ رقم انتظار */
class KioskController extends Controller
{
    public function show(string $token)
    {
        return view('kiosk.show', [
            'token' => $token,
            'banks' => Bank::query()->active()->ordered()
                ->with(['agents' => fn ($q) => $q->active()->ordered()->select('id', 'bank_id', 'name')])
                ->get(['id', 'name']),
        ]);
    }

    public function register(Request $request, QueueService $queue, WhatsAppService $whatsapp, string $token)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'mobile' => ['required', 'string', 'max:20', new Mobile],
            'customer_type' => ['required', Rule::in(array_keys(CustomerType::queueOptions()))],
            'bank_id' => ['required', function ($attr, $value, $fail) {
                if ($value !== 'none' && ! Bank::query()->active()->whereKey($value)->exists()) {
                    $fail(__('اختر البنك من القائمة'));
                }
            }],
            // وكيل البنك: مطلوب عند اختيار بنك له وكلاء (أو «بدون وكيل»)
            'agent_id' => ['nullable', function ($attr, $value, $fail) use ($request) {
                $bankId = $request->input('bank_id');
                if ($value === 'none' || $bankId === 'none') {
                    return;
                }
                if (! BankAgent::query()->active()->whereKey($value)->where('bank_id', $bankId)->exists()) {
                    $fail(__('اختر الوكيل من القائمة'));
                }
            }],
        ], [
            'name.required' => __('اكتب اسمك'),
            'name.min' => __('اكتب اسمك'),
            'customer_type.required' => __('اختر هل أنت بائع أم مشتري'),
            'bank_id.required' => __('اختر البنك'),
        ]);

        $bankId = $data['bank_id'] === 'none' ? null : (int) $data['bank_id'];
        $agentId = ($data['agent_id'] ?? null) && $data['agent_id'] !== 'none' ? (int) $data['agent_id'] : null;

        if ($bankId && ! $agentId && ($data['agent_id'] ?? null) !== 'none'
            && BankAgent::query()->active()->where('bank_id', $bankId)->exists()) {
            throw ValidationException::withMessages(['agent_id' => __('اختر وكيل البنك، أو «بدون وكيل»')]);
        }

        [$visit, $existing] = $queue->registerWalkIn(
            name: trim(preg_replace('/\s+/u', ' ', $data['name'])),
            mobile: Phone::normalize($data['mobile']),
            type: CustomerType::from($data['customer_type']),
            bankId: $bankId,
            agentId: $agentId,
        );

        // ترتيب الطابور بحسب وقت التسجيل (المعرّف التسلسلي للتذكرة يحسم التساوي في الثانية نفسها)
        $ahead = $queue->waitingQuery()
            ->where('queue_tickets.id', '<', $visit->ticket->id)
            ->count();

        return response()->json([
            'ticket_no' => $visit->ticket->ticket_no,
            'type' => $visit->customer_type->value,
            'type_label' => $visit->customer_type->label(),
            'first_name' => $visit->customer->firstName(),
            'phone_masked' => Phone::mask($visit->customer->mobile),
            'ahead' => $visit->status->value === 'waiting' ? $ahead : 0,
            'existing' => $existing,
            'agent' => $visit->agent?->name,
            'whatsapp' => $whatsapp->messageEnabled(MessageKey::Registered),
        ], $existing ? 200 : 201);
    }

    /** يسمح بإضافة صفحة الاستقبال للشاشة الرئيسية في الآيباد لتعمل بملء الشاشة */
    public function manifest(string $token)
    {
        return response()->json([
            'name' => setting('company_name').' — '.__('الاستقبال'),
            'short_name' => __('الاستقبال'),
            'start_url' => route('kiosk.show', $token),
            'display' => 'fullscreen',
            'orientation' => 'any',
            'background_color' => '#f3f5f3',
            'theme_color' => '#0f5c4d',
            'lang' => 'ar',
            'dir' => 'rtl',
        ])->header('Content-Type', 'application/manifest+json');
    }
}
