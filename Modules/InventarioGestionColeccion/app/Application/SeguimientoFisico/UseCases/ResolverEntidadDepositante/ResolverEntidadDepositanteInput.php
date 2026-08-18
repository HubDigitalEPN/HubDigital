<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ResolverEntidadDepositante;

/**
 * Quién depositó el material, tal como lo conoce el módulo de recepciones.
 *
 * En primitivos: cruza desde GestionPrestamosRecepciones.
 */
final readonly class ResolverEntidadDepositanteInput
{
    public function __construct(
        public string $nombrePersona,
        public ?string $institucion = null,
        public ?string $email = null,
        public ?string $cargo = null,
    ) {}
}
