<?php

use App\Providers\AppServiceProvider;
use App\Providers\DomainServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\ClientPanelProvider;

return [
    DomainServiceProvider::class,
    AppServiceProvider::class,
    AdminPanelProvider::class,
    ClientPanelProvider::class,
];
