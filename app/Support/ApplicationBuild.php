<?php

namespace App\Support;

final class ApplicationBuild
{
    public static function version(): string
    {
        $version = config('finba.app_version');

        return filled($version) ? (string) $version : 'dev';
    }

    /**
     * User-facing version string (always prefixed with "v" except for "dev").
     */
    public static function displayVersion(): string
    {
        $version = self::version();

        if ($version === 'dev' || str_starts_with($version, 'v')) {
            return $version;
        }

        return 'v'.$version;
    }

    public static function stage(): string
    {
        $stage = config('finba.stage');

        return filled($stage) ? (string) $stage : 'Beta';
    }

    public static function build(): string
    {
        $build = config('finba.app_build');

        return filled($build) ? (string) $build : 'local';
    }

    public static function gitSha(): ?string
    {
        $sha = config('finba.git_sha');

        if (! is_string($sha) || blank($sha)) {
            return null;
        }

        return $sha;
    }

    public static function shortGitSha(): ?string
    {
        $sha = self::gitSha();

        if ($sha === null) {
            return null;
        }

        return substr($sha, 0, 7);
    }

    /**
     * @return array{app_version: string, app_build: string, git_sha: ?string}
     */
    public static function toArray(): array
    {
        return [
            'app_version' => self::version(),
            'app_build' => self::build(),
            'git_sha' => self::gitSha(),
        ];
    }

    public static function human(): string
    {
        $version = self::displayVersion();
        $build = self::build();
        $shortSha = self::shortGitSha();

        $details = array_values(array_filter([
            filled($build) ? $build : null,
            $shortSha,
        ]));

        if ($details === []) {
            return $version;
        }

        return sprintf('%s (%s)', $version, implode(' · ', $details));
    }
}
