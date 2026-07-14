<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Sushi\Sushi;

class Country extends Model
{
    use Sushi;

    protected $rows = [
        ['code' => 'BR', 'name' => 'Brasil', 'currency' => 'BRL'],
        ['code' => 'US', 'name' => 'United States', 'currency' => 'USD'],
        ['code' => 'PT', 'name' => 'Portugal', 'currency' => 'EUR'],
        ['code' => 'CA', 'name' => 'Canada', 'currency' => 'CAD'],
        ['code' => 'AR', 'name' => 'Argentina', 'currency' => 'ARS'],
        ['code' => 'DE', 'name' => 'Germany', 'currency' => 'EUR'],
        ['code' => 'ES', 'name' => 'Spain', 'currency' => 'EUR'],
        ['code' => 'FR', 'name' => 'France', 'currency' => 'EUR'],
        ['code' => 'IT', 'name' => 'Italy', 'currency' => 'EUR'],
        ['code' => 'GB', 'name' => 'United Kingdom', 'currency' => 'GBP'],
    ];
}
