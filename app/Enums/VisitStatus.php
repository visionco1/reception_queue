<?php

namespace App\Enums;

enum VisitStatus: string
{
    case Waiting = 'waiting';
    case Called = 'called';
    case InService = 'in_service';
    case Completed = 'completed';
    case NoShow = 'no_show';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Waiting => __('انتظار'),
            self::Called => __('تم الاستدعاء'),
            self::InService => __('قيد الخدمة'),
            self::Completed => __('انتهت الخدمة'),
            self::NoShow => __('تم التخطي / لم يحضر'),
            self::Cancelled => __('ملغي'),
        };
    }

    /** الحالات التي يكون فيها العميل مع موظف الآن */
    public static function active(): array
    {
        return [self::Called->value, self::InService->value];
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Completed, self::NoShow, self::Cancelled], true);
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
