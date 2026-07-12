<?php

namespace App\Filament\Pages;

use App\Filament\Forms\LocationFormFields;
use App\Filament\Forms\UserPreferenceFormFields;
use App\Services\UserPreferencesService;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\Rule;

class Profile extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static ?string $navigationLabel = 'Perfil';

    protected static ?string $title = 'Perfil';

    protected static ?string $slug = 'profile';

    protected static ?int $navigationSort = 1000;

    protected string $view = 'filament.pages.profile';

    public ?array $data = [];

    public function mount(UserPreferencesService $preferences): void
    {
        $user = auth()->user();

        $this->form->fill([
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            ...$preferences->defaultFormState($user),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->label('Nome')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                TextInput::make('username')
                    ->label('Nome de usuário')
                    ->maxLength(255)
                    ->rule(fn () => Rule::unique('users', 'username')->ignore(auth()->id())),

                TextInput::make('email')
                    ->label('E-mail')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->rule(fn () => Rule::unique('users', 'email')->ignore(auth()->id()))
                    ->extraAttributes([
                        'class' => 'finba-mobile-email-spacing',
                    ]),

                Section::make('Localização e idioma')
                    ->schema([
                        LocationFormFields::profileLocaleSelect(),
                        LocationFormFields::profileHiddenCountryField(),
                        LocationFormFields::profileRegionSelect(),
                        LocationFormFields::profileDefaultCitySelect(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                ...collect(UserPreferenceFormFields::featureToggles())
                    ->map(fn ($component) => $component->columnSpanFull())
                    ->all(),
            ]);
    }

    public function save(UserPreferencesService $preferences): void
    {
        $state = $this->form->getState();
        $user = auth()->user();

        $preferences->persistPreferences($user, $state);

        $user->update([
            'name' => $state['name'],
            'username' => $state['username'],
            'email' => $state['email'],
        ]);

        auth()->setUser(auth()->user()->fresh());

        Notification::make()
            ->title('Perfil atualizado')
            ->success()
            ->send();

        $this->redirect(static::getUrl(), navigate: false);
    }
}
