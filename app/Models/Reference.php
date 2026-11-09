<?php

namespace App\Models;

use Illuminate\Support\Str;

/** Générateur de références lisibles (WD-7K3P9Q, CLI-…, PAY-…). */
final class Reference
{
    private const ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ'; // sans 0/O/1/I

    public static function next(string $prefix): string
    {
        $code = '';
        for ($i = 0; $i < 7; $i++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return Str::upper($prefix).'-'.$code;
    }
}
