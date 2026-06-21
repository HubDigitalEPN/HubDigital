<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarRecordatoriosPrestamo;

use Modules\GestionPrestamosRecepciones\Domain\Entities\RecordatorioDevolucion;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\RecordatorioDevolucionRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;

/**
 * Manejador del caso de uso para consultar los recordatorios de devolución
 * programados de un préstamo.
 *
 * {@see ConsultarRecordatoriosPrestamoInput}
 * {@see ConsultarRecordatoriosPrestamoOutput}
 */
final class ConsultarRecordatoriosPrestamoHandler
{
    public function __construct(
        private readonly RecordatorioDevolucionRepositoryInterface $recordatorioRepo,
    ) {}

    /**
     * Ejecuta el caso de uso.
     */
    public function handle(ConsultarRecordatoriosPrestamoInput $input): ConsultarRecordatoriosPrestamoOutput
    {
        $recordatorios = $this->recordatorioRepo->listarPorPrestamo(
            PrestamoId::fromString($input->prestamoId),
        );

        $vista = array_map(
            static fn (RecordatorioDevolucion $r): RecordatorioVista => new RecordatorioVista(
                diasAntes: $r->diasAntesVencimiento(),
                fechaProgramada: $r->fechaProgramada(),
            ),
            $recordatorios,
        );

        return new ConsultarRecordatoriosPrestamoOutput(recordatorios: $vista);
    }
}
