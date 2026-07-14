<?php

namespace App\Filament\Components;

use App\Support\MoneyFormatter;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Number;

class MoneyInput extends TextInput
{
    protected function setUp(): void
    {
        parent::setUp();

        $locale = MoneyFormatter::inputLocale();

        $this
            ->prefix(fn (): string => MoneyFormatter::symbol())
            ->inputMode('numeric')
            ->extraInputAttributes([
                'x-on:input' => <<<JS
                    let digits = \$event.target.value.replace(/\\D/g, '');

                    if (! digits) {
                        \$event.target.value = '';
                        return;
                    }

                    \$event.target.value = (Number(digits) / 100).toLocaleString('{$locale}', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                    });
                JS,
            ])
            ->formatStateUsing(fn ($state) => filled($state)
                ? Number::format((float) $state, precision: 2, locale: MoneyFormatter::numberLocale())
                : null
            )
            ->dehydrateStateUsing(function ($state) {
                if (blank($state)) {
                    return null;
                }

                $digits = preg_replace('/\D/', '', (string) $state);

                if ($digits === '' || $digits === null) {
                    return null;
                }

                return number_format(((int) $digits) / 100, 2, '.', '');
            })
            ->rule(function () {
                return function (string $attribute, mixed $value, \Closure $fail): void {
                    $digits = preg_replace('/\D/', '', (string) $value);

                    if ($digits === '' || $digits === null || ! is_numeric($digits)) {
                        $fail('O valor informado é inválido.');
                    }
                };
            });
    }
}
