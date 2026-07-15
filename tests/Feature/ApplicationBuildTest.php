<?php

use App\Support\ApplicationBuild;

it('falls back when version config is blank', function () {
    config([
        'finba.app_version' => '',
        'finba.app_build' => '',
        'finba.git_sha' => null,
        'finba.stage' => '',
    ]);

    expect(ApplicationBuild::version())->toBe('dev')
        ->and(ApplicationBuild::displayVersion())->toBe('dev')
        ->and(ApplicationBuild::stage())->toBe('Beta')
        ->and(ApplicationBuild::build())->toBe('local')
        ->and(ApplicationBuild::gitSha())->toBeNull();
});

it('prefixes visible versions with v', function () {
    config([
        'finba.app_version' => '0.1.0-beta',
        'finba.stage' => 'Beta',
    ]);

    expect(ApplicationBuild::displayVersion())->toBe('v0.1.0-beta')
        ->and(ApplicationBuild::stage())->toBe('Beta');
});

it('shortens the configured git sha', function () {
    config([
        'finba.app_version' => '0.1.0-beta',
        'finba.app_build' => '20260714.3',
        'finba.git_sha' => '84ac12fabc123',
    ]);

    expect(ApplicationBuild::gitSha())->toBe('84ac12fabc123')
        ->and(ApplicationBuild::shortGitSha())->toBe('84ac12f');
});

it('formats human readable build strings with display version', function (array $config, string $expected) {
    config($config);

    expect(ApplicationBuild::human())->toBe($expected);
})->with([
    'version and local build' => [
        [
            'finba.app_version' => '0.1.0-beta',
            'finba.app_build' => 'local',
            'finba.git_sha' => null,
        ],
        'v0.1.0-beta (local)',
    ],
    'version and dated build' => [
        [
            'finba.app_version' => '0.1.0-beta',
            'finba.app_build' => '20260714.3',
            'finba.git_sha' => null,
        ],
        'v0.1.0-beta (20260714.3)',
    ],
    'version build and sha' => [
        [
            'finba.app_version' => '0.1.0-beta',
            'finba.app_build' => '20260714.3',
            'finba.git_sha' => '84ac12fabc123',
        ],
        'v0.1.0-beta (20260714.3 · 84ac12f)',
    ],
]);

it('can be resolved after config cache', function () {
    config([
        'finba.app_version' => '0.1.0-beta',
        'finba.app_build' => 'local',
        'finba.git_sha' => 'deadbeef',
    ]);

    expect(ApplicationBuild::human())->toBe('v0.1.0-beta (local · deadbee)');
});
