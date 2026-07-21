<?php

namespace App\Support\Geo\Exceptions;

use Throwable;

class GeoConnectionException extends GeoException
{
    public function __construct(
        string $message = 'Geo service connection failed.',
        ?string $endpoint = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, null, $endpoint, $previous);
    }
}
