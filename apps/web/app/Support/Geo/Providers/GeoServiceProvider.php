<?php

namespace App\Support\Geo\Providers;

use App\Support\Geo\Contracts\GeoContract;
use App\Support\Geo\Facades\Geo as GeoFacade;
use App\Support\Geo\GeoClient;
use App\Support\Geo\GeoManager;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\ServiceProvider;

class GeoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GeoClient::class, function (): GeoClient {
            return new GeoClient(
                baseUrl: (string) config('geo.base_url'),
                apiKey: config('geo.internal_api_key'),
                timeout: (int) config('geo.timeout'),
                connectTimeout: (int) config('geo.connect_timeout'),
                retryAttempts: (int) config('geo.retry_attempts'),
                retryDelay: (int) config('geo.retry_delay'),
                userAgent: (string) config('geo.user_agent'),
            );
        });

        // Scoped so request memoization on GeoManager does not grow across worker jobs.
        $this->app->scoped(GeoContract::class, function ($app): GeoManager {
            /** @var array{countries: int, country: int, regions: int, region: int, city: int} $ttl */
            $ttl = config('geo.cache_ttl', []);

            return new GeoManager(
                client: $app->make(GeoClient::class),
                cacheEnabled: (bool) config('geo.cache'),
                cacheTtl: [
                    'countries' => (int) ($ttl['countries'] ?? 86400),
                    'country' => (int) ($ttl['country'] ?? 86400),
                    'regions' => (int) ($ttl['regions'] ?? 86400),
                    'region' => (int) ($ttl['region'] ?? 86400),
                    'city' => (int) ($ttl['city'] ?? 86400),
                ],
                cacheStore: config('geo.cache_store'),
            );
        });

        $this->app->alias(GeoContract::class, 'geo');
    }

    public function boot(): void
    {
        AliasLoader::getInstance()->alias('Geo', GeoFacade::class);
    }
}
