<?php

namespace Modules\InventarioGestionColeccion\Tests\Behat\Contexts;

use Behat\Behat\Context\Context;
use Illuminate\Contracts\Console\Kernel;

class InventarioGestionColeccionContext implements Context
{
    protected static $app;

    /** @BeforeSuite */
    public static function bootstrapLaravel(): void
    {
        $bootstrap = dirname(__DIR__, 5) . '/bootstrap/app.php';

        self::$app = require $bootstrap;
        self::$app->make(Kernel::class)->bootstrap();
    }
}
