<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Contexts;

use Behat\Behat\Context\Context;
use Behat\Hook\BeforeSuite;
use Illuminate\Contracts\Console\Kernel;
use PHPUnit\TextUI\CliArguments\Builder as CliArgumentsBuilder;
use PHPUnit\TextUI\Configuration\Registry as PhpUnitRegistry;
use PHPUnit\TextUI\XmlConfiguration\DefaultConfiguration;

abstract class BaseContext implements Context
{
    protected static mixed $app = null;

    #[BeforeSuite]
    public static function bootstrapLaravel(): void
    {
        if (self::$app !== null) {
            return;
        }

        // dirname 5 sube: Contexts/ -> Behat/ -> tests/ -> InventarioGestionColeccion/ -> Modules/ -> [raiz]
        self::$app = require dirname(__DIR__, 5).'/bootstrap/app.php';
        self::$app->make(Kernel::class)->bootstrap();

        PhpUnitRegistry::init(
            (new CliArgumentsBuilder)->fromParameters([]),
            DefaultConfiguration::create(),
        );
    }

    protected function make(string $abstract): mixed
    {
        if (self::$app === null) {
            self::bootstrapLaravel();
        }

        return self::$app->make($abstract);
    }
}
