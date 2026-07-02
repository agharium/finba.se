<?php

namespace App\Filament\Widgets;

use App\Exceptions\TitheDeliveryException;
use App\Filament\Widgets\Concerns\InteractsWithDashboardPeriod;
use App\Services\TitheDeliverySelection;
use App\Services\TitheDeliveryService;
use App\Support\TitheMetrics;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Enums\Width;
use Filament\Widgets\Widget;
use Illuminate\Contracts\View\View;

class TitheSummaryWidget extends Widget implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithDashboardPeriod;
    use InteractsWithSchemas;

    protected static bool $isDiscovered = false;

    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    protected string $view = 'filament.widgets.tithe-summary-widget';

    public bool $deliverTithe = false;

    public bool $deliverOffering = false;

    public bool $deliverFirstfruits = false;

    public static function canView(): bool
    {
        return auth()->user()?->isTither() ?? false;
    }

    public function resetDeliverySelection(): void
    {
        $summary = $this->titheMetrics()->summary();

        $this->deliverTithe = $summary['tithe_pending'] > 0;
        $this->deliverOffering = false;
        $this->deliverFirstfruits = false;
    }

    public function deliverAction(): Action
    {
        return Action::make('deliver')
            ->label(fn (): string => TitheMetrics::ctaLabel($this->titheMetrics()->summary()))
            ->button()
            ->color('success')
            ->size('lg')
            ->extraAttributes([
                'class' => 'finba-dashboard-tithe__cta-trigger w-full',
            ])
            ->mountUsing(function (): void {
                $this->resetDeliverySelection();
            })
            ->modalHeading('Confirmar entrega')
            ->modalDescription(fn (): string => sprintf(
                'Escolha o que deseja entregar referente a %s.',
                $this->dashboardMetrics()->monthLabel(),
            ))
            ->modalContent(fn (): View => view('filament.widgets.tithe-delivery-modal', [
                'summary' => $this->titheMetrics()->summary(),
                'monthLabel' => $this->dashboardMetrics()->monthLabel(),
            ]))
            ->modalSubmitActionLabel('Confirmar entrega')
            ->modalCancelActionLabel('Cancelar')
            ->action(function (TitheDeliveryService $deliveryService): void {
                try {
                    $selection = new TitheDeliverySelection(
                        deliverTithe: $this->deliverTithe,
                        deliverOffering: $this->deliverOffering,
                        deliverFirstfruits: $this->deliverFirstfruits,
                    );

                    $deliveryService->deliver(
                        auth()->user(),
                        $this->dashboardPeriod(),
                        $selection,
                    );

                    Notification::make()
                        ->title('Entrega registrada')
                        ->body(sprintf(
                            'Os valores selecionados de %s foram registrados com sucesso.',
                            $this->dashboardMetrics()->monthLabel(),
                        ))
                        ->success()
                        ->send();
                } catch (TitheDeliveryException $exception) {
                    Notification::make()
                        ->title('Não foi possível entregar')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    $this->halt();
                }
            })
            ->modalWidth(Width::Medium)
            ->extraModalWindowAttributes([
                'class' => 'finba-tithe-modal',
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $summary = $this->titheMetrics()->summary();

        return [
            'summary' => $summary,
            'ctaEnabled' => TitheMetrics::ctaEnabled($summary),
            'ctaLabel' => TitheMetrics::ctaLabel($summary),
        ];
    }
}
