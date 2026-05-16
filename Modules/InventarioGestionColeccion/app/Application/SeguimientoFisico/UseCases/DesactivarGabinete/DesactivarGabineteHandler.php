<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\DesactivarGabinete;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\GabineteRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\GabineteId;

final class DesactivarGabineteHandler
{
    public function __construct(
        private readonly GabineteRepository $gabineteRepo,
    ) {}

    public function handle(DesactivarGabineteInput $input): DesactivarGabineteOutput
    {
        $gabinete = $this->gabineteRepo->buscarPorId(GabineteId::desde($input->gabineteId));

        if ($gabinete === null) {
            throw new \DomainException('Gabinete no encontrado.');
        }

        $gabinete->desactivar();
        $this->gabineteRepo->guardar($gabinete);

        return new DesactivarGabineteOutput;
    }
}
