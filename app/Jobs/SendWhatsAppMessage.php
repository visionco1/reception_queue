<?php

namespace App\Jobs;

use App\Models\WhatsappLog;
use App\Services\WhatsApp\WhatsAppException;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * يرسل رسالة واتساب واحدة ويحدّث حالتها. لا يرمي استثناءات أبدًا حتى لا
 * يتأثر تدفق الاستقبال عند تعطل المزود؛ يعيد المحاولة بمهلة متزايدة ثم يسجل الفشل.
 */
class SendWhatsAppMessage implements ShouldQueue
{
    use Queueable;

    public int $tries;

    public function __construct(public int $logId)
    {
        $this->tries = (int) config('qms.whatsapp.tries', 3) + 1;
    }

    public function handle(WhatsAppService $whatsapp): void
    {
        $log = WhatsappLog::find($this->logId);
        if (! $log || $log->status !== 'queued') {
            return;
        }

        $maxAttempts = (int) config('qms.whatsapp.tries', 3);
        $log->increment('attempts');

        try {
            $messageId = $whatsapp->driver()->send($log);

            $log->update([
                'status' => 'sent',
                'provider_message_id' => $messageId,
                'sent_at' => now(),
                'error' => null,
            ]);
        } catch (Throwable $e) {
            $permanent = $e instanceof WhatsAppException && $e->permanent;
            $final = $permanent || $log->attempts >= $maxAttempts;

            $log->update([
                'error' => mb_substr($e->getMessage(), 0, 1000),
                'status' => $final ? 'failed' : 'queued',
                'failed_at' => $final ? now() : null,
            ]);

            Log::warning('WhatsApp send failed', ['log' => $log->id, 'error' => $e->getMessage(), 'final' => $final]);

            if (! $final) {
                $this->release([10, 60, 300][min($log->attempts - 1, 2)]);
            }
        }
    }
}
