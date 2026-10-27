<?php

namespace App\Enums;

enum CustomerType: string
{
    case Buyer = 'buyer';
    case Seller = 'seller';
    // نوع آخر (يحدده الموظف من قائمة أنواع العملاء) — للعملاء عن بعد فقط، بدون رقم انتظار
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Buyer => __('مشتري'),
            self::Seller => __('بائع'),
            self::Other => __('أخرى'),
        };
    }

    /** الأنواع التي تأخذ رقم انتظار من الآيباد وتدخل في صفقة */
    public static function queueTypes(): array
    {
        return [self::Buyer, self::Seller];
    }

    public function isParty(): bool
    {
        return $this !== self::Other;
    }

    public function opposite(): ?self
    {
        return match ($this) {
            self::Buyer => self::Seller,
            self::Seller => self::Buyer,
            self::Other => null,
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }

    public static function queueOptions(): array
    {
        return collect(self::queueTypes())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
