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

    /**
     * Lista préstamos para una bandeja, aplicando filtros y orden.
     *
     * Devuelve filas planas (lectura) con los datos del préstamo y el número de su
     * acta. El parámetro $investigadorId restringe a un investigador concreto (su
     * propia bandeja); $investigadorIds aplica el filtro por nombre ya resuelto del
     * curador. Ambos son null cuando no aplican.
     *
     * @param array<int, string>|null $investigadorIds
     * @return array<int, array{prestamoId: string, numeroPrestamo: string|null, investigadorId: string|null, estado: string, iniciadoEn: \DateTimeImmutable|null, fechaFin: \DateTimeImmutable|null}>
     */
    public function listarParaBandeja(
        ?string $investigadorId,
        ?array $investigadorIds,
        string $estado,
        string $busquedaTexto,
        string $ordenCampo,
        string $ordenDireccion,
    ): array;

    /** Genera un identificador nuevo para un préstamo aún no persistido. */
    public function nextIdentity(): PrestamoId;
}
