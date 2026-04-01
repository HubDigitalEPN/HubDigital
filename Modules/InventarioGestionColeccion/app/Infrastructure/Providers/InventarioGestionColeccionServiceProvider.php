<?php

namespace Modules\InventarioGestionColeccion\Infrastructure\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;

class InventarioGestionColeccionServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'InventarioGestionColeccion';

    protected string $nameLower = 'inventariogestioncoleccion';

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];
}
