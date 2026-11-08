<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SettingsController extends Controller
{
    public function edit(Settings $settings)
    {
        return view('admin.settings', ['s' => $settings->all()]);
    }

    public function update(Request $request, Settings $settings)
    {
        $prefix = ['required', 'string', 'max:3', 'regex:/^[A-Za-z]+$/'];
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:120'],
            'welcome_message' => ['nullable', 'string', 'max:300'],
            'ticket_prefix_buyer' => [...$prefix, 'different:ticket_prefix_seller', 'different:ticket_prefix_other'],
            'ticket_prefix_seller' => [...$prefix, 'different:ticket_prefix_other'],
            'ticket_prefix_other' => $prefix,
            'ticket_digits' => ['required', 'integer', 'min:2', 'max:5'],
            'kiosk_reset_seconds' => ['required', 'integer', 'min:5', 'max:120'],
            'default_service_minutes' => ['required', 'integer', 'min:1', 'max:120'],
            'country_code' => ['required', 'string', 'regex:/^\d{1,4}$/'],
            'employee_search_scope' => ['required', 'in:all,own'],
            'work_start' => ['nullable', 'date_format:H:i', 'required_with:work_end'],
            'work_end' => ['nullable', 'date_format:H:i', 'required_with:work_start'],
            'display_mode' => ['required', 'in:current,offices'],
            'display_alert_seconds' => ['required', 'integer', 'min:3', 'max:60'],
            'display_idle_minutes' => ['required', 'integer', 'min:1', 'max:240'],
            'display_volume' => ['required', 'integer', 'min:0', 'max:100'],
            'display_recent_count' => ['required', 'integer', 'min:1', 'max:8'],
            'ticker_messages' => ['nullable', 'string', 'max:2000'],
        ], [
            'ticket_prefix_buyer.different' => __('يجب أن تختلف الرموز عن بعضها'),
            'ticket_prefix_seller.different' => __('يجب أن تختلف الرموز عن بعضها'),
            'ticket_prefix_buyer.regex' => __('الرمز حروف إنجليزية فقط'),
            'ticket_prefix_seller.regex' => __('الرمز حروف إنجليزية فقط'),
            'ticket_prefix_other.regex' => __('الرمز حروف إنجليزية فقط'),
            'work_start.required_with' => __('حدد بداية ونهاية الدوام معًا'),
            'work_end.required_with' => __('حدد بداية ونهاية الدوام معًا'),
        ]);

        foreach (['ticket_prefix_buyer', 'ticket_prefix_seller', 'ticket_prefix_other'] as $key) {
            $data[$key] = strtoupper($data[$key]);
        }
        $data['work_start'] ??= '';
        $data['work_end'] ??= '';
        $data['ticker_messages'] ??= '';
        foreach (['display_sound', 'display_voice'] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }

        $changed = $settings->set($data);
        if ($changed) {
            AuditLog::record('settings.updated', null, __('تعديل الإعدادات'), ['keys' => $changed]);
        }

        return back()->with('status', __('حُفظت الإعدادات'));
    }

    public function regenerateToken(Settings $settings, string $device)
    {
        $settings->set([$device.'_token' => Str::random(32)]);
        AuditLog::record('settings.token_regenerated', null, $device === 'kiosk'
            ? __('تغيير رابط آيباد الاستقبال')
            : __('تغيير رابط شاشة العرض'));

        return back()->with('status', __('أُنشئ رابط جديد. الرابط القديم لم يعد يعمل؛ افتح الرابط الجديد على الجهاز.'));
    }
}
