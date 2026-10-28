<?php

namespace App\Services\Payments;

use Illuminate\Validation\ValidationException;

class MpesaPhone
{
    public static function normalize(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            $digits = '254'.substr($digits, 1);
        }

        if (str_starts_with($digits, '7') && strlen($digits) === 9) {
            $digits = '254'.$digits;
        }

        if (str_starts_with($digits, '1') && strlen($digits) === 9) {
            $digits = '254'.$digits;
        }

        if (! preg_match('/^254(7|1)\d{8}$/', $digits)) {
            throw ValidationException::withMessages([
                'phone' => 'Enter a Safaricom number in the format 2547XXXXXXXX.',
            ]);
        }

        return $digits;
    }
}
