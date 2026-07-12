<?php

namespace App\Services;

use App\Models\City;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class UserPreferencesService
{
    public function __construct(
        private LocationDefaultsService $locationDefaults,
        private LocationCatalogService $locationCatalog,
        private UserCityService $userCityService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function defaultFormState(User $user): array
    {
        $locale = $this->locationDefaults->getLocale($user);

        return [
            'locale' => $locale,
            'default_country_code' => $user->default_country_code ?? $this->locationDefaults->countryFromLocale($locale),
            'default_region_code' => $user->default_region_code,
            'default_city_id' => $user->default_city_id,
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
        $locale = (string) ($state['locale'] ?? $this->locationDefaults->getLocale($user));
        $advanced = (bool) ($state['advanced'] ?? false);
        $regionCode = $state['default_region_code'] ?? null;

        if (blank($regionCode)) {
            throw ValidationException::withMessages([
                'default_region_code' => 'Selecione o estado ou região.',
            ]);
        }

        $settings = $user->settings ?? [];
        $settings['locale'] = $locale;
        $settings['advanced'] = $advanced;
        $settings['tither'] = (bool) ($state['tither'] ?? false);
        $settings['accounts_receivable'] = $advanced ? (bool) ($state['accounts_receivable'] ?? false) : false;

        $countryCode = $this->locationDefaults->resolveCountryForSave(
            $user,
            $locale,
            $state['default_country_code'] ?? null,
        );

        $defaultCityId = $this->resolveDefaultCityId(
            $user,
            $countryCode,
            $regionCode,
            $state['default_city_id'] ?? null,
            $requireCity,
        );

        return [
            'settings' => $settings,
            'default_country_code' => $countryCode,
            'default_region_code' => $regionCode,
            'default_city_id' => $defaultCityId,
        ];
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
        $locale = (string) ($state['locale'] ?? $this->locationDefaults->getLocale($user));
        $countryCode = $state['default_country_code']
            ?? $user->default_country_code
            ?? $this->locationDefaults->countryFromLocale($locale);
        $regionCode = (string) ($state['default_region_code'] ?? '');
        $regionLabel = $this->locationCatalog->regionOptions($countryCode)[$regionCode] ?? $regionCode;

        return [
            'locale' => LocationDefaultsService::SUPPORTED_LOCALES[$locale] ?? $locale,
            'location' => filled($regionLabel)
                ? trim($regionLabel . ($this->citySummaryLabel($user, $state, $countryCode, $regionCode) ? ' · ' . $this->citySummaryLabel($user, $state, $countryCode, $regionCode) : ''))
                : '—',
            'advanced' => ($state['advanced'] ?? false) ? 'Ativado' : 'Desativado',
            'accounts_receivable' => ($state['advanced'] ?? false) && ($state['accounts_receivable'] ?? false)
                ? 'Ativado'
                : 'Desativado',
            'tither' => ($state['tither'] ?? false) ? 'Ativado' : 'Desativado',
        ];
    }

    private function resolveDefaultCityId(
        User $user,
        ?string $countryCode,
        ?string $regionCode,
        mixed $cityValue,
        bool $required,
    ): ?string {
        if (blank($cityValue)) {
            if ($required) {
                throw ValidationException::withMessages([
                    'default_city_id' => 'Selecione a cidade.',
                ]);
            }

            return null;
        }

        $resolved = $this->userCityService->resolveCatalogSelection(
            $user,
            $countryCode,
            $regionCode,
            is_string($cityValue) ? $cityValue : null,
        );

        if ($required && blank($resolved)) {
            throw ValidationException::withMessages([
                'default_city_id' => 'Selecione uma cidade válida.',
            ]);
        }

        return $resolved;
    }

    private function citySummaryLabel(User $user, array $state, ?string $countryCode, ?string $regionCode): ?string
    {
        $cityValue = $state['default_city_id'] ?? null;

        if (blank($cityValue)) {
            return null;
        }

        return $this->userCityService->optionLabel(
            is_string($cityValue) ? $cityValue : null,
            $regionCode,
        );
    }
}
