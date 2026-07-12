<?php

namespace App\Support;

use Illuminate\Support\Str;

class LocationNameNormalizer
{
    public static function normalize(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        $normalized = preg_replace('/\s+/u', ' ', trim($name));

        return $normalized === '' ? null : $normalized;
    }

    public static function normalizeForComparison(?string $name): ?string
    {
        $normalized = self::normalize($name);

        if ($normalized === null) {
            return null;
        }

        return Str::lower(Str::ascii($normalized));
    }
}
