<?php

use App\Filament\Pages\Changelog;
use App\Filament\Pages\Roadmap;
use App\Models\User;
use App\Services\RoadmapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function roadmapUser(): User
{
    return User::query()->create([
        'name' => 'Roadmap User',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
        'email_verified_at' => now(),
        'settings' => ['locale' => 'pt-BR'],
    ]);
}

it('allows authenticated users to access roadmap', function () {
    Livewire::actingAs(roadmapUser())
        ->test(Roadmap::class)
        ->assertSuccessful()
        ->assertSee('Roadmap')
        ->assertSee('Organização financeira')
        ->assertSee('Colaboração');
});

it('denies guests access to roadmap', function () {
    $this->get(Roadmap::getUrl())
        ->assertRedirect();
});

it('uses the roadmap route slug', function () {
    expect(Roadmap::getUrl())->toEndWith('/roadmap');
});

it('places roadmap in the projeto navigation group after changelog', function () {
    expect(Roadmap::getNavigationGroup())->toBe('Projeto')
        ->and(Roadmap::getNavigationSort())->toBeGreaterThan(Changelog::getNavigationSort());
});

it('renders completed, in-progress and planned items', function () {
    Livewire::actingAs(roadmapUser())
        ->test(Roadmap::class)
        ->assertSee('Categorias e subcategorias')
        ->assertSee('Empréstimos e dívidas')
        ->assertSee('Transferências entre contas')
        ->assertSee('Concluído')
        ->assertSee('Em desenvolvimento')
        ->assertSee('Planejado');
});

it('shows correct summary counts from roadmap data', function () {
    $counts = app(RoadmapService::class)->statusCounts();

    expect($counts)->toBe([
        'completed' => 19,
        'in_progress' => 3,
        'planned' => 5,
    ]);

    Livewire::actingAs(roadmapUser())
        ->test(Roadmap::class)
        ->assertSee('19')
        ->assertSee('Concluídos')
        ->assertSee('Em desenvolvimento')
        ->assertSee('Planejados')
        ->assertSee('Primeira versão beta publicada')
        ->assertSee('Estabilização da versão beta');
});

it('handles unsupported statuses safely', function () {
    $service = app(RoadmapService::class);

    expect($service->isSupportedStatus('completed'))->toBeTrue()
        ->and($service->isSupportedStatus('unknown'))->toBeFalse()
        ->and($service->normalizeStatus('unknown'))->toBe('planned')
        ->and($service->statusLabel('unknown'))->toBe('Planejado');
});

it('uses card markup instead of tables', function () {
    $view = file_get_contents(resource_path('views/filament/pages/roadmap.blade.php'));

    expect($view)
        ->toContain('finba-roadmap-category')
        ->not->toContain('<table');
});
