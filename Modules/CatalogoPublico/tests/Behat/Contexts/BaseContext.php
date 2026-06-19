<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Tests\Behat\Contexts;

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

        // dirname 5: Contexts/ -> Behat/ -> tests/ -> CatalogoPublico/ -> Modules/ -> [raiz]
        self::$app = require dirname(__DIR__, 5).'/bootstrap/app.php';
        self::$app->make(Kernel::class)->bootstrap();

        // Las aserciones de PHPUnit\Framework\Assert exportan el valor real al fallar,
        // lo que invoca su Registry de configuración. Bajo Behat ese Registry nunca se
        // inicializa y el fallo se convierte en un "Fatal error: assert(self::$instance
        // instanceof Configuration)" que oculta el mensaje real. Inicializarlo restaura
        // los mensajes legibles ("se esperaba X, se obtuvo Y").
        PhpUnitRegistry::init(
            (new CliArgumentsBuilder)->fromParameters([]),
            DefaultConfiguration::create(),
        );
    }

    /**
     * @template T
     *
     * @param  class-string<T>  $abstract
     * @return T
     */
    protected function make(string $abstract): mixed
    {
        if (self::$app === null) {
            self::bootstrapLaravel();
        }

        return static::$app->make($abstract);
    }
}
