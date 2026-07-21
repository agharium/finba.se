<?php

use App\Enums\Locale;
use App\Filament\Auth\Register;
use App\Filament\Pages\Profile;
use App\Models\User;
use App\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['app.locale' => 'en']);
    app()->setLocale('en');
});

function verificationMail(User $user): MailMessage
{
    $previous = app()->getLocale();
    app()->setLocale($user->preferredLocale());

    try {
        return (new VerifyEmail)->toMail($user);
    } finally {
        app()->setLocale($previous);
    }
}

it('normalizes browser language tags through Locale', function (string $raw, string $expected) {
    expect(Locale::detectBrowserLocale($raw)->value)->toBe($expected);
})->with([
    ['pt-BR', 'pt_BR'],
    ['pt', 'pt_BR'],
    ['pt-PT', 'pt_BR'],
    ['en-US', 'en'],
    ['en-GB', 'en'],
    ['es-MX', 'es'],
    ['es-AR', 'es'],
    ['fr-FR', 'en'],
    ['invalid', 'en'],
    ['', 'en'],
]);

it('falls back to English for null and invalid configured APP_LOCALE', function () {
    config(['app.locale' => 'en']);

    expect(Locale::fromNullable(null))->toBe(Locale::English)
        ->and(Locale::normalize(null))->toBeNull();

    config(['app.locale' => 'not-a-locale']);

    expect(Locale::default())->toBe(Locale::English)
        ->and(Locale::fromNullable(null))->toBe(Locale::English)
        ->and(Locale::values())->toBe(['en', 'pt_BR', 'es']);
});

it('returns canonical preferredLocale strings for users', function () {
    config(['app.locale' => 'en']);

    expect(User::factory()->create(['locale' => 'pt_BR'])->preferredLocale())->toBe('pt_BR')
        ->and(User::factory()->create(['locale' => 'pt-BR'])->preferredLocale())->toBe('pt_BR')
        ->and(User::factory()->create(['locale' => 'en'])->preferredLocale())->toBe('en')
        ->and(User::factory()->create(['locale' => 'fr'])->preferredLocale())->toBe('en');
});

it('persists a non-null detected locale during registration', function () {
    Notification::fake();

    Livewire::withHeaders([
        'Accept-Language' => 'pt-BR,pt;q=0.9,en;q=0.8',
    ])
        ->test(Register::class)
        ->set('data.name', 'Locale User')
        ->set('data.username', 'locale_user')
        ->set('data.email', 'locale-user@finba.se')
        ->set('data.password', 'password')
        ->set('data.passwordConfirmation', 'password')
        ->set('data.browser_locale', 'es-MX')
        ->call('register')
        ->assertHasNoFormErrors();

    $user = User::query()->where('email', 'locale-user@finba.se')->first();

    expect($user)->not->toBeNull()
        ->and($user->locale)->toBe('es')
        ->and($user->locale)->not->toBeNull();

    Notification::assertSentToTimes($user, VerifyEmail::class, 1);
});

it('falls back to English when browser locale is missing during registration', function () {
    Notification::fake();
    config(['app.locale' => 'en']);

    Livewire::test(Register::class)
        ->set('data.name', 'Default Locale User')
        ->set('data.username', 'default_locale_user')
        ->set('data.email', 'default-locale@finba.se')
        ->set('data.password', 'password')
        ->set('data.passwordConfirmation', 'password')
        ->set('data.browser_locale', '')
        ->call('register')
        ->assertHasNoFormErrors();

    $user = User::query()->where('email', 'default-locale@finba.se')->first();

    expect($user?->locale)->toBe('en');
});

it('updates users.locale from preferences without browser detection', function () {
    prepareGeoTestEnvironment();
    fakeGeoContractApi();

    $user = User::factory()->create([
        'locale' => 'en',
        'settings' => ['advanced' => true, 'locale' => 'en'],
    ]);

    $user->update(['geo_city_id' => 1001]);

    Livewire::actingAs($user->fresh())
        ->test(Profile::class)
        ->set('data.locale', 'pt_BR')
        ->set('data.geo_country_code', 'BR')
        ->set('data.geo_region_id', 2021)
        ->set('data.geo_city_id', 1001)
        ->call('save')
        ->assertRedirect(Profile::getUrl());

    expect($user->fresh()->locale)->toBe('pt_BR')
        ->and($user->fresh()->settings['locale'] ?? null)->toBe('pt_BR');
});

it('sends the Portuguese verification email for a pt_BR user', function () {
    $mail = verificationMail(User::factory()->create([
        'locale' => 'pt_BR',
        'email_verified_at' => null,
    ]));

    expect($mail->subject)->toBe('Confirme seu e-mail no Finba.se')
        ->and($mail->actionText)->toBe('Confirmar meu e-mail');
});

it('sends the English verification email for an en user', function () {
    $mail = verificationMail(User::factory()->create([
        'locale' => 'en',
        'email_verified_at' => null,
    ]));

    expect($mail->subject)->toBe('Confirm your email address for Finba.se')
        ->and($mail->actionText)->toBe('Confirm my email');
});

it('sends the Spanish verification email for an es user', function () {
    $mail = verificationMail(User::factory()->create([
        'locale' => 'es',
        'email_verified_at' => null,
    ]));

    expect($mail->subject)->toBe('Confirma tu correo electrónico en Finba.se')
        ->and($mail->actionText)->toBe('Confirmar mi correo electrónico');
});

it('builds a Filament temporary signed verification URL for APP_URL', function () {
    config(['app.url' => 'http://localhost:8000']);
    URL::forceRootUrl('http://localhost:8000');

    $mail = verificationMail(User::factory()->create([
        'locale' => 'en',
        'email_verified_at' => null,
    ]));

    expect($mail->actionUrl)
        ->toContain('localhost:8000/email-verification/verify/')
        ->and($mail->actionUrl)->toContain('signature=')
        ->and($mail->actionUrl)->toContain('expires=')
        ->and(URL::hasValidSignature(request()->create($mail->actionUrl)))->toBeTrue();
});

it('does not register a CreateUrlUsing callback on the custom VerifyEmail notification', function () {
    expect(VerifyEmail::$createUrlCallback)->toBeNull();
});
