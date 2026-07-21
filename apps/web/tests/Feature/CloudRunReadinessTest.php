<?php

it('exposes an unauthenticated health endpoint', function () {
    $this->get('/up')
        ->assertSuccessful()
        ->assertDontSee('APP_KEY')
        ->assertDontSee('DB_PASSWORD')
        ->assertDontSee('SUPABASE_STORAGE_SECRET_ACCESS_KEY');
});

it('trusts proxies for cloud load balancers', function () {
    $bootstrap = file_get_contents(base_path('bootstrap/app.php'));

    expect($bootstrap)
        ->toContain('trustProxies(')
        ->toContain("at: '*'")
        ->toContain('HEADER_X_FORWARDED_PROTO')
        ->toContain('HEADER_X_FORWARDED_HOST')
        ->toContain('HEADER_X_FORWARDED_PORT');
});

it('documents cloud run production conventions in env example', function () {
    $example = file_get_contents(base_path('.env.example'));

    expect($example)
        ->toContain('SESSION_DRIVER=database')
        ->toContain('CACHE_STORE=database')
        ->toContain('QUEUE_CONNECTION=database')
        ->toContain('LOG_CHANNEL=stderr')
        ->toContain('FINBA_STORAGE_DISK=finba')
        ->toContain('QUEUE_CONNECTION=sync')
        ->toContain('GOOGLE_CLIENT_ID=')
        ->toContain('SESSION_SECURE_COOKIE=true')
        ->toContain('DB_SSLMODE=require')
        ->toContain('APP_URL=https://app.finba.se')
        ->toContain('GOOGLE_REDIRECT_URL=https://app.finba.se/auth/google/callback')
        ->toContain('ASSET_URL');
});

it('ships a cloud run dockerfile and entrypoint', function () {
    $dockerfile = file_get_contents(base_path('Dockerfile'));
    $entrypoint = file_get_contents(base_path('docker/entrypoint.sh'));

    expect(file_exists(base_path('Dockerfile')))->toBeTrue()
        ->and(file_exists(base_path('docker/entrypoint.sh')))->toBeTrue()
        ->and(file_exists(base_path('docker/Caddyfile')))->toBeTrue()
        ->and($dockerfile)->toContain('frankenphp')
        ->and($dockerfile)->toContain('pdo_sqlite')
        ->and($dockerfile)->toContain('country-currencies.php')
        ->and($entrypoint)->toContain('php artisan optimize')
        ->and($entrypoint)->not->toContain('sushi-*.sqlite');
});
