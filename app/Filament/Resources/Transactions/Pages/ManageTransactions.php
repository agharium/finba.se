<?php

namespace App\Filament\Resources\Transactions\Pages;

use App\Enums\IncomePaymentMode;
use App\Enums\TransactionType;
use App\Filament\Resources\Transactions\TransactionResource;
use App\Models\Transaction;
use App\Services\ReceivableSaleService;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Exceptions\Halt;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ManageTransactions extends ManageRecords
{
    protected static string $resource = TransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(fn (): string => $this->currentType() === TransactionType::INCOME->value
                    ? 'Nova receita'
                    : 'Nova despesa')
                ->modalHeading(fn (): string => $this->currentType() === TransactionType::INCOME->value
                    ? 'Criar receita'
                    : 'Criar despesa')
                ->icon('heroicon-m-plus')
                ->color('info')
                ->fillForm(fn (): array => [
                    'type' => $this->currentType(),
                    'status' => 'PAID',
                    'date' => now(),
                    'payment_mode' => IncomePaymentMode::NOW->value,
                ])
                ->using(function (array $data): Model {
                    $data['user_id'] = Auth::id();
                    $data['status'] ??= 'PAID';

                    if (TransactionResource::shouldCreateReceivable($data)) {
                        app(ReceivableSaleService::class)->create(auth()->user(), $data);

                        Notification::make()
                            ->title('Conta a receber criada com sucesso.')
                            ->success()
                            ->send();

                        throw new Halt();
                    }

                    return Transaction::query()->create(
                        TransactionResource::prepareTransactionAttributes($data),
                    );
                })
                ->extraAttributes([
                    'class' => 'finba-mobile-fab',
                ]),
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
        return [
            'class' => 'finba-transactions-page',
        ];
    }
}
