<?php

namespace App\Rules;

use App\Support\Phone;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Mobile implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || Phone::normalize($value) === null) {
            $fail(Phone::countryCode() === '966'
                ? __('رقم الجوال غير صحيح. اكتبه بالصيغة 05XXXXXXXX')
                : __('رقم الجوال غير صحيح.'));
        }
    }
}
