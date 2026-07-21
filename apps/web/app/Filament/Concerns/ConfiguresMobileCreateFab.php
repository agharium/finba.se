<?php

namespace App\Filament\Concerns;

use Filament\Actions\CreateAction;

trait ConfiguresMobileCreateFab
{
    protected function withMobileCreateFab(CreateAction $action): CreateAction
    {
        return $action
            ->icon('heroicon-m-plus')
            ->extraAttributes([
                'class' => 'finba-mobile-fab',
            ]);
    }

    /**
     * @return array<string, string>
     */
    protected function mobileFabBodyAttributes(string ...$classes): array
    {
        return [
            'class' => collect([...$classes, 'finba-has-mobile-fab'])
                ->filter()
                ->unique()
                ->implode(' '),
        ];
    }
}
