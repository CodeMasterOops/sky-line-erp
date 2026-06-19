<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A Nepal PAN/VAT number is exactly 9 digits (IRD requirement). Used on party
 * records and to flag malformed PANs in the VAT registers before IRD export.
 */
class NepaliPan implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! self::isValid((string) $value)) {
            $fail(__('The :attribute must be a valid 9-digit PAN number.', [
                'attribute' => str_replace('_', ' ', $attribute),
            ]));
        }
    }

    public static function isValid(?string $pan): bool
    {
        return $pan !== null && preg_match('/^\d{9}$/', trim($pan)) === 1;
    }
}
