<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// المهام الدورية — تحتاج سطر cron واحدًا على الخادم يعمل كل دقيقة:
// * * * * * php /path/to/app/artisan schedule:run >> /dev/null 2>&1
//
// كل مهمة تعمل داخل نفس عملية PHP (Schedule::call) وليس كعملية منفصلة،
// لأن الاستضافات المشتركة مثل Hostinger تعطّل proc_open التي يحتاجها
// Schedule::command، فتفشل المهام بصمت ولا تُرسل رسائل واتساب.
$run = fn (string $command, array $options = []) => fn () => Artisan::call($command, $options);

Schedule::call($run('qms:close-stale'))->name('qms:close-stale')->dailyAt('00:10');
Schedule::call($run('qms:backup'))->name('qms:backup')->dailyAt('02:30');
Schedule::call($run('qms:whatsapp-retry-stuck'))->name('qms:whatsapp-retry-stuck')->everyTenMinutes();
Schedule::call($run('queue:prune-failed', ['--hours' => 720]))->name('queue:prune-failed')->weekly();

// للاستضافة المشتركة التي لا تدعم Supervisor: المجدول يشغّل عامل الطابور كل دقيقة
// ويبقى يعمل 50 ثانية، فتُرسل رسائل واتساب خلال ثوانٍ لا بعد دقيقة.
// يبقى آخر مهمة حتى لا يؤخر المهام الأخرى، والقفل ينتهي بعد دقيقتين إن توقفت العملية فجأة.
if (config('qms.queue_via_scheduler')) {
    Schedule::call($run('queue:work', ['--max-time' => 50, '--sleep' => 2, '--tries' => 1]))
        ->name('queue:work')
        ->everyMinute()
        ->withoutOverlapping(2);
}
