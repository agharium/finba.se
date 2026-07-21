<?php

namespace App\Services;

use App\Enums\Locale;
use App\Models\User;
use App\Support\Geo\Support\GeoCityResolver;
use App\Support\Geo\Support\GeoPresenter;

class UserPreferencesService
{
    public function __construct(
        private LocationDefaultsService $locationDefaults,
        private GeoCityResolver $geoCityResolver,
        private GeoPresenter $geoPresenter,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function defaultFormState(User $user): array
    {
        $locale = $user->preferredLocale();
        $fallbackCountry = $this->locationDefaults->countryFromLocale($locale);

        $location = $this->geoCityResolver->formStateFromCityId(
            $user->geo_city_id,
            $fallbackCountry,
        );

        if (blank($location['geo_country_code'])) {
            $location['geo_country_code'] = $fallbackCountry;
        }

        return [
            'locale' => $locale,
            ...$location,
            'advanced' => $user->hasAdvancedMode(),
            'tither' => $user->isTither(),
            'accounts_receivable' => $user->hasSetting('accounts_receivable'),
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function buildPreferencePayload(User $user, array $state, bool $requireCity = false): array
    {
        $locale = Locale::fromNullable(
            isset($state['locale']) && is_string($state['locale'])
                ? $state['locale']
                : $user->preferredLocale(),
        )->value;
        $advanced = (bool) ($state['advanced'] ?? false);

        $settings = $user->settings ?? [];
        $settings['locale'] = $locale;
        $settings['advanced'] = $advanced;
        $settings['tither'] = (bool) ($state['tither'] ?? false);
        $settings['accounts_receivable'] = $advanced ? (bool) ($state['accounts_receivable'] ?? false) : false;

        $geoCityId = $this->geoCityResolver->resolveForPersistence($state, $requireCity);

        $payload = [
            'settings' => $settings,
            'locale' => $locale,
            'geo_city_id' => $geoCityId,
        ];

        if ($geoCityId !== null) {
            $timezone = $this->geoCityResolver->timezoneForCity($geoCityId);

            // Suggest IANA timezone from city when the user has none yet.
            if (filled($timezone) && blank($user->timezone)) {
                $payload['timezone'] = $timezone;
            }
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function persistPreferences(User $user, array $state, bool $requireCity = false): void
    {
        $user->update($this->buildPreferencePayload($user, $state, $requireCity));
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function completeOnboarding(User $user, array $state): void
    {
        $this->persistPreferences($user, $state, requireCity: true);

        $user->update([
            'onboarding_completed_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, string>
     */
    public function buildSummary(User $user, array $state): array
    {
        $locale = (string) ($state['locale'] ?? $user->preferredLocale());
        $geoCityId = isset($state['geo_city_id']) && filled($state['geo_city_id'])
            ? (int) $state['geo_city_id']
            : null;

        $location = $this->geoPresenter->fullLabel($geoCityId) ?? '—';

        if ($location === GeoPresenter::UNAVAILABLE || $location === GeoPresenter::MISSING) {
            $location = $location === GeoPresenter::MISSING
                ? 'Localização indisponível no catálogo'
                : 'Localização temporariamente indisponível';
        }

        return [
            'locale' => Locale::fromNullable($locale)->label(),
            'location' => $location,
            'advanced' => ($state['advanced'] ?? false) ? 'Ativado' : 'Desativado',
            'accounts_receivable' => ($state['advanced'] ?? false) && ($state['accounts_receivable'] ?? false)
                ? 'Ativado'
                : 'Desativado',
            'tither' => ($state['tither'] ?? false) ? 'Ativado' : 'Desativado',
        ];
    }
}
