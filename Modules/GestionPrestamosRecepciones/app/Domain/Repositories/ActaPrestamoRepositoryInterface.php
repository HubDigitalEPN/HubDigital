<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Repositories;

use Modules\GestionPrestamosRecepciones\Domain\Entities\ActaPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ActaPrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudPrestamoId;

/**
 * Puerto de persistencia para el agregado {@see ActaPrestamo}.
 *
 * Implementado por un repositorio Eloquent en Infrastructure.
 */
interface ActaPrestamoRepositoryInterface
{
    /** Persiste (inserta o actualiza) el acta. */
    public function guardar(ActaPrestamo $acta): void;

    /** Recupera el acta por su identificador, o null si no existe. */
    public function buscarPorId(ActaPrestamoId $id): ?ActaPrestamo;

    /** Recupera el acta asociada a una solicitud, o null si no existe. */
    public function buscarPorSolicitudId(SolicitudPrestamoId $solicitudId): ?ActaPrestamo;

    /** Genera un identificador nuevo para un acta aún no persistida. */
    public function nextIdentity(): ActaPrestamoId;
}
