<?php

namespace App\Services;

use App\Enums\Locale;
use App\Models\User;
use App\Support\Geo\Support\GeoPresenter;
use Illuminate\Http\Request;

class LocationDefaultsService
{
    public function __construct(
        private GeoPresenter $geoPresenter,
    ) {}

    public function hasConfiguredLocation(?User $user): bool
    {
        return filled($user?->geo_city_id);
    }

    public function getLocale(User $user): string
    {
        return $user->preferredLocale();
    }

    public function inferLocale(?Request $request = null): string
    {
        $request ??= request();

        return Locale::detectBrowserLocale($request->header('Accept-Language'))->value;
    }

    public function countryFromLocale(string $locale): ?string
    {
        return match (Locale::fromNullable($locale)) {
            Locale::PortugueseBrazil => 'BR',
            default => null,
        };
    }

    public function internalCountryCode(?User $user): ?string
    {
        if (! $user?->geo_city_id) {
            return null;
        }

        return $this->geoPresenter->detail($user->geo_city_id)?->country->code;
    }

    public function regionCode(?User $user): ?string
    {
        if (! $user?->geo_city_id) {
            return null;
        }

        return $this->geoPresenter->detail($user->geo_city_id)?->region?->code;
    }

    /**
     * Resolve country for form cascading before a city exists.
     */
    public function resolveCountryForForm(User $user, string $locale, ?string $formCountryCode): ?string
    {
        if (filled($formCountryCode)) {
            return $formCountryCode;
        }

        if ($country = $this->internalCountryCode($user)) {
            return $country;
        }

        return $this->countryFromLocale($locale);
    }

    public function cityIdForCreate(?User $user): ?int
    {
        return $user?->geo_city_id;
    }
}
