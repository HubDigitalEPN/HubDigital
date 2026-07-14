<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Repositories;

use Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudProrroga;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudProrrogaId;

/**
 * Puerto de persistencia para el agregado {@see SolicitudProrroga}.
 *
 * Implementado por un repositorio Eloquent en Infrastructure. El dominio depende
 * solo de esta interfaz.
 */
interface SolicitudProrrogaRepositoryInterface
{
    /** Persiste (inserta o actualiza) la solicitud de prórroga. */
    public function guardar(SolicitudProrroga $solicitud): void;

    /** Recupera la solicitud por su identificador, o null si no existe. */
    public function buscarPorId(SolicitudProrrogaId $id): ?SolicitudProrroga;

    /**
     * Recupera la solicitud de prórroga pendiente (en estado Solicitada) de un
     * préstamo, o null si no existe ninguna.
     */
    public function buscarPendientePorPrestamo(PrestamoId $prestamoId): ?SolicitudProrroga;

    /** Genera un identificador nuevo para una solicitud aún no persistida. */
    public function nextIdentity(): SolicitudProrrogaId;
}
