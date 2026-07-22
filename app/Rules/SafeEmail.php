<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SafeEmail implements ValidationRule
{
    // ponytail: ASCII-only addresses contain Laravel 10 CRLF risk; upgrade to Laravel 12.61.1+ before relaxing.
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)
            || strlen($value) > 254
            || preg_match('/[^\x21-\x7E]/', $value)
            || ! filter_var($value, FILTER_VALIDATE_EMAIL)
        ) {
            $fail('Alamat email tidak valid.');
        }
    }
}
