<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\Rule;

class Profile extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static ?string $navigationLabel = 'Perfil';

    protected static ?string $title = 'Perfil';

    protected static ?string $slug = 'profile';

    protected string $view = 'filament.pages.profile';

    public ?array $data = [];

    public function mount(): void
    {
        $user = auth()->user();

        $this->form->fill([
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'is_advanced' => $user->is_advanced,
            'is_tither' => $user->is_tither,
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
                    ->required()
                    ->maxLength(255)
                    ->rule(fn () => Rule::unique('users', 'username')->ignore(auth()->id())),
    
                TextInput::make('email')
                    ->label('E-mail')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->rule(fn () => Rule::unique('users', 'email')->ignore(auth()->id())),
    
                Toggle::make('is_advanced')
                    ->label('Modo avançado')
                    ->helperText('Habilita recursos mais específicos, como vínculos entre pessoas e categorias.')
                    ->columnSpanFull(),
    
                Toggle::make('is_tither')
                    ->label('Calcular dízimos, ofertas e primícias')
                    ->helperText('Mostra opções relacionadas ao cálculo de dízimos nas transações e relatórios.')
                    ->columnSpanFull(),
            ]);
    }

    public function save(): void
    {
        auth()->user()->update($this->form->getState());

        Notification::make()
            ->title('Perfil atualizado')
            ->success()
            ->send();
    }
}