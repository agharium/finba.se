<?php

namespace App\Filament\Resources\Loans\Pages;

use App\Filament\Resources\Loans\LoanResource;
use Filament\Resources\Pages\ManageRecords;

class ManageLoans extends ManageRecords
{
    protected static string $resource = LoanResource::class;

    public function getExtraBodyAttributes(): array
    {
        return [
            'class' => 'finba-loans-page finba-transactions-page',
        ];
    }
}
