<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Repositories;

use Modules\GestionPrestamosRecepciones\Domain\Entities\VerificacionEspecimenes;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoVerificacion;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\VerificacionEspecimenesId;

/**
 * Puerto de persistencia para el agregado {@see VerificacionEspecimenes}.
 *
 * Cubre las verificaciones de especímenes en sus distintos momentos (recepción por
 * el investigador, devolución verificada por el curador). Implementado por un
 * repositorio Eloquent en Infrastructure.
 */
interface VerificacionEspecimenesRepositoryInterface
{
    /** Persiste (inserta o actualiza) la verificación de especímenes. */
    public function guardar(VerificacionEspecimenes $verificacion): void;

    /** Recupera la verificación de un préstamo para un tipo dado, o null si no existe. */
    public function buscarPorPrestamoYTipo(PrestamoId $prestamoId, TipoVerificacion $tipo): ?VerificacionEspecimenes;

    /** Genera un identificador nuevo para una verificación aún no persistida. */
    public function nextIdentity(): VerificacionEspecimenesId;
}
