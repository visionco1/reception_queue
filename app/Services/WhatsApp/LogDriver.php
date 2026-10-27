<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsappLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** وضع التجربة: يسجل الرسالة في سجل النظام دون إرسال فعلي */
class LogDriver implements WhatsAppDriver
{
    public function send(WhatsappLog $log): ?string
    {
        Log::info('[WhatsApp:log] '.$log->to_phone.' :: '.$log->body);

        return 'log-'.Str::lower(Str::random(12));
    }

    public function name(): string
    {
        return 'log';
    }
}
