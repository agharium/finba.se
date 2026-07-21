<?php

namespace App\Support\Geo;

use App\Support\Geo\Exceptions\GeoAuthenticationException;
use App\Support\Geo\Exceptions\GeoConnectionException;
use App\Support\Geo\Exceptions\GeoException;
use App\Support\Geo\Exceptions\GeoNotFoundException;
use App\Support\Geo\Exceptions\GeoRateLimitException;
use App\Support\Geo\Exceptions\GeoResponseException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class GeoClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $apiKey,
        private readonly int $timeout,
        private readonly int $connectTimeout,
        private readonly int $retryAttempts,
        private readonly int $retryDelay,
        private readonly string $userAgent,
    ) {}

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>|list<mixed>
     */
    public function get(string $path, array $query = []): array
    {
        $endpoint = '/'.ltrim($path, '/');

        try {
            $response = $this->request()
                ->get($endpoint, $query);

            if ($response->failed()) {
                throw $this->mapFailedResponse($response, $endpoint);
            }

            $payload = $response->json();

            if (! is_array($payload)) {
                throw new GeoResponseException(
                    'Geo service returned a non-JSON object/array payload.',
                    $response->status(),
                    $endpoint,
                );
            }

            return $payload;
        } catch (GeoException $exception) {
            throw $exception;
        } catch (ConnectionException $exception) {
            throw new GeoConnectionException(
                'Geo service connection failed: '.$exception->getMessage(),
                $endpoint,
                $exception,
            );
        } catch (RequestException $exception) {
            throw $this->mapFailedResponse($exception->response, $endpoint, $exception);
        } catch (Throwable $exception) {
            throw new GeoException(
                'Geo service unexpected error: '.$exception->getMessage(),
                null,
                $endpoint,
                $exception,
            );
        }
    }

    private function request(): PendingRequest
    {
        $request = Http::baseUrl($this->baseUrl)
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeout)
            ->connectTimeout($this->connectTimeout)
            ->withHeaders([
                'User-Agent' => $this->userAgent,
            ]);

        if (filled($this->apiKey)) {
            $request = $request->withToken($this->apiKey);
        }

        if ($this->retryAttempts > 0) {
            $request = $request->retry(
                $this->retryAttempts,
                $this->retryDelay,
                function (Throwable $exception): bool {
                    if ($exception instanceof ConnectionException) {
                        return true;
                    }

                    return $exception instanceof RequestException
                        && $exception->response->serverError();
                },
                throw: false,
            );
        }

        return $request;
    }

    private function mapFailedResponse(Response $response, string $endpoint, ?Throwable $previous = null): GeoException
    {
        $status = $response->status();
        $message = $this->errorMessage($response) ?? "Geo service request failed with HTTP {$status}.";

        return match (true) {
            $status === 401 => new GeoAuthenticationException($message, $endpoint, $previous),
            $status === 404 => new GeoNotFoundException($message, $endpoint, $previous),
            $status === 429 => new GeoRateLimitException(
                $message,
                $this->retryAfterSeconds($response),
                $endpoint,
                $previous,
            ),
            default => new GeoException($message, $status, $endpoint, $previous),
        };
    }

    private function errorMessage(Response $response): ?string
    {
        $payload = $response->json();

        if (! is_array($payload)) {
            return null;
        }

        $error = $payload['error'] ?? null;

        if (is_array($error) && isset($error['message']) && is_string($error['message'])) {
            return $error['message'];
        }

        return null;
    }

    private function retryAfterSeconds(Response $response): ?int
    {
        $header = $response->header('Retry-After');

        if (is_numeric($header)) {
            return max(1, (int) $header);
        }

        $payload = $response->json();

        if (is_array($payload)
            && isset($payload['error'])
            && is_array($payload['error'])
            && isset($payload['error']['retryAfter'])
            && is_numeric($payload['error']['retryAfter'])) {
            return max(1, (int) $payload['error']['retryAfter']);
        }

        return null;
    }
}
