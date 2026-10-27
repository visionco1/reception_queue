<?php

use Illuminate\Support\Facades\Schedule;

// المهام الدورية — تتطلب إضافة سطر cron واحد على الخادم:
// * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1

Schedule::command('qms:close-stale')->dailyAt('00:10');
Schedule::command('qms:backup')->dailyAt('02:30');
Schedule::command('qms:whatsapp-retry-stuck')->everyTenMinutes();
Schedule::command('queue:prune-failed --hours=720')->weekly();

// للاستضافة التي لا تدعم Supervisor: تشغيل عامل الطابور من المجدول كل دقيقة
if (config('qms.queue_via_scheduler')) {
    Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=1')
        ->everyMinute()
        ->withoutOverlapping()
        ->runInBackground();
}
