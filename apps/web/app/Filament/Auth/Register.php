<?php

namespace App\Filament\Auth;

use App\Enums\Locale;
use App\Models\User;
use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Model;

class Register extends BaseRegister
{
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            $this->getNameFormComponent(),
            $this->getUsernameFormComponent(),
            $this->getEmailFormComponent(),
            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
            $this->getBrowserLocaleFormComponent(),
        ]);
    }

    protected function getUsernameFormComponent(): TextInput
    {
        return TextInput::make('username')
            ->label('Usuário')
            ->required()
            ->unique(table: 'users', column: 'username')
            ->maxLength(255);
    }

    protected function getBrowserLocaleFormComponent(): Hidden
    {
        return Hidden::make('browser_locale')
            ->default(fn (): string => (string) request()->header('Accept-Language', ''))
            ->dehydrated()
            ->extraAttributes([
                // Prefer the browser UI language when JavaScript is available.
                'x-init' => 'if (window.navigator?.language) { $el.value = window.navigator.language }',
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeRegister(array $data): array
    {
        $raw = $data['browser_locale'] ?? null;

        if (! is_string($raw) || trim($raw) === '') {
            $raw = request()->header('Accept-Language');
        }

        unset($data['browser_locale']);

        $data['locale'] = Locale::detectBrowserLocale(is_string($raw) ? $raw : null)->value;

        return $data;
    }

    protected function handleRegistration(array $data): User
    {
        return User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => $data['password'],
            'locale' => $data['locale'] ?? Locale::default()->value,
        ]);
    }

    /**
     * Use the User model notification so App\Notifications\VerifyEmail
     * (content + Filament signed URL) is sent exactly once.
     */
    protected function sendEmailVerificationNotification(Model $user): void
    {
        if (! $user instanceof MustVerifyEmail) {
            return;
        }

        if ($user->hasVerifiedEmail()) {
            return;
        }

        $user->sendEmailVerificationNotification();
    }
}
