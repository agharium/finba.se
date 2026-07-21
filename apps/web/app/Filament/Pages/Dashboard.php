<?php

namespace App\Filament\Pages;

use App\Filament\Forms\UserPreferenceFormFields;
use App\Filament\Widgets\MonthlyKpiWidget;
use App\Filament\Widgets\RecentTransactionsWidget;
use App\Filament\Widgets\TitheSummaryWidget;
use App\Filament\Widgets\TopExpenseCategoriesWidget;
use App\Services\UserPreferencesService;
use App\Support\DashboardMetrics;
use App\Support\Helpers;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static ?string $title = 'Início';

    protected static ?string $navigationLabel = 'Início';

    protected static ?int $navigationSort = -2;

    public bool $onboardingSkippedThisVisit = false;

    public function mount(): void
    {
        if (blank($this->filters['year'] ?? null) || blank($this->filters['month'] ?? null)) {
            $this->filters = [
                'year' => now()->year,
                'month' => now()->month,
            ];
        }

        if ($this->shouldShowOnboarding()) {
            $this->mountAction('onboarding');
        }
    }

    public function onboardingAction(): Action
    {
        return Action::make('onboarding')
            ->modalHeading('Bem-vindo ao Finba.se')
            ->modalDescription('Vamos configurar o básico para deixar o aplicativo do seu jeito.')
            ->modalWidth(Width::Large)
            ->closeModalByClickingAway(false)
            ->modalSubmitActionLabel('Começar a usar o Finba.se')
            ->modalCancelActionLabel('Pular por agora')
            ->modalCancelAction(fn (Action $action) => $action
                ->color('gray')
                ->action(function (): void {
                    $this->onboardingSkippedThisVisit = true;
                }))
            ->fillForm(fn (UserPreferencesService $preferences): array => $preferences->defaultFormState(auth()->user()))
            ->steps([
                Step::make('Localização e idioma')
                    ->description('Defina idioma, estado e cidade para personalizar o app.')
                    ->schema(UserPreferenceFormFields::locationFields(requireRegion: true, requireCity: true))
                    ->columns(1),

                Step::make('Recursos')
                    ->description('Ative apenas o que fizer sentido para você.')
                    ->schema(UserPreferenceFormFields::featureToggles('finba-onboarding-advanced-nested'))
                    ->columns(1),

                Step::make('Resumo')
                    ->description('Confira suas escolhas antes de começar.')
                    ->schema([
                        ViewField::make('onboarding_summary')
                            ->dehydrated(false)
                            ->view('filament.forms.onboarding-summary')
                            ->viewData(fn (Get $get, UserPreferencesService $preferences): array => [
                                'summary' => $preferences->buildSummary(auth()->user(), [
                                    'locale' => $get('locale'),
                                    'geo_country_code' => $get('geo_country_code'),
                                    'geo_region_id' => $get('geo_region_id'),
                                    'geo_city_id' => $get('geo_city_id'),
                                    'advanced' => $get('advanced'),
                                    'accounts_receivable' => $get('accounts_receivable'),
                                    'tither' => $get('tither'),
                                ]),
                            ]),
                    ]),
            ])
            ->action(function (Action $action, UserPreferencesService $preferences): void {
                $user = auth()->user();

                $preferences->completeOnboarding($user, $action->getData());

                auth()->setUser($user->fresh());

                Notification::make()
                    ->title('Tudo pronto!')
                    ->body('Suas preferências foram salvas. Bom uso do Finba.se!')
                    ->success()
                    ->send();

                $this->redirect(static::getUrl(), navigate: false);
            })
            ->extraAttributes([
                'class' => 'finba-onboarding-action',
            ]);
    }

    public function getSubheading(): string|Htmlable|null
    {
        $year = (int) ($this->filters['year'] ?? now()->year);
        $month = (int) ($this->filters['month'] ?? now()->month);

        return Helpers::monthLabelPtBr($month).' '.$year;
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->columns([
                'default' => 1,
                'md' => 2,
            ])
            ->components([
                Select::make('year')
                    ->label('Ano')
                    ->options(fn (): array => DashboardMetrics::availableYearOptions())
                    ->default(now()->year)
                    ->selectablePlaceholder(false)
                    ->native(false)
                    ->live(),

                Select::make('month')
                    ->label('Mês')
                    ->options(fn (): array => DashboardMetrics::availableMonthOptions(
                        $this->filters['year'] ?? now()->year,
                    ))
                    ->default(now()->month)
                    ->selectablePlaceholder(false)
                    ->native(false)
                    ->live(),
            ]);
    }

    public function getFiltersForm(): Schema
    {
        if ((! $this->isCachingSchemas) && $this->hasCachedSchema('filtersForm')) {
            return $this->getSchema('filtersForm');
        }

        $schema = $this->makeSchema()
            ->columns([
                'default' => 1,
                'md' => 2,
            ])
            ->extraAttributes([
                'wire:partial' => 'table-filters-form',
                'class' => 'finba-dashboard-filters',
            ])
            ->live()
            ->statePath('filters');

        return $this->filtersForm($schema);
    }

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            MonthlyKpiWidget::class,
            TitheSummaryWidget::class,
            RecentTransactionsWidget::class,
            TopExpenseCategoriesWidget::class,
        ];
    }

    public function getColumns(): int|array
    {
        return 1;
    }

    public function getExtraBodyAttributes(): array
    {
        return [
            'class' => 'finba-dashboard-page',
        ];
    }

    protected function shouldShowOnboarding(): bool
    {
        $user = auth()->user();

        return $user !== null
            && ! $user->hasCompletedOnboarding()
            && ! $this->onboardingSkippedThisVisit;
    }
}
