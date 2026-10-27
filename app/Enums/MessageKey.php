<?php

namespace App\Enums;

/** أنواع رسائل واتساب في رحلة العميل الحضوري */
enum MessageKey: string
{
    case Registered = 'registered';
    case NearTurn = 'near_turn';
    case Called = 'called';
    case Thanks = 'thanks';
    case Test = 'test';

    public function label(): string
    {
        return match ($this) {
            self::Registered => __('بعد التسجيل'),
            self::NearTurn => __('عند اقتراب الدور'),
            self::Called => __('عند الاستدعاء'),
            self::Thanks => __('بعد إنهاء الخدمة'),
            self::Test => __('رسالة تجريبية'),
        };
    }

    /** الرسائل القابلة للتعديل من لوحة الإدارة */
    public static function journey(): array
    {
        return [self::Registered, self::NearTurn, self::Called, self::Thanks];
    }
}
