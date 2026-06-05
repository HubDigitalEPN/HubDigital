<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ActualizarRecordatoriosPrestamoEspecifico;

use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Entities\RecordatorioDevolucion;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\PrestamoNoEncontradoException;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\PrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\RecordatorioDevolucionRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;

final class ActualizarRecordatoriosPrestamoEspecificoHandler
{
    public function __construct(
        private readonly PrestamoRepositoryInterface $prestamoRepo,
        private readonly RecordatorioDevolucionRepositoryInterface $recordatorioRepo,
        private readonly TransactionManagerPort $transactionManager,
    ) {}

    public function handle(ActualizarRecordatoriosPrestamoEspecificoInput $input): ActualizarRecordatoriosPrestamoEspecificoOutput
    {
        $prestamoId = PrestamoId::fromString($input->prestamoId);
        $prestamo = $this->prestamoRepo->buscarPorId($prestamoId);

        if ($prestamo === null) {
            throw PrestamoNoEncontradoException::conId($prestamoId);
        }

        $fechaFin = $prestamo->fechaFin();

        $nuevosRecordatorios = array_map(
            fn (int $dias): RecordatorioDevolucion => RecordatorioDevolucion::programar(
                id: $this->recordatorioRepo->nextIdentity(),
                prestamoId: $prestamo->id(),
                fechaProgramada: $fechaFin->modify("-{$dias} days"),
                diasAntesVencimiento: $dias,
            ),
            $input->diasAntes,
        );

        $this->transactionManager->executeTransactional(function () use ($prestamo, $nuevosRecordatorios): void {
            $this->recordatorioRepo->eliminarPorPrestamo($prestamo->id());
            $this->recordatorioRepo->guardarTodos($nuevosRecordatorios);
        });

        return ActualizarRecordatoriosPrestamoEspecificoOutput::from($prestamo->id(), count($nuevosRecordatorios));
    }
}
