<?php

namespace App\Filament\Pages;

use App\Services\RoadmapService;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use UnitEnum;

class Roadmap extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Projeto';

    protected static ?string $navigationLabel = 'Roadmap';

    protected static ?string $title = 'Roadmap';

    protected static ?string $slug = 'roadmap';

    protected static ?int $navigationSort = 1020;

    protected string $view = 'filament.pages.roadmap';

    public function getSubheading(): string|Htmlable|null
    {
        return 'Acompanhe o que já foi concluído, o que está em desenvolvimento e o que está planejado para o Finba.se.';
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function getCategories(): Collection
    {
        return app(RoadmapService::class)->categories();
    }

    /**
     * @return array<string, int>
     */
    public function getStatusCounts(): array
    {
        return app(RoadmapService::class)->statusCounts();
    }

    public function getExtraBodyAttributes(): array
    {
        return [
            'class' => 'finba-roadmap-page',
        ];
    }
}
