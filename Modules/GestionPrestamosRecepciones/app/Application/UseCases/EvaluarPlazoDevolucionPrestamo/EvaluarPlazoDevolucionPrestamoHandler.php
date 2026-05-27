<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\EvaluarPlazoDevolucionPrestamo;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\PrestamoNoEncontradoException;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\ConfiguracionGlobalRecordatoriosRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\PrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;

final class EvaluarPlazoDevolucionPrestamoHandler
{
    public function __construct(
        private readonly PrestamoRepositoryInterface $prestamoRepo,
        private readonly ConfiguracionGlobalRecordatoriosRepositoryInterface $configRepo,
        private readonly TransactionManagerPort $transactionManager,
        private readonly EventPublisherPort $publisher,
    ) {}

    public function handle(EvaluarPlazoDevolucionPrestamoInput $input): EvaluarPlazoDevolucionPrestamoOutput
    {
        $prestamoId = PrestamoId::fromString($input->prestamoId);

        $prestamo = $this->prestamoRepo->buscarPorId($prestamoId);

        if ($prestamo === null) {
            throw PrestamoNoEncontradoException::conId($prestamoId);
        }

        $config = $this->configRepo->obtenerUnica();

        if ($config === null) {
            return EvaluarPlazoDevolucionPrestamoOutput::from(false, null);
        }

        $ahora = new DateTimeImmutable;

        $estadoRecordatorio = $prestamo->evaluarEstadoRecordatorio(
            diasAntes: $config->diasAntes(),
            ahora: $ahora,
        );

        if ($estadoRecordatorio === null) {
            return EvaluarPlazoDevolucionPrestamoOutput::from(false, null);
        }

        $prestamo->registrarEnvioDeRecordatorio(
            estado: $estadoRecordatorio,
            ahora: $ahora,
        );

        $this->transactionManager->executeTransactional(function () use ($prestamo): void {
            $this->prestamoRepo->guardar($prestamo);
            foreach ($prestamo->pullEvents() as $event) {
                $this->publisher->publish($event);
            }
        });

        return EvaluarPlazoDevolucionPrestamoOutput::from(true, $estadoRecordatorio);
    }
}
