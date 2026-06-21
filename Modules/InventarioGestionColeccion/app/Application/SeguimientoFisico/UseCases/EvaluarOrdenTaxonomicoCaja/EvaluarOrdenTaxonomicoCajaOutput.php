<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\EvaluarOrdenTaxonomicoCaja;

/**
 * DTO de salida que indica si la evaluación generó una alerta y, en su caso, su tipo e identificador.
 */
final readonly class EvaluarOrdenTaxonomicoCajaOutput
{
    private function __construct(
        public string $cajaId,
        public string $estadoCaja,
        public bool $alertaGenerada,
        public ?string $tipoAlerta,
        public ?string $alertaId,
    ) {}

    public static function fromPrimitives(array $data): self
    {
        return new self(
            cajaId: $data['cajaId'],
            estadoCaja: $data['estadoCaja'],
            alertaGenerada: $data['alertaGenerada'],
            tipoAlerta: $data['tipoAlerta'],
            alertaId: $data['alertaId'],
        );
    }
}
