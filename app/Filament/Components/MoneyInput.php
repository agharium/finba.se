<?php

namespace App\Filament\Components;

use Filament\Forms\Components\TextInput;

class MoneyInput extends TextInput
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->prefix('R$')
            ->inputMode('numeric')
            ->extraInputAttributes([
                'x-on:input' => <<<'JS'
                    let digits = $event.target.value.replace(/\D/g, '');

                    if (! digits) {
                        $event.target.value = '';
                        return;
                    }

                    $event.target.value = (Number(digits) / 100).toLocaleString('pt-BR', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                    });
                JS,
            ])
            ->formatStateUsing(fn ($state) => filled($state)
                ? number_format((float) $state, 2, ',', '.')
                : null
            )
            ->dehydrateStateUsing(function ($state) {
                if (blank($state)) {
                    return null;
                }

                return str($state)
                    ->replace('.', '')
                    ->replace(',', '.')
                    ->toString();
            })
            ->rule(function () {
                return function (string $attribute, mixed $value, \Closure $fail): void {
                    $normalized = str($value)
                        ->replace('.', '')
                        ->replace(',', '.')
                        ->toString();

                    if (! is_numeric($normalized)) {
                        $fail('O valor informado é inválido.');
                    }
                };
            });
    }
}