<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    use \Sushi\Sushi;

    protected $rows = [
        ['code' => 'BR', 'name' => 'Brasil'],
        ['code' => 'US', 'name' => 'United States'],
        ['code' => 'PT', 'name' => 'Portugal'],
        ['code' => 'CA', 'name' => 'Canada'],
        ['code' => 'AR', 'name' => 'Argentina'],
        ['code' => 'DE', 'name' => 'Germany'],
        ['code' => 'ES', 'name' => 'Spain'],
        ['code' => 'FR', 'name' => 'France'],
        ['code' => 'IT', 'name' => 'Italy'],
        ['code' => 'GB', 'name' => 'United Kingdom'],
    ];
}