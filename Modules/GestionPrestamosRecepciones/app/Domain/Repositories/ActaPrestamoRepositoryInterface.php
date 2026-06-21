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

    /**
     * Lista actas para la bandeja del curador, aplicando filtros y orden.
     *
     * Devuelve filas planas (lectura) con los datos del acta y su solicitud.
     * El filtro por investigador se recibe ya resuelto a identificadores; si es
     * null no se aplica.
     *
     * @param array<int, string>|null $investigadorIds
     * @return array<int, array{actaId: string, numeroPrestamo: string, numeroSolicitud: string|null, investigadorId: string|null, estado: string, fecha: \DateTimeImmutable}>
     */
    public function listarParaBandeja(
        ?array $investigadorIds,
        string $estado,
        string $busquedaTexto,
        string $ordenCampo,
        string $ordenDireccion,
    ): array;

    /** Genera un identificador nuevo para un acta aún no persistida. */
    public function nextIdentity(): ActaPrestamoId;
}
