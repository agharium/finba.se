<?php

namespace App\Enums;

enum Locale: string
{
    case English = 'en';
    case PortugueseBrazil = 'pt_BR';
    case Spanish = 'es';

    public static function default(): self
    {
        $configured = config('app.locale', self::English->value);

        return self::normalize(is_string($configured) ? $configured : null)
            ?? self::English;
    }

    public static function fromNullable(?string $locale): self
    {
        return self::normalize($locale) ?? self::default();
    }

    /**
     * Normalize a single language tag into a supported Locale.
     *
     * Accepts aliases such as pt, pt-BR, en-US, es-MX.
     * Returns null for unsupported values.
     */
    public static function normalize(?string $locale): ?self
    {
        if (! is_string($locale) || trim($locale) === '') {
            return null;
        }

        $tag = trim(explode(',', $locale, 2)[0]);
        $tag = trim(explode(';', $tag, 2)[0]);

        if ($tag === '') {
            return null;
        }

        $normalized = str_replace('-', '_', $tag);
        $lower = strtolower($normalized);
        $prefix = explode('_', $lower)[0] ?? '';

        return match (true) {
            $lower === 'pt_br', $prefix === 'pt' => self::PortugueseBrazil,
            $lower === 'en', str_starts_with($lower, 'en_') => self::English,
            $lower === 'es', str_starts_with($lower, 'es_') => self::Spanish,
            default => null,
        };
    }

    /**
     * Resolve a browser language string (navigator.language or Accept-Language).
     */
    public static function detectBrowserLocale(?string $raw): self
    {
        if (! is_string($raw) || trim($raw) === '') {
            return self::default();
        }

        foreach (preg_split('/\s*,\s*/', $raw) ?: [] as $part) {
            $tag = trim(explode(';', $part, 2)[0]);

            if ($locale = self::normalize($tag)) {
                return $locale;
            }
        }

        return self::default();
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $locale): array => [$locale->value => $locale->label()])
            ->all();
    }

    public function label(): string
    {
        return match ($this) {
            self::English => 'English',
            self::PortugueseBrazil => 'Português',
            self::Spanish => 'Español',
        };
    }

    public function carbonLocale(): string
    {
        return match ($this) {
            self::English => 'en',
            self::PortugueseBrazil => 'pt_BR',
            self::Spanish => 'es',
        };
    }

    public function icuLocale(): string
    {
        return match ($this) {
            self::English => 'en_US',
            self::PortugueseBrazil => 'pt_BR',
            self::Spanish => 'es_ES',
        };
    }

    public function htmlLocale(): string
    {
        return match ($this) {
            self::English => 'en',
            self::PortugueseBrazil => 'pt-BR',
            self::Spanish => 'es',
        };
    }

    public function browserLocale(): string
    {
        return match ($this) {
            self::English => 'en-US',
            self::PortugueseBrazil => 'pt-BR',
            self::Spanish => 'es-ES',
        };
    }

    /**
     * Ready-to-send Accept-Language header value (no header name).
     */
    public function acceptLanguageHeader(): string
    {
        return match ($this) {
            self::English => $this->browserLocale().',en;q=0.9',
            self::PortugueseBrazil => $this->browserLocale().',pt;q=0.9',
            self::Spanish => $this->browserLocale().',es;q=0.9',
        };
    }

    public function fakerLocale(): string
    {
        return match ($this) {
            self::English => 'en_US',
            self::PortugueseBrazil => 'pt_BR',
            self::Spanish => 'es_ES',
        };
    }

    /**
     * Carbon translatedFormat pattern for long calendar dates.
     */
    public function longDateFormat(): string
    {
        return match ($this) {
            self::English => 'F j, Y',
            self::PortugueseBrazil, self::Spanish => 'd \d\e F \d\e Y',
        };
    }
}
