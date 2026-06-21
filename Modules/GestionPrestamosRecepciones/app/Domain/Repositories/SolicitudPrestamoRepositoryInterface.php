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

    /**
     * Lista solicitudes para la bandeja del curador, aplicando filtros y orden.
     *
     * Excluye los borradores. El filtro por investigador se recibe ya resuelto a
     * identificadores; si es null no se aplica.
     *
     * @param array<int, string>|null $investigadorIds
     * @return array<int, array{solicitudId: string, numeroSolicitud: string|null, tituloEstudio: string|null, investigadorId: string|null, estado: string, fecha: \DateTimeImmutable}>
     */
    public function listarParaBandeja(
        ?array $investigadorIds,
        string $estado,
        string $busquedaTexto,
        string $ordenCampo,
        string $ordenDireccion,
    ): array;

    /**
     * Lista las solicitudes de un investigador (incluye borradores), con el estado
     * del acta asociada si existe. Proyección de lectura para "Mis solicitudes".
     *
     * @return array<int, array{solicitudId: string, numeroSolicitud: string|null, tituloEstudio: string|null, estado: string, fecha: \DateTimeImmutable, actaId: string|null, actaEstado: string|null}>
     */
    public function listarPorInvestigador(
        string $investigadorId,
        string $estado,
        string $busquedaTexto,
        string $ordenCampo,
        string $ordenDireccion,
    ): array;
}
