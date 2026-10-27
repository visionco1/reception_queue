<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Supervisor = 'supervisor';
    case Employee = 'employee';

    public function label(): string
    {
        return match ($this) {
            self::Admin => __('مدير النظام'),
            self::Supervisor => __('مشرف'),
            self::Employee => __('موظف'),
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
