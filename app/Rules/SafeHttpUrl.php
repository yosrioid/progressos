<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SafeHttpUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! filter_var($value, FILTER_VALIDATE_URL)) {
            $fail('The :attribute must be a valid URL.');
            return;
        }

        $parts = parse_url($value);
        $scheme = strtolower($parts['scheme'] ?? '');
        if (! in_array($scheme, ['http', 'https'], true)) {
            $fail('The :attribute must use http or https.');
            return;
        }

        $host = $parts['host'] ?? '';
        if ($host === '' || in_array(strtolower($host), ['localhost'], true) || str_ends_with(strtolower($host), '.local')) {
            $fail('The :attribute cannot point to a local host.');
            return;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) === false) {
            return;
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            $fail('The :attribute cannot point to a private or reserved address.');
        }
    }
}
