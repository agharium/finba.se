<?php

namespace App\Filament\Pages;

use App\Services\ChangelogService;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use UnitEnum;

class Changelog extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'Projeto';

    protected static ?string $navigationLabel = 'Changelog';

    protected static ?string $title = 'Changelog';

    protected static ?string $slug = 'changelog';

    protected static ?int $navigationSort = 1010;

    protected string $view = 'filament.pages.changelog';

    public function getSubheading(): string|Htmlable|null
    {
        return 'Histórico detalhado das evoluções do Finba.se, em ordem cronológica.';
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function getEntries(): Collection
    {
        return app(ChangelogService::class)->entries(auth()->user());
    }

    public function getExtraBodyAttributes(): array
    {
        return [
            'class' => 'finba-changelog-page',
        ];
    }
}
