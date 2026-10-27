<?php

namespace App\Services\WhatsApp;

use RuntimeException;

class WhatsAppException extends RuntimeException
{
    /** أخطاء دائمة لا تفيد معها إعادة المحاولة (رقم غير صالح، قالب غير معتمد...) */
    public function __construct(string $message, public readonly bool $permanent = false)
    {
        parent::__construct($message);
    }
}
