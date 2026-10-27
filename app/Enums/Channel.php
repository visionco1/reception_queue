<?php

namespace App\Enums;

enum Channel: string
{
    case InPerson = 'in_person';
    case Remote = 'remote';

    public function label(): string
    {
        return match ($this) {
            self::InPerson => __('حضوري'),
            self::Remote => __('عن بُعد'),
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
