<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Repositories;

use Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudPrestamoId;

/**
 * Puerto de persistencia para el agregado {@see SolicitudPrestamo}.
 *
 * La implementación concreta vive en Infrastructure (repositorio Eloquent) y se
 * enlaza en el ServiceProvider del módulo. El dominio depende únicamente de esta
 * interfaz.
 */
interface SolicitudPrestamoRepositoryInterface
{
    /** Persiste (inserta o actualiza) la solicitud. */
    public function guardar(SolicitudPrestamo $solicitud): void;

    /** Recupera la solicitud por su identificador, o null si no existe. */
    public function buscarPorId(SolicitudPrestamoId $id): ?SolicitudPrestamo;

    /** Genera un identificador nuevo para una solicitud aún no persistida. */
    public function nextIdentity(): SolicitudPrestamoId;

    /**
     * Proyección de lectura: resuelve el código externo legible de cada ítem.
     *
     * @param  list<string>  $itemPrestamoIds  Identificadores de ítems de préstamo.
     * @return array<string, string>  Mapa [itemPrestamoId => códigoExterno] (omite ítems inexistentes).
     */
    public function mapaCodigosExternos(array $itemPrestamoIds): array;
}
