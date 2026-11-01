<?php

namespace App\Enums;

/** تفصيل «أخرى» في الآيباد: العميل ليس عن طريق بنك */
enum ViaDetail: string
{
    case Company = 'company';
    case Individuals = 'individuals';

    public function label(): string
    {
        return match ($this) {
            self::Company => __('شركة عقارية'),
            self::Individuals => __('أفراد'),
        };
    }

    public function labelEn(): string
    {
        return match ($this) {
            self::Company => 'Real-estate company',
            self::Individuals => 'Individuals',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
