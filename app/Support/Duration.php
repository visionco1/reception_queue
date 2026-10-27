<?php

namespace App\Support;

class Duration
{
    /** "8 دقائق"، "دقيقتان"، "ساعة و 5 دقائق" */
    public static function human(?int $seconds): string
    {
        if ($seconds === null) {
            return '—';
        }
        if ($seconds < 60) {
            return __('أقل من دقيقة');
        }

        $minutes = intdiv($seconds, 60);
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        if ($hours === 0) {
            return self::minutes($minutes);
        }

        $h = $hours === 1 ? __('ساعة') : ($hours === 2 ? __('ساعتان') : $hours.' '.__('ساعات'));

        return $rest ? $h.' '.__('و').' '.self::minutes($rest) : $h;
    }

    public static function minutes(int $m): string
    {
        return match (true) {
            $m === 1 => __('دقيقة'),
            $m === 2 => __('دقيقتان'),
            $m >= 3 && $m <= 10 => $m.' '.__('دقائق'),
            default => $m.' '.__('دقيقة'),
        };
    }

    /** صيغة مختصرة للجداول والتقارير: 12:05 */
    public static function clock(?int $seconds): string
    {
        if ($seconds === null) {
            return '—';
        }
        $m = intdiv($seconds, 60);

        return $m >= 60
            ? sprintf('%d:%02d:%02d', intdiv($m, 60), $m % 60, $seconds % 60)
            : sprintf('%d:%02d', $m, $seconds % 60);
    }
}
