<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts;

use Behat\Behat\Context\Context;
use Behat\Hook\AfterScenario;
use Behat\Hook\BeforeScenario;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Modules\GestionPrestamosRecepciones\Application\Ports\ExtraccionDatosDocumentoPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\NotificacionCuratoriaPort;
use Modules\GestionPrestamosRecepciones\Tests\Behat\Fakes\FakeExtraccionDatosDocumentoAdapter;
use Modules\GestionPrestamosRecepciones\Tests\Behat\Fakes\FakeNotificacionCuratoriaAdapter;

abstract class BaseContext implements Context
{
    protected static mixed $app = null;

    private static bool $resetDone = false;

    private static function bootApp(): void
    {
        if (static::$app !== null) {
            return;
        }

        // dirname 5 sube: Contexts/ -> Behat/ -> tests/ -> GestionPrestamosRecepciones/ -> Modules/ -> [raíz]
        static::$app = require dirname(__DIR__, 5).'/bootstrap/app.php';
        static::$app->make(ConsoleKernel::class)->bootstrap();

        static::$app->bind(ExtraccionDatosDocumentoPort::class, FakeExtraccionDatosDocumentoAdapter::class);
        static::$app->bind(NotificacionCuratoriaPort::class, FakeNotificacionCuratoriaAdapter::class);
    }

    #[BeforeScenario]
    public function resetDatabase(): void
    {
        static::bootApp();

        if (static::$resetDone) {
            return;
        }

        static::$resetDone = true;
        static::$app->make(ConsoleKernel::class)->call('migrate:reset', ['--force' => true]);
        static::$app->make(ConsoleKernel::class)->call('migrate', ['--force' => true]);
    }

    #[AfterScenario]
    public function clearResetFlag(): void
    {
        static::$resetDone = false;
    }

    /** @template T @param class-string<T> $abstract @return T */
    protected function make(string $abstract): mixed
    {
        static::bootApp();

        return static::$app->make($abstract);
    }
}
