<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\IniciarPrestamo;

use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Entities\Prestamo;
use Modules\GestionPrestamosRecepciones\Domain\Entities\RecordatorioDevolucion;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\ActaPrestamoNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\ActaPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\ConfiguracionGlobalRecordatoriosRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\PrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\RecordatorioDevolucionRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ActaPrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudPrestamoId;
use RuntimeException;

final class IniciarPrestamoHandler
{
    public function __construct(
        private readonly ActaPrestamoRepositoryInterface $actaRepo,
        private readonly SolicitudPrestamoRepositoryInterface $solicitudRepo,
        private readonly PrestamoRepositoryInterface $prestamoRepo,
        private readonly ConfiguracionGlobalRecordatoriosRepositoryInterface $configRepo,
        private readonly RecordatorioDevolucionRepositoryInterface $recordatorioRepo,
        private readonly EventPublisherPort $publisher,
        private readonly TransactionManagerPort $transactionManager,
    ) {}

    public function handle(IniciarPrestamoInput $input): IniciarPrestamoOutput
    {
        $actaId = ActaPrestamoId::fromString($input->actaPrestamoId);
        $acta = $this->actaRepo->buscarPorId($actaId);

        if ($acta === null) {
            throw ActaPrestamoNoEncontradaException::conId($actaId);
        }

        $solicitudId = SolicitudPrestamoId::fromString($input->solicitudId);
        $solicitud = $this->solicitudRepo->buscarPorId($solicitudId);

        if ($solicitud === null) {
            throw new RuntimeException("Solicitud no encontrada: {$input->solicitudId}");
        }

        $prestamo = Prestamo::iniciar(
            id: $this->prestamoRepo->nextIdentity(),
            actaPrestamoId: $actaId,
            investigadorId: $solicitud->investigadorId(),
            alcancePrestamo: $acta->alcancePrestamo(),
            iniciadoEn: $acta->fechaInicio(),
            fechaFin: $acta->fechaFin(),
        );

        $configuracion = $this->configRepo->obtenerUnica();

        $recordatorios = [];
        if ($configuracion !== null) {
            $fechaFin = $acta->fechaFin();
            $recordatorios = array_map(
                fn (int $dias): RecordatorioDevolucion => RecordatorioDevolucion::programar(
                    id: $this->recordatorioRepo->nextIdentity(),
                    prestamoId: $prestamo->id(),
                    fechaProgramada: $fechaFin->modify("-{$dias} days"),
                    diasAntesVencimiento: $dias,
                ),
                $configuracion->diasAntes(),
            );
        }

        $this->transactionManager->executeTransactional(function () use ($prestamo, $recordatorios): void {
            $this->prestamoRepo->guardar($prestamo);
            if ($recordatorios !== []) {
                $this->recordatorioRepo->guardarTodos($recordatorios);
            }
            foreach ($prestamo->pullEvents() as $event) {
                $this->publisher->publish($event);
            }
        });

        return IniciarPrestamoOutput::fromPrestamo($prestamo);
    }
}
