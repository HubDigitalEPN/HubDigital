<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\CrearGabinete;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Gabinete;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\RanuraGabinete;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\GabineteRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\RanuraGabineteRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CodigoGabinete;

/**
 * Caso de uso: dar de alta un gabinete con su total de ranuras, generando automáticamente las
 * ranuras numeradas {1..totalRanuras} para mantener el invariante de numeración contigua.
 *
 * @see CrearGabineteInput
 * @see CrearGabineteOutput
 */
final class CrearGabineteHandler
{
    /**
     * @param  GabineteRepository  $gabineteRepo  Genera la identidad del gabinete y lo persiste.
     * @param  RanuraGabineteRepository  $ranuraRepo  Genera y persiste cada ranura del gabinete.
     */
    public function __construct(
        private readonly GabineteRepository $gabineteRepo,
        private readonly RanuraGabineteRepository $ranuraRepo,
    ) {}

    /**
     * Crea el gabinete, lo persiste y genera sus ranuras numeradas desde 1 hasta el total
     * indicado, devolviendo los datos del gabinete resultante.
     */
    public function handle(CrearGabineteInput $input): CrearGabineteOutput
    {
        $id = $this->gabineteRepo->nextIdentity();
        $codigo = CodigoGabinete::desde($input->codigo);

        $gabinete = Gabinete::crear(
            id: $id,
            codigo: $codigo,
            nombre: $input->nombre,
            totalRanuras: $input->totalRanuras,
        );

        $this->gabineteRepo->guardar($gabinete);

        for ($numero = 1; $numero <= $input->totalRanuras; $numero++) {
            $this->ranuraRepo->guardar(RanuraGabinete::crear(
                id: $this->ranuraRepo->nextIdentity(),
                gabineteId: $gabinete->id(),
                numeroRanura: $numero,
            ));
        }

        return new CrearGabineteOutput(
            id: (string) $gabinete->id(),
            codigo: (string) $gabinete->codigo(),
            nombre: $gabinete->nombre(),
            totalRanuras: $gabinete->totalRanuras(),
            activo: $gabinete->activo(),
        );
    }
}
