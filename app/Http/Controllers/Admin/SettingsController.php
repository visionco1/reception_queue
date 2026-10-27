<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class SettingsController extends Controller
{
    public function edit(Settings $settings)
    {
        return view('admin.settings', ['s' => $settings->all()]);
    }

    public function update(Request $request, Settings $settings)
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:120'],
            'welcome_message' => ['nullable', 'string', 'max:300'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
            'ticket_prefix_buyer' => ['required', 'string', 'max:3', 'regex:/^[A-Za-z]+$/', 'different:ticket_prefix_seller'],
            'ticket_prefix_seller' => ['required', 'string', 'max:3', 'regex:/^[A-Za-z]+$/'],
            'ticket_digits' => ['required', 'integer', 'min:2', 'max:5'],
            'kiosk_reset_seconds' => ['required', 'integer', 'min:5', 'max:120'],
            'country_code' => ['required', 'string', 'regex:/^\d{1,4}$/'],
            'employee_search_scope' => ['required', 'in:all,own'],
            'display_bg' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'display_primary' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'display_accent' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'display_recent_count' => ['required', 'integer', 'min:1', 'max:10'],
        ], [
            'ticket_prefix_buyer.different' => __('يجب أن يختلف رمز المشتري عن رمز البائع'),
            'ticket_prefix_buyer.regex' => __('الرمز حروف إنجليزية فقط'),
            'ticket_prefix_seller.regex' => __('الرمز حروف إنجليزية فقط'),
        ]);

        $data['ticket_prefix_buyer'] = strtoupper($data['ticket_prefix_buyer']);
        $data['ticket_prefix_seller'] = strtoupper($data['ticket_prefix_seller']);
        foreach (['display_show_employee', 'display_sound', 'display_voice'] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }

        unset($data['logo']);
        if ($request->hasFile('logo')) {
            $data['logo_path'] = $this->storeLogo($request);
        } elseif ($request->boolean('remove_logo')) {
            $data['logo_path'] = null;
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

    private function storeLogo(Request $request): string
    {
        $dir = public_path('uploads');
        File::ensureDirectoryExists($dir);
        $file = $request->file('logo');
        $name = 'logo-'.now()->format('YmdHis').'.'.strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $file->move($dir, $name);

        return 'uploads/'.$name;
    }
}
