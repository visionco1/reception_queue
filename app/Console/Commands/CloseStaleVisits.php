<?php

namespace App\Console\Commands;

use App\Enums\Channel;
use App\Enums\VisitStatus;
use App\Models\Visit;
use App\Services\QueueService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('qms:close-stale')]
#[Description('إغلاق تذاكر الأيام السابقة التي بقيت مفتوحة (يعمل يوميًا بعد منتصف الليل)')]
class CloseStaleVisits extends Command
{
    public function handle(QueueService $queue): int
    {
        $closed = 0;

        Visit::query()
            ->where('channel', Channel::InPerson)
            ->whereIn('status', [VisitStatus::Waiting->value, ...VisitStatus::active()])
            ->where('created_at', '<', today())
            ->with('sessions')
            ->each(function (Visit $visit) use ($queue, &$closed) {
                $from = $visit->status;
                $to = $from === VisitStatus::Waiting ? VisitStatus::Cancelled : VisitStatus::Completed;

                $visit->sessions->whereNull('ended_at')->each->update(['ended_at' => now(), 'end_status' => 'auto_closed']);
                // مدة الخدمة غير معروفة فلا تُحسب ضمن المتوسطات
                $visit->update(['status' => $to, 'completed_at' => now()]);
                $queue->logStatus($visit, $from, $to, null, __('إغلاق تلقائي نهاية اليوم'));
                $closed++;
            });

        $this->info("Closed {$closed} stale visits.");

        return self::SUCCESS;
    }
}
