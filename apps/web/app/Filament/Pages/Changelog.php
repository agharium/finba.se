<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Pages\PageConfiguration;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Navigation entry for the canonical public changelog at /changelog.
 *
 * Does not register a Filament route — that would conflict with the public page.
 */
class Changelog extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'Projeto';

    protected static ?string $navigationLabel = 'Changelog';

    protected static ?string $title = 'Changelog';

    protected static ?string $slug = 'changelog';

    protected static ?int $navigationSort = 1010;

    protected string $view = 'filament.pages.changelog';

    public static function registerRoutes(Panel $panel, ?PageConfiguration $configuration = null): void
    {
        // Intentionally empty: GET /changelog is owned by the public ChangelogController.
    }

    /**
     * @param  array<mixed>  $parameters
     */
    public static function getUrl(array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?Model $tenant = null, bool $shouldGuessMissingParameters = false, ?string $configuration = null): string
    {
        return route('changelog', absolute: $isAbsolute);
    }

    public function mount(): void
    {
        $this->redirect(route('changelog'), navigate: false);
    }
}
