<?php

namespace App\Exceptions;

use RuntimeException;

/** خطأ متوقع في عمليات الطابور تُعرض رسالته للمستخدم كما هي */
class QueueException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }

    public static function alreadyTaken(): self
    {
        return new self(__('أخذ موظف آخر هذا العميل للتو. اختر عميلًا آخر من القائمة.'), 409);
    }
}
