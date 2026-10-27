<?php

namespace App\Console\Commands;

use App\Jobs\SendWhatsAppMessage;
use App\Models\WhatsappLog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('qms:whatsapp-retry-stuck')]
#[Description('إعادة جدولة رسائل واتساب العالقة في حالة الانتظار (مثلًا بعد توقف عامل الطابور)')]
class RetryStuckWhatsApp extends Command
{
    public function handle(): int
    {
        $stuck = WhatsappLog::query()
            ->where('status', 'queued')
            ->where('updated_at', '<', now()->subMinutes(10))
            ->where('created_at', '>', now()->subDay())
            ->limit(200)
            ->get();

        foreach ($stuck as $log) {
            $log->touch();
            SendWhatsAppMessage::dispatch($log->id);
        }

        $this->info("Re-dispatched {$stuck->count()} messages.");

        return self::SUCCESS;
    }
}
