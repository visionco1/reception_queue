<?php

namespace App\Support;

/**
 * توحيد أرقام الجوال: تُحفظ بصيغة دولية بدون + (مثل 966512345678)
 * وتقبل المدخلات المحلية (05...) والأرقام العربية (٠٥...) والمسافات والرموز.
 */
class Phone
{
    public static function countryCode(): string
    {
        return (string) setting('country_code', '966');
    }

    public static function toAsciiDigits(string $value): string
    {
        return strtr($value, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);
    }

    public static function normalize(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $value = trim(self::toAsciiDigits($input));
        $hasPlus = str_starts_with($value, '+');
        $digits = preg_replace('/\D+/', '', $value);

        if ($digits === '') {
            return null;
        }

        $cc = self::countryCode();

        if ($hasPlus) {
            $number = $digits;
        } elseif (str_starts_with($digits, '00')) {
            $number = substr($digits, 2);
        } elseif (str_starts_with($digits, $cc) && strlen($digits) > strlen($cc) + 7) {
            $number = $digits;
        } elseif (str_starts_with($digits, '0')) {
            $number = $cc.substr($digits, 1);
        } else {
            $number = $cc.$digits;
        }

        return self::isValid($number) ? $number : null;
    }

    public static function isValid(string $number): bool
    {
        // جوال سعودي: 9665XXXXXXXX
        if (str_starts_with($number, '966')) {
            return (bool) preg_match('/^9665\d{8}$/', $number);
        }

        return (bool) preg_match('/^\d{8,15}$/', $number);
    }

    /** عرض الرقم بالصيغة المحلية المألوفة: 050 123 4567 */
    public static function format(?string $number): string
    {
        if (! $number) {
            return '';
        }

        $cc = self::countryCode();
        if (str_starts_with($number, $cc)) {
            $local = '0'.substr($number, strlen($cc));
            if (strlen($local) === 10) {
                return substr($local, 0, 3).' '.substr($local, 3, 3).' '.substr($local, 6);
            }

            return $local;
        }

        return '+'.$number;
    }

    /** إخفاء جزء من الرقم عند عرضه على شاشة عامة */
    public static function mask(?string $number): string
    {
        $formatted = self::format($number);
        $len = mb_strlen($formatted);
        if ($len < 7) {
            return $formatted;
        }

        $out = '';
        $digitsSeen = 0;
        $totalDigits = preg_match_all('/\d/', $formatted);
        foreach (mb_str_split($formatted) as $ch) {
            if (ctype_digit($ch)) {
                $digitsSeen++;
                $out .= ($digitsSeen > 2 && $digitsSeen <= $totalDigits - 4) ? '•' : $ch;
            } else {
                $out .= $ch;
            }
        }

        return $out;
    }
}
