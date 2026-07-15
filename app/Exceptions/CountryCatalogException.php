<?php

namespace App\Exceptions;

use RuntimeException;

class CountryCatalogException extends RuntimeException
{
    public static function missingSource(string $path): self
    {
        return new self("Country catalog JSON is missing at [{$path}].");
    }

    public static function invalidSource(string $path, string $reason): self
    {
        return new self("Country catalog JSON at [{$path}] is invalid: {$reason}");
    }
}
