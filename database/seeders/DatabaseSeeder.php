<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Bank;
use App\Models\ClientType;
use App\Models\Desk;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * البيانات الأساسية للتشغيل: المكاتب، البنوك، حساب المدير، وروابط الأجهزة.
 * آمن للتشغيل أكثر من مرة.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (range(1, 4) as $n) {
            Desk::firstOrCreate(['number' => (string) $n], ['sort_order' => $n]);
        }

        $banks = [
            'مصرف الراجحي', 'البنك الأهلي السعودي', 'بنك الرياض', 'مصرف الإنماء', 'بنك البلاد',
            'البنك العربي الوطني', 'البنك السعودي الأول (ساب)', 'بنك الجزيرة', 'البنك السعودي الفرنسي',
            'البنك السعودي للاستثمار', 'بنك الخليج الدولي',
        ];
        foreach ($banks as $i => $name) {
            Bank::firstOrCreate(['name' => $name], ['sort_order' => $i + 1]);
        }

        // أنواع العملاء الأخرى للعملاء عن بعد (يعدلها مدير النظام)
        if (! ClientType::query()->exists()) {
            foreach (['استشارة قانونية', 'توثيق عقد', 'مستثمر'] as $i => $name) {
                ClientType::create(['name' => $name, 'sort_order' => $i + 1]);
            }
        }

        if (! User::query()->where('role', Role::Admin)->exists()) {
            $password = config('qms.admin.password') ?: 'ChangeMe@2026';
            $username = config('qms.admin.username') ?: 'admin';
            User::create([
                'name' => 'مدير النظام',
                'username' => $username,
                'password' => $password,
                'role' => Role::Admin,
                'desk_id' => Desk::query()->orderBy('sort_order')->value('id'),
                'is_active' => true,
            ]);
            $this->command?->warn('حساب المدير: '.$username.' / '.$password.' — غيّر كلمة المرور فور الدخول.');
        }

        $settings = app(Settings::class);
        foreach (['kiosk_token', 'display_token'] as $key) {
            if (! $settings->get($key)) {
                $settings->set([$key => Str::random(32)]);
            }
        }

        $this->command?->info('رابط الآيباد: '.url('/kiosk/'.$settings->get('kiosk_token')));
        $this->command?->info('رابط شاشة العرض: '.url('/display/'.$settings->get('display_token')));
    }
}
