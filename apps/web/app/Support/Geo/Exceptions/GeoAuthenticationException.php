<?php

namespace App\Support\Geo\Exceptions;

use Throwable;

class GeoAuthenticationException extends GeoException
{
    public function __construct(
        string $message = 'Geo service authentication failed.',
        ?string $endpoint = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 401, $endpoint, $previous);
    }
}
