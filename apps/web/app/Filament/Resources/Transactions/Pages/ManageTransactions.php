<?php

namespace App\Filament\Resources\Transactions\Pages;

use App\Enums\IncomePaymentMode;
use App\Enums\TransactionEntryMode;
use App\Enums\TransactionType;
use App\Filament\Concerns\ConfiguresMobileCreateFab;
use App\Filament\Resources\Transactions\TransactionResource;
use App\Services\LocationDefaultsService;
use App\Services\TransactionService;
use App\Support\Geo\Support\GeoCityResolver;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ManageTransactions extends ManageRecords
{
    use ConfiguresMobileCreateFab;

    protected static string $resource = TransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->withMobileCreateFab(
                CreateAction::make()
                    ->label(fn (): string => $this->currentType() === TransactionType::INCOME->value
                        ? 'Nova receita'
                        : 'Nova despesa')
                    ->modalHeading(fn (): string => $this->currentType() === TransactionType::INCOME->value
                        ? 'Criar receita'
                        : 'Criar despesa')
                    ->color('info')
                    ->fillForm(function (): array {
                        $user = auth()->user();
                        $geoCityId = app(LocationDefaultsService::class)->cityIdForCreate($user);

                        return [
                            'type' => $this->currentType(),
                            'status' => 'PAID',
                            'date' => now(),
                            'payment_mode' => IncomePaymentMode::NOW->value,
                            'entry_mode' => TransactionEntryMode::IMMEDIATE->value,
                            ...app(GeoCityResolver::class)->formStateFromCityId($geoCityId),
                        ];
                    })
                    ->mutateFormDataUsing(fn (array $data): array => TransactionResource::mutateTransactionFormDataForSave($data))
                    ->using(function (array $data): Model {
                        $result = app(TransactionService::class)->create(auth()->user(), $data);

                        if ($result->isReceivableSale) {
                            Notification::make()
                                ->title('Conta a receber criada com sucesso.')
                                ->success()
                                ->send();

                            throw new Halt;
                        }

                        return $result->record;
                    }),
            ),
        ];
    }

    public function getTabs(): array
    {
        return [
            'incomes' => Tab::make('Receitas')
                ->icon(TransactionType::INCOME->getIcon())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', TransactionType::INCOME->value)),

            'expenses' => Tab::make('Despesas')
                ->icon(TransactionType::EXPENSE->getIcon())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', TransactionType::EXPENSE->value)),
        ];
    }

    private function currentType(): string
    {
        return $this->activeTab === 'incomes'
            ? TransactionType::INCOME->value
            : TransactionType::EXPENSE->value;
    }

    public function getExtraBodyAttributes(): array
    {
        return $this->mobileFabBodyAttributes('finba-transactions-page');
    }
}
