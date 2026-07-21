<?php

namespace App\Enums;

enum ChangelogVisibility: string
{
    case Public = 'public';
    case Authenticated = 'authenticated';
    case Internal = 'internal';

    public static function fromMixed(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            return self::tryFrom(strtolower($value)) ?? self::Public;
        }

        return self::Public;
    }

    public function isVisibleToPublic(): bool
    {
        return $this === self::Public;
    }

    public function isVisibleToAuthenticated(): bool
    {
        return true;
    }
}
