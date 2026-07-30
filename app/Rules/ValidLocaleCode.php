<?php

namespace App\Rules;

use App\Support\Localization\LocaleCode;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidLocaleCode implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! LocaleCode::isValid($value)) {
            $fail('Mã ngôn ngữ phải là locale BCP 47 hợp lệ, ví dụ vi, en-US hoặc zh-Hant-HK.');
        }
    }
}
