<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ResolverCodigoQr;

/**
 * Ficha digital mínima que se devuelve al resolver un QR desde el móvil: los
 * datos que un curador o investigador necesita verificar en campo sin abrir el
 * sistema completo.
 */
final readonly class ResolverCodigoQrOutput
{
    public function __construct(
        public string $id,
        public string $codigoCatalogo,
        public ?string $taxonId,
        public string $localidad,
        public string $fechaColecta,
        public string $colector,
        public string $estado,
        public ?string $entidadDepositanteId,
    ) {}
}
