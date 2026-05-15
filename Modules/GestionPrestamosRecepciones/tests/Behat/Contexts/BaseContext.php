<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts;

use Behat\Behat\Context\Context;
use Behat\Hook\AfterScenario;
use Behat\Hook\BeforeScenario;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Modules\GestionPrestamosRecepciones\Application\Ports\ExtraccionDatosDocumentoPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\NotificacionCuratoriaPort;
use Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\Fakes\FakeExtraccionDatosDocumentoAdapter;
use Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\Fakes\FakeNotificacionCuratoriaAdapter;

abstract class BaseContext implements Context
{
    private static mixed $app = null;

    private static bool $resetDone = false;

    private static function bootApp(): void
    {
        if (self::$app !== null) {
            return;
        }

        // dirname 5 sube: Contexts/ -> Behat/ -> tests/ -> GestionPrestamosRecepciones/ -> Modules/ -> [raíz]
        self::$app = require dirname(__DIR__, 5).'/bootstrap/app.php';
        self::$app->make(ConsoleKernel::class)->bootstrap();

        self::$app->bind(ExtraccionDatosDocumentoPort::class, FakeExtraccionDatosDocumentoAdapter::class);
        self::$app->bind(NotificacionCuratoriaPort::class, FakeNotificacionCuratoriaAdapter::class);
    }

    #[BeforeScenario]
    public function resetDatabase(): void
    {
        self::bootApp();

        if (self::$resetDone) {
            return;
        }

        self::$resetDone = true;
        self::$app->make(ConsoleKernel::class)->call('migrate:reset', ['--force' => true]);
        self::$app->make(ConsoleKernel::class)->call('migrate', ['--force' => true]);
    }

    #[AfterScenario]
    public function clearResetFlag(): void
    {
        self::$resetDone = false;
    }

    /** @template T @param class-string<T> $abstract @return T */
    protected function make(string $abstract): mixed
    {
        self::bootApp();

        return self::$app->make($abstract);
    }

    protected function make(string $abstract): mixed
    {
        return static::$app->make($abstract);
    }
}
