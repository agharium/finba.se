<?php

use App\Filament\Resources\Categories\Pages\ManageCategories;
use App\Filament\Resources\People\Pages\ManagePeople;
use App\Filament\Resources\Transactions\Pages\ManageTransactions;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function mobileFabUser(array $overrides = []): User
{
    return User::query()->create(array_merge([
        'name' => 'Mobile FAB User',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
        'email_verified_at' => now(),
        'settings' => ['advanced' => true, 'locale' => 'pt-BR'],
        'default_country_code' => 'BR',
        'default_region_code' => 'RS',
    ], $overrides));
}

it('exposes the mobile create fab on transactions, people and categories pages', function (string $page) {
    $user = mobileFabUser();

    $component = Livewire::actingAs($user)
        ->test($page)
        ->assertOk()
        ->assertSeeHtml('finba-mobile-fab');

    expect($component->instance()->getExtraBodyAttributes()['class'] ?? '')
        ->toContain('finba-has-mobile-fab');
})->with([
    ManageTransactions::class,
    ManagePeople::class,
    ManageCategories::class,
]);
