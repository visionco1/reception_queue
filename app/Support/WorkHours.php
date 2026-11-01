<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/** ساعات العمل من الإعدادات (فارغة = بدون قيود) */
class WorkHours
{
    public static function configured(): bool
    {
        return self::parse(setting('work_start')) !== null && self::parse(setting('work_end')) !== null;
    }

    public static function isOpen(?Carbon $at = null): bool
    {
        if (! self::configured()) {
            return true;
        }

        $at ??= now();
        $minutes = $at->hour * 60 + $at->minute;
        $start = self::parse(setting('work_start'));
        $end = self::parse(setting('work_end'));

        return $start <= $end
            ? $minutes >= $start && $minutes < $end
            : $minutes >= $start || $minutes < $end; // دوام يمتد بعد منتصف الليل
    }

    /** «8:00 ص – 5:00 م» */
    public static function label(): string
    {
        if (! self::configured()) {
            return '';
        }

        return self::human(setting('work_start')).' – '.self::human(setting('work_end'));
    }

    private static function parse(?string $value): ?int
    {
        if (! $value || ! preg_match('/^(\d{1,2}):(\d{2})$/', trim($value), $m)) {
            return null;
        }

        return ((int) $m[1]) * 60 + (int) $m[2];
    }

    private static function human(string $value): string
    {
        [$h, $m] = array_map('intval', explode(':', $value));
        $suffix = $h < 12 ? __('ص') : __('م');
        $h12 = $h % 12 === 0 ? 12 : $h % 12;

        return $h12.':'.str_pad((string) $m, 2, '0', STR_PAD_LEFT).' '.$suffix;
    }
}
