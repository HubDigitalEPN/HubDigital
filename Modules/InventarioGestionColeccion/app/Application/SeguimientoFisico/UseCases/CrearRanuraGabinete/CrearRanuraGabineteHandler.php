<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\CrearRanuraGabinete;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\RanuraGabinete;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\GabineteRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\RanuraGabineteRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\GabineteId;

final class CrearRanuraGabineteHandler
{
    public function __construct(
        private readonly RanuraGabineteRepository $ranuraRepo,
        private readonly GabineteRepository $gabineteRepo,
    ) {}

    public function handle(CrearRanuraGabineteInput $input): CrearRanuraGabineteOutput
    {
        $gabineteId = GabineteId::desde($input->gabineteId);

        if ($this->gabineteRepo->buscarPorId($gabineteId) === null) {
            throw new \DomainException("Gabinete '{$input->gabineteId}' no encontrado.");
        }

        $id = $this->ranuraRepo->nextIdentity();

        $ranura = RanuraGabinete::crear(
            id: $id,
            gabineteId: $gabineteId,
            numeroRanura: $input->numeroRanura,
            familiaTaxonomicaEsperadaId: $input->familiaTaxonomicaEsperadaId,
        );

        $this->ranuraRepo->guardar($ranura);

        return new CrearRanuraGabineteOutput(
            id: (string) $ranura->id(),
            gabineteId: (string) $ranura->gabineteId(),
            numeroRanura: $ranura->numeroRanura(),
            familiaTaxonomicaEsperadaId: $ranura->familiaTaxonomicaEsperadaId(),
            activa: $ranura->activa(),
        );
    }
}
