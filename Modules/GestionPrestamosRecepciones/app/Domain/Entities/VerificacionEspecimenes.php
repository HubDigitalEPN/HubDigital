<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Entities;

use InvalidArgumentException;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ObservacionEspecimen;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ResultadoVerificacion;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoVerificacion;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\VerificacionEspecimenesId;

/**
 * Entidad que registra una verificación de especímenes sobre un préstamo.
 *
 * Generaliza el patrón "cambiar un estado y adjuntar novedades" para los dos
 * momentos del ciclo de vida (ver {@see TipoVerificacion}): la recepción por parte
 * del investigador y la devolución verificada por el curador al cerrar el préstamo.
 *
 * Captura el resultado de la inspección (sin / con novedades), la lista de novedades
 * por espécimen y una nota general opcional para observaciones no atadas a un
 * espécimen concreto. Invariante: si hay novedades debe registrarse al menos una
 * novedad (por espécimen o en la nota general).
 *
 * Construir vía {@see self::registrar()}; rehidratar vía {@see self::reconstituir()}.
 */
final class VerificacionEspecimenes
{
    /** @param list<ObservacionEspecimen> $observaciones */
    private function __construct(
        private readonly VerificacionEspecimenesId $id,
        private readonly PrestamoId $prestamoId,
        private readonly TipoVerificacion $tipo,
        private readonly ResultadoVerificacion $resultado,
        private readonly array $observaciones,
        private readonly ?string $observacionGeneral,
    ) {}

    /**
     * Registra una verificación de especímenes.
     *
     * @param  list<ObservacionEspecimen>  $observaciones
     *
     * @throws InvalidArgumentException Si hay novedades sin ninguna novedad registrada.
     */
    public static function registrar(
        VerificacionEspecimenesId $id,
        PrestamoId $prestamoId,
        TipoVerificacion $tipo,
        ResultadoVerificacion $resultado,
        array $observaciones,
        ?string $observacionGeneral = null,
    ): self {
        $observacionGeneral = self::normalizarNota($observacionGeneral);

        if (
            $resultado === ResultadoVerificacion::ConNovedades
            && count($observaciones) === 0
            && $observacionGeneral === null
        ) {
            throw new InvalidArgumentException(
                'Debe registrar al menos una novedad (por espécimen o en la nota general) cuando el resultado es con novedades.'
            );
        }

        return new self(
            id: $id,
            prestamoId: $prestamoId,
            tipo: $tipo,
            resultado: $resultado,
            observaciones: $observaciones,
            observacionGeneral: $observacionGeneral,
        );
    }

    /**
     * Reconstitución desde persistencia — no valida invariantes.
     *
     * @param  list<ObservacionEspecimen>  $observaciones
     */
    public static function reconstituir(
        VerificacionEspecimenesId $id,
        PrestamoId $prestamoId,
        TipoVerificacion $tipo,
        ResultadoVerificacion $resultado,
        array $observaciones,
        ?string $observacionGeneral = null,
    ): self {
        return new self(
            id: $id,
            prestamoId: $prestamoId,
            tipo: $tipo,
            resultado: $resultado,
            observaciones: $observaciones,
            observacionGeneral: self::normalizarNota($observacionGeneral),
        );
    }

    public function id(): VerificacionEspecimenesId
    {
        return $this->id;
    }

    public function prestamoId(): PrestamoId
    {
        return $this->prestamoId;
    }

    public function tipo(): TipoVerificacion
    {
        return $this->tipo;
    }

    public function resultado(): ResultadoVerificacion
    {
        return $this->resultado;
    }

    /** @return list<ObservacionEspecimen> */
    public function observaciones(): array
    {
        return $this->observaciones;
    }

    public function observacionGeneral(): ?string
    {
        return $this->observacionGeneral;
    }

    private static function normalizarNota(?string $nota): ?string
    {
        if ($nota === null) {
            return null;
        }

        $nota = trim($nota);

        return $nota === '' ? null : $nota;
    }
}
