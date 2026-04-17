<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts;

use Behat\Behat\Context\Context;
use Behat\Hook\BeforeSuite;
use Illuminate\Contracts\Console\Kernel;

abstract class BaseContext implements Context
{
    protected static mixed $app = null;

    #[BeforeSuite]
    public static function bootstrapLaravel(): void
    {
        if (self::$app !== null) {
            return;
        }

        // __DIR__ siempre resuelve al directorio de este archivo (tests/Behat/Contexts),
        // incluso cuando el método es invocado desde una subclase.
        // dirname 5 sube: Contexts/ -> Behat/ -> tests/ -> GestionPrestamosRecepciones/ -> Modules/ -> [raiz]
        self::$app = require dirname(__DIR__, 5) . '/bootstrap/app.php';
        self::$app->make(Kernel::class)->bootstrap();
    }
}
