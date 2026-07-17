<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Concerns\ConfiguresMobileCreateFab;
use App\Filament\Resources\Categories\CategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Auth;

class ManageCategories extends ManageRecords
{
    use ConfiguresMobileCreateFab;

    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->withMobileCreateFab(
                CreateAction::make()
                    ->color('info')
                    ->mutateDataUsing(function (array $data): array {
                        $data['user_id'] = Auth::id();

                        return $data;
                    }),
            ),
        ];
    }

    public function getExtraBodyAttributes(): array
    {
        return $this->mobileFabBodyAttributes('finba-categories-page');
    }
}
