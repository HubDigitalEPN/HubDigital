<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services;

/**
 * Veredicto de calidad para un espécimen frente a las reglas de GBIF.
 *
 * `motivosExclusion` lista los criterios incumplidos (vacío si publicable).
 */
final readonly class ResultadoCalidadGbif
{
    /** @param string[] $motivosExclusion */
    public function __construct(
        public bool $esPublicable,
        public array $motivosExclusion,
    ) {}

    public static function publicable(): self
    {
        return new self(true, []);
    }

    /** @param string[] $motivos */
    public static function excluido(array $motivos): self
    {
        return new self(false, $motivos);
    }
}
