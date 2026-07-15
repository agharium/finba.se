<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

class About extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedInformationCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Sistema';

    protected static ?string $navigationLabel = 'Sobre o Finba.se';

    protected static ?string $title = 'Sobre o Finba.se';

    protected static ?string $slug = 'about';

    protected static ?int $navigationSort = 1040;

    protected string $view = 'filament.pages.about';

    public function getSubheading(): string|Htmlable|null
    {
        return 'Origem, status e princípios por trás do projeto.';
    }

    /**
     * @return array{
     *     name: string,
     *     url: ?string,
     *     github_url: ?string,
     *     linkedin_url: ?string,
     * }
     */
    public function getCreator(): array
    {
        return [
            'name' => (string) config('finba.creator.name'),
            'url' => $this->nullableUrl(config('finba.creator.url')),
            'github_url' => $this->nullableUrl(config('finba.creator.github_url')),
            'linkedin_url' => $this->nullableUrl(config('finba.creator.linkedin_url')),
        ];
    }

    public function getChangelogUrl(): string
    {
        return Changelog::getUrl();
    }

    public function getRoadmapUrl(): string
    {
        return Roadmap::getUrl();
    }

    public function getExtraBodyAttributes(): array
    {
        return [
            'class' => 'finba-about-page',
        ];
    }

    private function nullableUrl(mixed $value): ?string
    {
        if (! is_string($value) || blank($value)) {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_URL) ? $value : null;
    }
}
