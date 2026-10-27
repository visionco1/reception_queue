<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * الإعدادات القابلة للتعديل من لوحة الإدارة.
 * القيم المحفوظة في قاعدة البيانات تتقدم على القيم الافتراضية في config/qms.php
 * ويُحدَّد نوع كل قيمة (نص/رقم/منطقي) حسب نوع قيمتها الافتراضية.
 */
class Settings
{
    private const CACHE_KEY = 'qms.settings.v1';

    private ?array $loaded = null;

    public function defaults(): array
    {
        return config('qms.defaults', []);
    }

    public function all(): array
    {
        if ($this->loaded !== null) {
            return $this->loaded;
        }

        $stored = Cache::rememberForever(self::CACHE_KEY, function () {
            try {
                return Setting::query()->pluck('value', 'key')->all();
            } catch (\Throwable) {
                return [];
            }
        });

        $values = [];
        foreach ($this->defaults() as $key => $default) {
            $values[$key] = array_key_exists($key, $stored) ? $this->cast($stored[$key], $default) : $default;
        }

        return $this->loaded = $values;
    }

    public function get(string $key, mixed $fallback = null): mixed
    {
        return $this->all()[$key] ?? $fallback;
    }

    /** حفظ مجموعة إعدادات وإرجاع المفاتيح التي تغيّرت فعليًا */
    public function set(array $values): array
    {
        $current = $this->all();
        $changed = [];

        foreach ($values as $key => $value) {
            if (! array_key_exists($key, $this->defaults())) {
                continue;
            }
            $stringValue = is_bool($value) ? ($value ? '1' : '0') : ($value === null ? null : (string) $value);
            $oldString = is_bool($current[$key]) ? ($current[$key] ? '1' : '0') : ($current[$key] === null ? null : (string) $current[$key]);

            if ($stringValue !== $oldString) {
                $changed[] = $key;
            }
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $stringValue]);
        }

        $this->flush();

        return $changed;
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->loaded = null;
    }

    /** رقم إصدار يتغير مع أي تعديل — تستخدمه شاشة العرض لإعادة التحميل تلقائيًا */
    public function version(): string
    {
        return substr(md5(json_encode($this->all())), 0, 10);
    }

    private function cast(mixed $value, mixed $default): mixed
    {
        return match (true) {
            is_bool($default) => in_array((string) $value, ['1', 'true', 'on', 'yes'], true),
            is_int($default) => (int) $value,
            default => $value,
        };
    }
}
