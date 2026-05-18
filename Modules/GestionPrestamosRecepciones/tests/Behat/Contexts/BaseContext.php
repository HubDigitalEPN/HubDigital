<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts;

use Behat\Behat\Context\Context;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;

abstract class BaseContext implements Context
{
    protected static mixed $app = null;

    protected static function bootApp(): void
    {
        if (self::$app !== null) {
            return;
        }

        // dirname 5 sube: Contexts/ -> Behat/ -> tests/ -> GestionPrestamosRecepciones/ -> Modules/ -> [raíz]
        self::$app = require dirname(__DIR__, 5).'/bootstrap/app.php';
        self::$app->make(ConsoleKernel::class)->bootstrap();
    }

    /** @template T @param class-string<T> $abstract @return T */
    protected function make(string $abstract): mixed
    {
        self::bootApp();

        return self::$app->make($abstract);
    }
}
