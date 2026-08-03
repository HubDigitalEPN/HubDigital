<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\DeshacerEdicionMasiva;

use DateTimeImmutable;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\TransactionManagerPort;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\BitacoraEdicionMasivaRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoReversionDetalle;

/**
 * Devuelve los especímenes de una edición masiva al valor que tenían antes.
 *
 * El deshacer es POR FILA, no todo o nada. Para cada espécimen se comprueba que
 * el campo siga como lo dejó la edición:
 *
 *  - si coincide, se restaura el valor previo;
 *  - si no coincide, alguien lo editó después y la fila se deja intacta, marcada
 *    como conflicto. Revertirla borraría un cambio más reciente que nadie pidió
 *    descartar, y esa pérdida sí sería irrecuperable.
 *
 * La comparación es sobre el VALOR del campo, nunca sobre `updated_at`: esa
 * marca se mueve con cualquier escritura al espécimen, así que corregir la
 * localidad de una fila bloquearía el deshacer de una edición que tocó el
 * colector.
 */
final class DeshacerEdicionMasivaHandler
{
    public function __construct(
        private readonly EspecimenRepositoryInterface $especimenRepo,
        private readonly BitacoraEdicionMasivaRepositoryInterface $bitacoraRepo,
        private readonly TransactionManagerPort $transacciones,
    ) {}

    public function handle(DeshacerEdicionMasivaInput $input): DeshacerEdicionMasivaOutput
    {
        return $this->transacciones->executeTransactional(function () use ($input): DeshacerEdicionMasivaOutput {
            $edicion = $this->bitacoraRepo->buscarPorId($input->edicionId);
            if ($edicion === null) {
                throw new \DomainException('No se encontró la edición que se quiere deshacer.');
            }

            // La entidad lanza si ya estaba deshecha: los valores previos
            // guardados dejarían de describir el estado del que se partía.
            $edicion->marcarDeshecha(new DateTimeImmutable);

            $detalles = $this->bitacoraRepo->detallesDe($edicion->id());
            $ids = array_map(fn ($d) => $d->especimenId(), $detalles);
            $actuales = $this->especimenRepo->valoresDeCampoPorIds($ids, $edicion->campo());

            $aRevertir = [];
            $conflictos = 0;
            $desaparecidos = 0;

            foreach ($detalles as $detalle) {
                $id = $detalle->especimenId();

                if (! array_key_exists($id, $actuales)) {
                    $detalle->marcarComo(EstadoReversionDetalle::Desaparecido);
                    $desaparecidos++;

                    continue;
                }

                if ($actuales[$id] !== $detalle->valorAplicado()) {
                    $detalle->marcarComo(EstadoReversionDetalle::Conflicto);
                    $conflictos++;

                    continue;
                }

                $aRevertir[$id] = $detalle->valorPrevio();
                $detalle->marcarComo(EstadoReversionDetalle::Revertido);
            }

            if ($aRevertir !== []) {
                $this->especimenRepo->fijarCampoPorIdValor($aRevertir, $edicion->campo());
            }

            $this->bitacoraRepo->guardar($edicion);
            $this->bitacoraRepo->actualizarEstadoDetalles($detalles);

            return new DeshacerEdicionMasivaOutput(count($aRevertir), $conflictos, $desaparecidos);
        });
    }
}
