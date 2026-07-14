<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\Support;

use Behat\Hook\BeforeScenario;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\PrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\RecordatorioDevolucionRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudProrrogaRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Adapters\PassThroughTransactionManagerAdapter;

/**
 * Compartido por los Contexts del flujo de prórroga. Resetea el estado in-memory por
 * escenario (en {@see ProrrogaTestState}) y resuelve handlers de forma diferida
 * re-registrando esos repos en el contenedor, lo que vence el clobbering producido por
 * los constructores de otros Contexts de la misma suite.
 */
trait EstadoProrrogaCompartido
{
    #[BeforeScenario]
    public function reiniciarEstadoProrrogaCompartido(): void
    {
        ProrrogaTestState::reiniciar();
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $handler
     * @return T
     */
    protected function resolverConReposCompartidos(string $handler): object
    {
        self::$app->instance(PrestamoRepositoryInterface::class, ProrrogaTestState::$prestamoRepo);
        self::$app->instance(SolicitudProrrogaRepositoryInterface::class, ProrrogaTestState::$solicitudRepo);
        self::$app->instance(RecordatorioDevolucionRepositoryInterface::class, ProrrogaTestState::$recordatorioRepo);
        self::$app->instance(TransactionManagerPort::class, new PassThroughTransactionManagerAdapter);
        self::$app->instance(EventPublisherPort::class, ProrrogaTestState::$publisher);

        return self::$app->make($handler);
    }
}
