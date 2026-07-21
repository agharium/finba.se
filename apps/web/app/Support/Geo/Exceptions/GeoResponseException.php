<?php

namespace App\Support\Geo\Exceptions;

use Throwable;

class GeoResponseException extends GeoException
{
    public function __construct(
        string $message = 'Geo service returned an unexpected response.',
        ?int $status = null,
        ?string $endpoint = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $endpoint, $previous);
    }
}
