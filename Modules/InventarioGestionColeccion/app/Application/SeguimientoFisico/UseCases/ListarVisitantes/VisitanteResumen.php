<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarVisitantes;

final readonly class VisitanteResumen
{
    public function __construct(
        public string $id,
        public string $nombre,
        public ?string $contacto,
        public int $versionAcceso,
        public bool $puedeReubicar,
        public \DateTimeImmutable $registradoEn,
    ) {}
}
