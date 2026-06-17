<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Repositories;

use Modules\GestionPrestamosRecepciones\Domain\Entities\Prestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ActaPrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;

/**
 * Puerto de persistencia para el agregado {@see Prestamo}.
 *
 * Implementado por un repositorio Eloquent en Infrastructure. El dominio depende
 * solo de esta interfaz.
 */
interface PrestamoRepositoryInterface
{
    /** Persiste (inserta o actualiza) el préstamo. */
    public function guardar(Prestamo $prestamo): void;

    /** Recupera el préstamo por su identificador, o null si no existe. */
    public function buscarPorId(PrestamoId $id): ?Prestamo;

    /** Recupera el préstamo asociado a un acta, o null si no existe. */
    public function buscarPorActaId(ActaPrestamoId $actaId): ?Prestamo;

    /**
     * Lista los préstamos en estado activo (usado para evaluar plazos de devolución).
     *
     * @return list<Prestamo>
     */
    public function listarActivos(): array;

    /** Genera un identificador nuevo para un préstamo aún no persistido. */
    public function nextIdentity(): PrestamoId;
}
