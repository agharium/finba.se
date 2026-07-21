<?php

namespace App\Support\Geo\Exceptions;

use Throwable;

class GeoRateLimitException extends GeoException
{
    public function __construct(
        string $message = 'Geo service rate limit exceeded.',
        public readonly ?int $retryAfter = null,
        ?string $endpoint = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 429, $endpoint, $previous);
    }

    /**
     * @return array{message: string, status: ?int, endpoint: ?string, retry_after: ?int}
     */
    public function context(): array
    {
        return [
            ...parent::context(),
            'retry_after' => $this->retryAfter,
        ];
    }
}
