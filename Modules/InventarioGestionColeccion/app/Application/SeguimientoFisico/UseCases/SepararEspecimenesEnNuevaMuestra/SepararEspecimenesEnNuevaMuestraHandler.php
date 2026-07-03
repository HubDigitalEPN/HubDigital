<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\SepararEspecimenesEnNuevaMuestra;

use Illuminate\Support\Facades\DB;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\MuestraColecta;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\MuestraColectaRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\MuestraColectaId;

/**
 * Separa un subconjunto de especímenes de una muestra de colecta hacia una
 * muestra NUEVA. Sirve cuando el importador agrupó por un `oldCode` reusado en
 * el Excel y el curador detecta que algunos ejemplares no son del mismo evento
 * de colecta: los marca y los separa en su propia muestra.
 *
 * La muestra nueva hereda el `codigoMuestra` de origen (es el mismo verbatim del
 * Excel; ambas quedan distinguidas por su id) y toma la fecha/localidad/colector
 * verbatim del primer espécimen separado como representativos. Nace pendiente
 * de revisión con un motivo que documenta la separación manual.
 *
 * Invariante: no se pueden separar TODOS los especímenes (dejaría la muestra de
 * origen vacía y la operación no tendría sentido); debe quedar al menos uno.
 */
final class SepararEspecimenesEnNuevaMuestraHandler
{
    public function __construct(
        private readonly EspecimenRepositoryInterface $especimenRepo,
        private readonly MuestraColectaRepositoryInterface $muestraRepo,
    ) {}

    public function handle(SepararEspecimenesEnNuevaMuestraInput $input): SepararEspecimenesEnNuevaMuestraOutput
    {
        $muestraOrigen = $this->muestraRepo->buscarPorId(MuestraColectaId::desde($input->muestraOrigenId));
        if ($muestraOrigen === null) {
            throw new \DomainException("Muestra de colecta no encontrada: '{$input->muestraOrigenId}'.");
        }

        // Miembros reales de la muestra de origen, indexados por id, para validar
        // la selección y derivar el representativo (sin confiar en ids sueltos).
        $miembros = [];
        foreach ($this->especimenRepo->buscarPorMuestraId($input->muestraOrigenId) as $e) {
            $miembros[(string) $e->id()] = $e;
        }

        $idsValidos = array_values(array_filter(
            array_unique($input->especimenIds),
            fn (string $id): bool => isset($miembros[$id]),
        ));

        if ($idsValidos === []) {
            throw new \DomainException('Selecciona al menos un espécimen de la muestra para separarlo.');
        }

        if (count($idsValidos) >= count($miembros)) {
            throw new \DomainException(
                'No puedes separar todos los especímenes: debe quedar al menos uno en la muestra original. '.
                'Marca solo los que no pertenecen a este evento de colecta.'
            );
        }

        /** @var Especimen $representativo */
        $representativo = $miembros[$idsValidos[0]];

        return DB::transaction(function () use ($muestraOrigen, $representativo, $idsValidos): SepararEspecimenesEnNuevaMuestraOutput {
            $nueva = MuestraColecta::crear(
                id: $this->muestraRepo->nextIdentity(),
                codigoMuestra: $muestraOrigen->codigoMuestra(),
                fechaVerbatim: $representativo->fechaVerbatim(),
                localidadVerbatim: $representativo->localidadVerbatim(),
                colector: $representativo->colector() !== '' ? $representativo->colector() : null,
                motivoRevision: 'separada manualmente de la agrupación por el curador',
            );
            $this->muestraRepo->guardar($nueva);

            $separados = $this->especimenRepo->reasignarMuestraPorIds($idsValidos, (string) $nueva->id());

            return new SepararEspecimenesEnNuevaMuestraOutput(
                nuevaMuestraId: (string) $nueva->id(),
                codigoMuestra: $nueva->codigoMuestra(),
                especimenesSeparados: $separados,
            );
        });
    }
}
