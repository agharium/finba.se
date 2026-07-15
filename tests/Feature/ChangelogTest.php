<?php

use App\Filament\Pages\Changelog;
use App\Filament\Pages\Profile;
use App\Filament\Pages\SendFeedback;
use App\Models\User;
use App\Services\ChangelogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function changelogUser(): User
{
    return User::query()->create([
        'name' => 'Changelog User',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
        'email_verified_at' => now(),
        'settings' => ['locale' => 'pt-BR'],
    ]);
}

it('allows authenticated users to access changelog', function () {
    Livewire::actingAs(changelogUser())
        ->test(Changelog::class)
        ->assertSuccessful()
        ->assertSee('Changelog')
        ->assertSee('Finba')
        ->assertSee('v0.1.0-beta')
        ->assertSee('Produção')
        ->assertSee('PWA instalável')
        ->assertSee('Lançamento da primeira versão beta')
        ->assertSee('Fundação do projeto Finba.se')
        ->assertSee('Onboarding, localização e changelog alfa')
        ->assertSee('Parcelamentos, PWA e moeda por país');
});

it('denies guests access to changelog', function () {
    $this->get(Changelog::getUrl())
        ->assertRedirect();
});

it('uses the changelog route slug', function () {
    expect(Changelog::getUrl())->toEndWith('/changelog');
});

it('does not register novidades as the canonical route', function () {
    $this->get('/novidades')->assertNotFound();
});

it('orders changelog entries by date descending', function () {
    $sorted = app(ChangelogService::class)->sortedEntries([
        ['date' => '2026-01-01', 'title' => 'older'],
        ['date' => '2026-07-12', 'title' => 'newer'],
        ['date' => '2026-03-15', 'title' => 'middle'],
    ]);

    expect($sorted->pluck('title')->all())->toBe([
        'newer',
        'middle',
        'older',
    ]);
});

it('represents the first project date in the changelog', function () {
    expect(app(ChangelogService::class)->earliestDate())->toBe('2026-05-16');
});

it('describes the current phase as beta', function () {
    $banner = view('filament.components.alpha-banner', [
        'changelogUrl' => Changelog::getUrl(),
        'feedbackUrl' => SendFeedback::getUrl(),
    ])->render();

    expect($banner)
        ->toContain('Beta')
        ->toContain('versão beta do Finba')
        ->toContain('Ver Changelog')
        ->toContain('Enviar Feedback')
        ->not->toContain('fase alfa')
        ->not->toContain('Alfa');

    Livewire::actingAs(changelogUser())
        ->test(Changelog::class)
        ->assertSee('Beta')
        ->assertSee('v0.1.0-beta')
        ->assertSee('Lançamento da primeira versão beta');
});

it('links the release banner to changelog and feedback', function () {
    $html = view('filament.components.alpha-banner', [
        'changelogUrl' => Changelog::getUrl(),
        'feedbackUrl' => SendFeedback::getUrl(),
    ])->render();

    expect($html)
        ->toContain(Changelog::getUrl())
        ->toContain(SendFeedback::getUrl())
        ->toContain('finba_release_banner_dismissed')
        ->toContain('sessionStorage');
});

it('keeps changelog accessible regardless of banner dismissal state', function () {
    $user = changelogUser();

    view('filament.components.alpha-banner', [
        'changelogUrl' => Changelog::getUrl(),
        'feedbackUrl' => SendFeedback::getUrl(),
    ])->render();

    Livewire::actingAs($user)
        ->test(Changelog::class)
        ->assertSuccessful()
        ->assertSee('Fundação do projeto Finba.se');
});

it('uses card and timeline markup instead of tables', function () {
    $view = file_get_contents(resource_path('views/filament/pages/changelog.blade.php'));

    expect($view)
        ->toContain('finba-changelog-day')
        ->toContain('finba-changelog-summary')
        ->not->toContain('<table');
});

it('places changelog after perfil in navigation order', function () {
    expect(Changelog::getNavigationSort())->toBeGreaterThan(Profile::getNavigationSort())
        ->and(Changelog::getNavigationGroup())->toBe('Sistema');
});

it('labels changelog item types in portuguese', function () {
    $service = app(ChangelogService::class);

    expect($service->itemTypeLabel('added'))->toBe('Adicionado')
        ->and($service->itemTypeLabel('changed'))->toBe('Alterado')
        ->and($service->itemTypeLabel('fixed'))->toBe('Corrigido')
        ->and($service->itemTypeLabel('removed'))->toBe('Removido')
        ->and($service->itemTypeLabel('decision'))->toBe('Decisão')
        ->and($service->itemTypeLabel('internal'))->toBe('Interno');
});
