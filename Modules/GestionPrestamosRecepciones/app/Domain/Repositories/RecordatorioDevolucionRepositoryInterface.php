<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Repositories;

use Modules\GestionPrestamosRecepciones\Domain\Entities\RecordatorioDevolucion;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\RecordatorioDevolucionId;

/**
 * Puerto de persistencia para el agregado {@see RecordatorioDevolucion}.
 *
 * Implementado por un repositorio Eloquent en Infrastructure.
 */
interface RecordatorioDevolucionRepositoryInterface
{
    /** Persiste (inserta o actualiza) un recordatorio. */
    public function guardar(RecordatorioDevolucion $recordatorio): void;

    /**
     * Persiste un lote de recordatorios en una sola operación.
     *
     * @param  list<RecordatorioDevolucion>  $recordatorios
     */
    public function guardarTodos(array $recordatorios): void;

    /**
     * Lista todos los recordatorios programados para un préstamo.
     *
     * @return list<RecordatorioDevolucion>
     */
    public function listarPorPrestamo(PrestamoId $prestamoId): array;

    /** Elimina todos los recordatorios de un préstamo (p. ej. al reprogramar). */
    public function eliminarPorPrestamo(PrestamoId $prestamoId): void;

    /** Genera un identificador nuevo para un recordatorio aún no persistido. */
    public function nextIdentity(): RecordatorioDevolucionId;
}
