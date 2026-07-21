<?php

use App\Enums\ChangelogVisibility;
use App\Filament\Pages\Changelog;
use App\Filament\Pages\Profile;
use App\Filament\Pages\SendFeedback;
use App\Models\User;
use App\Services\ChangelogService;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

it('allows authenticated users to read the full changelog on the canonical page', function () {
    $this->withoutVite();

    $this->actingAs(changelogUser())
        ->get(route('changelog'))
        ->assertOk()
        ->assertSee('Changelog', false)
        ->assertSee('Finba.se')
        ->assertSee('v0.1.0-beta')
        ->assertSee('Lançamento da primeira versão beta')
        ->assertSee('Fundação do projeto Finba.se')
        ->assertSee('Onboarding, localização e changelog alfa')
        ->assertSee('Parcelamentos, PWA e moeda por país')
        ->assertSee('Marco arquitetural: extração da plataforma geográfica')
        ->assertSee('A validação pós-migração confirmou');
});

it('keeps filament changelog navigation pointing at the public route', function () {
    expect(Changelog::getUrl())->toEndWith('/changelog')
        ->and(Changelog::getUrl())->toBe(route('changelog'));
});

it('does not register a filament-owned changelog route', function () {
    expect(collect(app('router')->getRoutes())->contains(
        fn ($route) => $route->getName() === 'filament.admin.pages.changelog'
    ))->toBeFalse();
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
        ->toContain('versão beta do Finba.se')
        ->toContain('Ver Changelog')
        ->toContain('Enviar Feedback')
        ->not->toContain('fase alfa')
        ->not->toContain('Alfa');

    $this->withoutVite();

    $this->actingAs(changelogUser())
        ->get(route('changelog'))
        ->assertOk()
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
        ->toContain(route('changelog'))
        ->toContain(SendFeedback::getUrl())
        ->toContain('finba_release_banner_dismissed')
        ->toContain('sessionStorage');
});

it('keeps changelog accessible regardless of banner dismissal state', function () {
    $this->withoutVite();

    view('filament.components.alpha-banner', [
        'changelogUrl' => Changelog::getUrl(),
        'feedbackUrl' => SendFeedback::getUrl(),
    ])->render();

    $this->actingAs(changelogUser())
        ->get(route('changelog'))
        ->assertOk()
        ->assertSee('Fundação do projeto Finba.se');
});

it('uses card and timeline markup instead of tables', function () {
    $partial = file_get_contents(resource_path('views/changelog/partials/entries.blade.php'));
    $public = file_get_contents(resource_path('views/changelog/public.blade.php'));

    expect($partial)
        ->toContain('finba-changelog-day')
        ->toContain('finba-changelog-day--featured')
        ->not->toContain('<table')
        ->and($public)
        ->toContain('changelog.partials.entries');
});

it('places changelog after perfil in navigation order', function () {
    expect(Changelog::getNavigationSort())->toBeGreaterThan(Profile::getNavigationSort())
        ->and(Changelog::getNavigationGroup())->toBe('Projeto');
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

it('allows anonymous visitors to read the public changelog', function () {
    $this->withoutVite();

    $latest = app(ChangelogService::class)->latestPublicEntry();

    $this->get(route('changelog'))
        ->assertOk()
        ->assertSee('Changelog', false)
        ->assertSee('Marco arquitetural: extração da plataforma geográfica')
        ->assertSee('Este é o ponto em que o Finba.se evoluiu')
        ->assertSee('data-featured="true"', false)
        ->assertSee('finba-changelog-day--featured', false)
        ->assertSee($latest['date'])
        ->assertDontSee('A validação pós-migração confirmou');
});

it('hides authenticated-only items from the public feed', function () {
    $service = app(ChangelogService::class);
    $needle = 'A validação pós-migração confirmou';

    $publicTexts = $service->publicEntries()
        ->flatMap(fn (array $entry) => collect($entry['groups'])->flatMap(fn (array $group) => $group['items']))
        ->pluck('text');

    $authenticatedTexts = $service->entries()
        ->flatMap(fn (array $entry) => collect($entry['groups'])->flatMap(fn (array $group) => $group['items']))
        ->pluck('text');

    expect($authenticatedTexts->contains(fn (string $text) => str_contains($text, $needle)))->toBeTrue()
        ->and($publicTexts->contains(fn (string $text) => str_contains($text, $needle)))->toBeFalse();
});

it('shares the same changelog source for guests and authenticated visitors', function () {
    $service = app(ChangelogService::class);

    expect($service->rawEntries())->not->toBeEmpty()
        ->and($service->publicEntries()->first()['date'])->toBe($service->latestDate())
        ->and(ChangelogVisibility::Public->isVisibleToPublic())->toBeTrue()
        ->and(ChangelogVisibility::Authenticated->isVisibleToPublic())->toBeFalse();
});

it('marks the geo extraction day as featured', function () {
    $entry = app(ChangelogService::class)
        ->publicEntries()
        ->firstWhere('date', '2026-07-19');

    expect($entry)->not->toBeNull()
        ->and($entry['featured'])->toBeTrue()
        ->and($entry['featured_label'])->toBe('Marco arquitetural');
});

it('documents the public changelog in the monorepo readme', function () {
    $readme = file_get_contents(base_path('../../README.md'));

    expect($readme)
        ->toContain('https://app.finba.se/changelog')
        ->toContain('Project changelog');
});
