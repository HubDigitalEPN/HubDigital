<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts;

use Behat\Behat\Context\Context;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use PHPUnit\TextUI\Configuration\Builder as PHPUnitConfigurationBuilder;

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

        self::inicializarConfiguracionPHPUnit();
    }

    /**
     * Las aserciones de PHPUnit\Framework\Assert (usadas en los Contexts) exportan
     * el valor real al fallar, lo que invoca su Registry de configuración. Bajo Behat
     * ese Registry nunca se inicializa y el fallo de aserción se convierte en un
     * "Fatal error: assert(self::$instance instanceof Configuration)" que oculta el
     * mensaje real. Inicializarlo con la configuración por defecto restaura los
     * mensajes legibles ("se esperaba X, se obtuvo Y").
     */
    private static function inicializarConfiguracionPHPUnit(): void
    {
        if (! class_exists(PHPUnitConfigurationBuilder::class)) {
            return;
        }

        try {
            (new PHPUnitConfigurationBuilder)->build(['--no-configuration']);
        } catch (\Throwable) {
            // Si la inicialización falla, no debe romper la suite de Behat.
        }
    }

    /** @template T @param class-string<T> $abstract @return T */
    protected function make(string $abstract): mixed
    {
        self::bootApp();

        return self::$app->make($abstract);
    }
}
