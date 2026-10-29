<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class ContactPhone implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^\+?([0-9(][0-9 ().-]*?)(?: *(?:ext\.?|x|#) *[0-9]{1,6})?$/iD', $value, $matches)) {
            $fail('Introduce un teléfono válido, por ejemplo +1 (809) 555-0123.');

            return;
        }
        $digits = preg_replace('/\D/', '', $matches[1]);
        if (strlen($digits) < 7 || strlen($digits) > 15) {
            $fail('El teléfono debe contener entre 7 y 15 dígitos, sin contar la extensión.');
        }
    }
}
