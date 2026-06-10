<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Repositories;

use Modules\GestionPrestamosRecepciones\Domain\Entities\VerificacionEntregaPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\VerificacionEntregaId;

/**
 * Puerto de persistencia para el agregado {@see VerificacionEntregaPrestamo}.
 *
 * Implementado por un repositorio Eloquent en Infrastructure.
 */
interface VerificacionEntregaPrestamoRepositoryInterface
{
    /** Persiste (inserta o actualiza) la verificación de entrega. */
    public function guardar(VerificacionEntregaPrestamo $verificacion): void;

    /** Recupera la verificación asociada a un préstamo, o null si no existe. */
    public function buscarPorPrestamoId(PrestamoId $prestamoId): ?VerificacionEntregaPrestamo;

    /** Genera un identificador nuevo para una verificación aún no persistida. */
    public function nextIdentity(): VerificacionEntregaId;
}
