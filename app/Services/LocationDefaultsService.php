<?php

namespace App\Services;

use App\Models\User;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Http\Request;

class LocationDefaultsService
{
    public const SUPPORTED_LOCALES = [
        'pt-BR' => 'Português (Brasil)',
        'en' => 'English',
    ];

    public function hasConfiguredLocation(?User $user): bool
    {
        return filled($this->internalCountryCode($user))
            && filled($user?->default_region_code);
    }

    public function getLocale(User $user): string
    {
        $locale = $user->settings['locale'] ?? null;

        if (is_string($locale) && array_key_exists($locale, self::SUPPORTED_LOCALES)) {
            return $locale;
        }

        return $this->inferLocale();
    }

    public function inferLocale(?Request $request = null): string
    {
        $request ??= request();

        $preferred = $request->getPreferredLanguage(array_keys(self::SUPPORTED_LOCALES));

        if (is_string($preferred) && array_key_exists($preferred, self::SUPPORTED_LOCALES)) {
            return $preferred;
        }

        if (str_starts_with((string) $preferred, 'pt')) {
            return 'pt-BR';
        }

        return 'en';
    }

    public function countryFromLocale(string $locale): ?string
    {
        return match ($locale) {
            'pt-BR' => 'BR',
            default => null,
        };
    }

    public function internalCountryCode(?User $user): ?string
    {
        return $user?->default_country_code;
    }

    public function resolveCountryForSave(User $user, string $locale, ?string $formCountryCode): ?string
    {
        if (filled($user->default_country_code)) {
            return $user->default_country_code;
        }

        if (filled($formCountryCode)) {
            return $formCountryCode;
        }

        return $this->countryFromLocale($locale);
    }

    /**
     * @return array{country_code: ?string, region_code: ?string}
     */
    public function searchContextFromFormState(Get $get): array
    {
        if ($get('search_other_region')) {
            return [
                'country_code' => $this->internalCountryCode(auth()->user()),
                'region_code' => $get('temporary_region_code'),
            ];
        }

        $user = auth()->user();

        return [
            'country_code' => $this->internalCountryCode($user),
            'region_code' => $user?->default_region_code,
        ];
    }

    public function canSearchCities(Get $get): bool
    {
        if ($get('search_other_region')) {
            $context = $this->searchContextFromFormState($get);

            return filled($context['country_code']) && filled($context['region_code']);
        }

        return $this->hasConfiguredLocation(auth()->user());
    }

    public function defaultCityIdForCreate(?User $user): ?string
    {
        return $user?->default_city_id;
    }
}
