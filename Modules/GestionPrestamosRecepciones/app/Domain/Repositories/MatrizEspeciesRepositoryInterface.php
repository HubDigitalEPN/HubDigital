<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Repositories;

use Modules\GestionPrestamosRecepciones\Domain\Entities\MatrizEspecies;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\MatrizEspeciesId;

/**
 * Puerto de persistencia para el agregado {@see MatrizEspecies}.
 *
 * Implementado por un repositorio Eloquent en Infrastructure.
 */
interface MatrizEspeciesRepositoryInterface
{
    /** Genera un identificador nuevo para una matriz aún no persistida. */
    public function nextIdentity(): MatrizEspeciesId;

    /** Persiste (inserta o actualiza) la matriz y sus registros. */
    public function guardar(MatrizEspecies $matriz): void;

    /** Recupera la matriz por su identificador, o null si no existe. */
    public function buscarPorId(MatrizEspeciesId $matrizId): ?MatrizEspecies;

    /** Recupera la matriz asociada a una solicitud, o null si no existe. */
    public function buscarPorSolicitudId(string $solicitudId): ?MatrizEspecies;
}
