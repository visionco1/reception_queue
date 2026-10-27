<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MessageKey;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Rules\Mobile;
use App\Services\WhatsApp\MetaCloudDriver;
use App\Services\WhatsApp\WhatsAppService;
use App\Support\Phone;
use App\Support\Settings;
use Illuminate\Http\Request;

/** نصوص رسائل واتساب وتفعيلها وقوالب Meta المعتمدة */
class MessageController extends Controller
{
    public function edit(Settings $settings, WhatsAppService $whatsapp)
    {
        $driver = $whatsapp->driver();

        return view('admin.messages', [
            's' => $settings->all(),
            'keys' => MessageKey::journey(),
            'driver' => $driver->name(),
            'configured' => ! ($driver instanceof MetaCloudDriver) || $driver->isConfigured(),
            'placeholders' => WhatsAppService::PLACEHOLDERS,
        ]);
    }

    public function update(Request $request, Settings $settings)
    {
        $rules = [
            'whatsapp_template_lang' => ['required', 'string', 'max:10'],
            'wa_near_turn_threshold' => ['required', 'integer', 'min:1', 'max:10'],
            'wa_services_line_text' => ['nullable', 'string', 'max:500'],
        ];
        foreach (MessageKey::journey() as $key) {
            $rules["wa_{$key->value}_text"] = ['required', 'string', 'max:1000'];
            $rules["wa_{$key->value}_template"] = ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9_]*$/'];
            $rules["wa_{$key->value}_params"] = ['nullable', 'string', 'max:200', 'regex:/^[a-z_,\s]*$/'];
        }

        $data = $request->validate($rules, [
            '*.regex' => __('اسم القالب أحرف إنجليزية صغيرة وأرقام وشرطة سفلية فقط'),
        ]);

        $data['whatsapp_enabled'] = $request->boolean('whatsapp_enabled');
        $data['wa_services_line_enabled'] = $request->boolean('wa_services_line_enabled');
        foreach (MessageKey::journey() as $key) {
            $data["wa_{$key->value}_enabled"] = $request->boolean("wa_{$key->value}_enabled");
            $data["wa_{$key->value}_template"] = (string) ($data["wa_{$key->value}_template"] ?? '');
            $data["wa_{$key->value}_params"] = preg_replace('/\s+/', '', (string) ($data["wa_{$key->value}_params"] ?? ''));
        }

        $changed = $settings->set($data);
        if ($changed) {
            AuditLog::record('messages.updated', null, __('تعديل رسائل واتساب'), ['keys' => $changed]);
        }

        return back()->with('status', __('حُفظت رسائل واتساب'));
    }

    /** إرسال نموذج من رسالة إلى رقم لاختبار الربط */
    public function test(Request $request, WhatsAppService $whatsapp)
    {
        $data = $request->validate([
            'phone' => ['required', 'string', new Mobile],
            'key' => ['required', 'in:registered,near_turn,called,thanks'],
        ]);

        $key = MessageKey::from($data['key']);
        $body = $whatsapp->render($key, [
            'name' => __('عميل تجريبي'), 'first_name' => __('عميل'), 'ticket' => 'M001', 'desk' => '1',
            'ahead' => setting('wa_near_turn_threshold', 1), 'company' => setting('company_name'),
        ]);
        $log = $whatsapp->sendTest(Phone::normalize($data['phone']), $body);
        AuditLog::record('messages.test', $log, __('رسالة تجريبية إلى :phone', ['phone' => $data['phone']]));

        return back()->with('status', __('أُرسلت الرسالة التجريبية إلى طابور الإرسال. تابع حالتها في سجل الرسائل.'));
    }
}
