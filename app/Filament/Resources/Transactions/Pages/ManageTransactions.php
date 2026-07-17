<?php

namespace App\Filament\Resources\Transactions\Pages;

use App\Enums\IncomePaymentMode;
use App\Enums\TransactionEntryMode;
use App\Enums\TransactionType;
use App\Filament\Concerns\ConfiguresMobileCreateFab;
use App\Filament\Forms\LocationFormFields;
use App\Filament\Resources\Transactions\TransactionResource;
use App\Services\TransactionService;
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
                    ->fillForm(fn (): array => [
                        'type' => $this->currentType(),
                        'status' => 'PAID',
                        'date' => now(),
                        'payment_mode' => IncomePaymentMode::NOW->value,
                        'entry_mode' => TransactionEntryMode::IMMEDIATE->value,
                        'city_id' => LocationFormFields::userDefaultCityId(),
                    ])
                    ->mutateFormDataUsing(fn (array $data): array => LocationFormFields::stripEphemeralFields($data))
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
