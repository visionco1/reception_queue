<?php

namespace App\Enums;

enum ClientSource: string
{
    case WhatsApp = 'whatsapp';
    case Call = 'call';
    case Referral = 'referral';
    case Website = 'website';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::WhatsApp => __('واتساب'),
            self::Call => __('اتصال'),
            self::Referral => __('إحالة'),
            self::Website => __('الموقع'),
            self::Other => __('غيره'),
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
