<?php

namespace App\Support\Geo\Exceptions;

use Exception;
use Throwable;

class GeoException extends Exception
{
    public function __construct(
        string $message,
        public readonly ?int $status = null,
        public readonly ?string $endpoint = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status ?? 0, $previous);
    }

    /**
     * @return array{message: string, status: ?int, endpoint: ?string}
     */
    public function context(): array
    {
        return [
            'message' => $this->getMessage(),
            'status' => $this->status,
            'endpoint' => $this->endpoint,
        ];
    }
}
