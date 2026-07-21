<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Support\Geo\Providers\GeoServiceProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    GeoServiceProvider::class,
];
