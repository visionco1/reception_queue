<?php

use App\Support\Settings;

if (! function_exists('setting')) {
    /** قراءة إعداد من لوحة الإدارة (مع القيمة الافتراضية من config/qms.php) */
    function setting(string $key, mixed $fallback = null): mixed
    {
        return app(Settings::class)->get($key, $fallback);
    }
}
