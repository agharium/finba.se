<?php

namespace App\Filament\Resources\Transactions\Pages;

use App\Filament\Resources\Transactions\TransactionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Auth;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ManageTransactions extends ManageRecords
{
    protected static string $resource = TransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(fn (): string => $this->currentType() === 'INCOME'
                    ? 'Nova receita'
                    : 'Nova despesa')
                ->modalHeading(fn (): string => $this->currentType() === 'INCOME'
                    ? 'Criar receita'
                    : 'Criar despesa')
                ->icon('heroicon-m-plus')
                ->color('info')
                ->fillForm(fn (): array => [
                    'type' => $this->currentType(),
                    'status' => 'PAID',
                    'date' => now(),
                ])
                ->mutateDataUsing(function (array $data): array {
                    $data['user_id'] = Auth::id();
                    $data['status'] ??= 'PAID';
                
                    $data['category_id'] = $data['child_category_id']
                        ?? $data['parent_category_id']
                        ?? null;
                
                    unset($data['parent_category_id'], $data['child_category_id']);
                
                    return $data;
                })
                ->extraAttributes([
                    'class' => 'finba-mobile-fab',
                ])
        ];
    }

    public function getTabs(): array
    {
        return [
            'incomes' => Tab::make('Receitas')
                ->icon('heroicon-m-arrow-trending-up')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', 'INCOME')),
    
            'expenses' => Tab::make('Despesas')
                ->icon('heroicon-m-arrow-trending-down')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', 'EXPENSE')),
        ];
    }

    private function currentType(): string
    {
        return $this->activeTab === 'incomes'
            ? 'INCOME'
            : 'EXPENSE';
    }

    public function getExtraBodyAttributes(): array
    {
        return [
            'class' => 'finba-transactions-page',
        ];
    }
}
