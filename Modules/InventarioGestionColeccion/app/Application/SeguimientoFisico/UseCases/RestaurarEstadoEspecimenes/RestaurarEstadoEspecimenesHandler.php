<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RestaurarEstadoEspecimenes;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;

/**
 * Caso de uso: devuelve al catálogo los especímenes de un préstamo que se cierra.
 *
 * La restauración es por espécimen, no por lote: el resultado global del préstamo
 * (sin novedades / con observación) es solo un resumen. Un espécimen con novedad queda
 * `observado` y no vuelve solo a `disponible` — eso lo resuelve el curador desde el
 * inventario.
 *
 * Al igual que el marcado, no lanza excepciones por espécimen ausente ni por espécimen
 * que ya estuviera en el estado destino: la operación es idempotente y las anomalías
 * salen en el Output.
 */
final class RestaurarEstadoEspecimenesHandler
{
    public function __construct(
        private readonly EspecimenRepositoryInterface $repo,
    ) {}

    public function handle(RestaurarEstadoEspecimenesInput $input): RestaurarEstadoEspecimenesOutput
    {
        $conNovedad = array_flip(array_filter($input->conNovedad));

        $disponibles = [];
        $observados = [];
        $noEncontrados = [];

        foreach (array_unique(array_filter($input->especimenIds)) as $id) {
            $especimen = $this->repo->buscarPorId(EspecimenId::desde($id));

            if ($especimen === null) {
                $noEncontrados[] = $id;

                continue;
            }

            if (isset($conNovedad[$id])) {
                $especimen->marcarObservado();
                $observados[] = $id;
            } else {
                $especimen->marcarDisponible();
                $disponibles[] = $id;
            }

            $this->repo->guardar($especimen);
        }

        return new RestaurarEstadoEspecimenesOutput(
            disponibles: $disponibles,
            observados: $observados,
            noEncontrados: $noEncontrados,
        );
    }
}
