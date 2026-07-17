<?php

namespace App\Filament\Resources\People\Pages;

use App\Filament\Concerns\ConfiguresMobileCreateFab;
use App\Filament\Resources\People\PersonResource;
use App\Models\Person;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Auth;

class ManagePeople extends ManageRecords
{
    use ConfiguresMobileCreateFab;

    protected static string $resource = PersonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->withMobileCreateFab(
                CreateAction::make()
                    ->color('info')
                    ->mutateDataUsing(function (array $data): array {
                        $data['user_id'] = Auth::id();

                        return $data;
                    })
                    ->using(fn (array $data) => PersonResource::savePerson(new Person([
                        'user_id' => Auth::id(),
                    ]), $data)),
            ),
        ];
    }

    public function getExtraBodyAttributes(): array
    {
        return $this->mobileFabBodyAttributes('finba-people-page');
    }
}
