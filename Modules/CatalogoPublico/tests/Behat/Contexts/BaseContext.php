<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Tests\Behat\Contexts;

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

        // dirname 5: Contexts/ -> Behat/ -> tests/ -> CatalogoPublico/ -> Modules/ -> [raiz]
        self::$app = require dirname(__DIR__, 5) . '/bootstrap/app.php';
        self::$app->make(Kernel::class)->bootstrap();
    }

    /**
     * @template T
     * @param class-string<T> $abstract
     * @return T
     */
    protected function make(string $abstract): mixed
    {
        return static::$app->make($abstract);
    }
}
