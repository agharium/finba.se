<?php

return [

    'app_version' => env('APP_VERSION', '0.1.0-beta'),

    'stage' => env('APP_STAGE', 'Beta'),

    'app_build' => env('APP_BUILD', 'local'),

    'git_sha' => env('GIT_SHA'),

    /*
    |--------------------------------------------------------------------------
    | Application storage disk
    |--------------------------------------------------------------------------
    |
    | Local development uses the Laravel "local" disk. Production should set
    | FINBA_STORAGE_DISK=finba (S3-compatible Supabase Storage). Application
    | code must resolve files via FileStorageService / Storage::disk(...) and
    | never assume local filesystem paths. See docs/supabase-storage.md.
    |
    */
    'storage' => [
        'disk' => env('FINBA_STORAGE_DISK', 'local'),
    ],

    'feedback' => [
        'email' => env('FINBA_FEEDBACK_EMAIL'),
        'rate_limit_per_hour' => (int) env('FINBA_FEEDBACK_RATE_LIMIT', 8),
        'max_attachment_kilobytes' => (int) env('FINBA_FEEDBACK_MAX_ATTACHMENT_KB', 2048),
        'attachment_directory' => 'feedback',
    ],

    'creator' => [
        'name' => 'José Paulo Oliveira Filho',
        'url' => 'https://finba.se',
        'github_url' => 'https://github.com/agharium/finba.se',
        'linkedin_url' => 'https://www.linkedin.com/in/jose-paulo-oliveira-filho/',
    ],

];
