<?php

namespace App\Support\Geo\Exceptions;

use Throwable;

class GeoNotFoundException extends GeoException
{
    public function __construct(
        string $message = 'Geo resource not found.',
        ?string $endpoint = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 404, $endpoint, $previous);
    }
}
