<?php

namespace App\Http\Controllers\Kiosk;

use App\Enums\CustomerType;
use App\Enums\MessageKey;
use App\Enums\ViaDetail;
use App\Exceptions\QueueException;
use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\BankAgent;
use App\Rules\Mobile;
use App\Services\QueueService;
use App\Services\WhatsApp\WhatsAppService;
use App\Support\Phone;
use App\Support\WorkHours;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** آيباد الاستقبال: العميل يسجل بنفسه ويأخذ رقم انتظار */
class KioskController extends Controller
{
    public function show(string $token, QueueService $queue)
    {
        $banks = Bank::query()->active()->ordered()->get(['id', 'name', 'is_featured']);

        return view('kiosk.show', [
            'token' => $token,
            'featured' => ($banks->where('is_featured', true)->isNotEmpty() ? $banks->where('is_featured', true) : $banks)->take(4)->values(),
            'banks' => $banks,
            // وكلاء كل بنك يظهرون بعد اختيار البنك
            'agents' => BankAgent::query()->active()->ordered()->whereIn('bank_id', $banks->pluck('id'))->get(['id', 'bank_id', 'name'])
                ->groupBy('bank_id')->map(fn ($g) => $g->map(fn ($a) => ['id' => $a->id, 'name' => $a->name])->values()),
            'status' => $this->status($queue),
        ]);
    }

    /** حالة الانتظار في أعلى الشاشة: عدد المنتظرين والوقت التقريبي */
    public function state(string $token, QueueService $queue)
    {
        return response()->json($this->status($queue))->header('Cache-Control', 'no-store');
    }

    public function register(Request $request, QueueService $queue, WhatsAppService $whatsapp, string $token)
    {
        if (! WorkHours::isOpen()) {
            throw new QueueException(__('التسجيل متاح في ساعات العمل :hours', ['hours' => WorkHours::label()]), 423);
        }

        $cc = Phone::countryCode();
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            // جوال سعودي: 9 أرقام تبدأ بـ 5 (رمز الدولة +966 ثابت)
            'mobile' => $cc === '966'
                ? ['required', 'string', 'regex:/^\s*0?5\d{8}\s*$/']
                : ['required', 'string', 'max:20', new Mobile],
            'customer_type' => ['required', Rule::in(array_keys(CustomerType::queueOptions()))],
            'via' => ['required', Rule::in(['bank', 'other'])],
            'bank_id' => ['nullable', 'required_if:via,bank', Rule::exists('banks', 'id')->where('is_active', true)],
            'via_detail' => ['nullable', Rule::enum(ViaDetail::class)],
            'agent_id' => ['nullable', Rule::exists('bank_agents', 'id')->where('is_active', true)->where('bank_id', (int) $request->input('bank_id'))],
        ], [
            'name.required' => __('اكتب اسمك'),
            'name.min' => __('الاسم حرفين على الأقل'),
            'mobile.required' => __('اكتب رقم الجوال'),
            'mobile.regex' => __('رقم الجوال 9 أرقام يبدأ بـ 5'),
            'customer_type.required' => __('اختر مشتري أو بائع'),
            'via.required' => __('اختر عن طريق بنك أو أخرى'),
            'bank_id.required_if' => __('اختر البنك'),
            'bank_id.exists' => __('اختر البنك من القائمة'),
            'agent_id.exists' => __('اختر الوكيل من القائمة'),
        ]);

        $bankId = $data['via'] === 'bank' ? (int) $data['bank_id'] : null;

        [$visit, $existing] = $queue->registerWalkIn(
            name: trim(preg_replace('/\s+/u', ' ', $data['name'])),
            mobile: Phone::normalize(Phone::toAsciiDigits($data['mobile'])),
            type: CustomerType::from($data['customer_type']),
            bankId: $bankId,
            agentId: $bankId && ! empty($data['agent_id']) ? (int) $data['agent_id'] : null,
            via: $bankId ? null : ViaDetail::tryFrom($data['via_detail'] ?? ''),
        );

        $ahead = $queue->ahead($visit);

        return response()->json([
            'ticket_no' => $visit->ticket->ticket_no,
            'type' => $visit->customer_type->value,
            'type_label' => $visit->customer_type->label(),
            'via' => $visit->bank?->name ?? ($visit->via_detail?->label() ?? __('أخرى')),
            'via_en' => $visit->bank?->name ?? ($visit->via_detail?->labelEn() ?? 'Other'),
            'first_name' => $visit->customer->firstName(),
            'phone_masked' => Phone::mask($visit->customer->mobile),
            'ahead' => $ahead,
            'minutes' => $queue->estimateMinutes($ahead),
            'existing' => $existing,
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
            'orientation' => 'landscape',
            'background_color' => '#F7F3EC',
            'theme_color' => '#1F261F',
            'lang' => 'ar',
            'dir' => 'rtl',
        ])->header('Content-Type', 'application/manifest+json');
    }

    private function status(QueueService $queue): array
    {
        $waiting = $queue->waitingQuery()->count();

        return [
            'waiting' => $waiting,
            'minutes' => $queue->estimateMinutes($waiting),
            'open' => WorkHours::isOpen(),
            'hours' => WorkHours::label(),
        ];
    }
}
