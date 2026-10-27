<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsappLog;

interface WhatsAppDriver
{
    /**
     * إرسال الرسالة وإرجاع معرّف الرسالة لدى المزود.
     *
     * @throws WhatsAppException عند فشل الإرسال
     */
    public function send(WhatsappLog $log): ?string;

    public function name(): string;
}
