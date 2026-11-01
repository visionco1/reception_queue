<?php

namespace App\Enums;

/** نتيجة الخدمة / الصفقة عند إنهائها */
enum Outcome: string
{
    case Completed = 'completed';
    case MissingDocs = 'missing_docs';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Completed => __('مكتملة'),
            self::MissingDocs => __('ناقصة أوراق'),
            self::Cancelled => __('ملغاة'),
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
