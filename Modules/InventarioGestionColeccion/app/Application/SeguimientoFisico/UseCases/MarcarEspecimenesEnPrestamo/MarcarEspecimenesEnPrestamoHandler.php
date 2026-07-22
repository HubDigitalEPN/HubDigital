<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\MarcarEspecimenesEnPrestamo;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoEspecimen;

/**
 * Caso de uso: saca del catálogo los especímenes que salen en un préstamo activo.
 *
 * Es el punto de entrada público del inventario para la sincronización de estados:
 * GestionPrestamosRecepciones lo invoca a través de su adaptador ACL, de modo que la
 * regla de transición vive aquí, del lado del módulo dueño del dato.
 *
 * No lanza excepciones por espécimen ausente o ya prestado. El préstamo que dispara
 * esta llamada ya tiene acta firmada y entrega física verificada: abortar dejaría un
 * préstamo imposible de cerrar. Las anomalías salen en el Output.
 */
final class MarcarEspecimenesEnPrestamoHandler
{
    public function __construct(
        private readonly EspecimenRepositoryInterface $repo,
    ) {}

    public function handle(MarcarEspecimenesEnPrestamoInput $input): MarcarEspecimenesEnPrestamoOutput
    {
        $actualizados = [];
        $noEncontrados = [];
        $yaEnPrestamo = [];

        foreach (array_unique(array_filter($input->especimenIds)) as $id) {
            $especimen = $this->repo->buscarPorId(EspecimenId::desde($id));

            if ($especimen === null) {
                $noEncontrados[] = $id;

                continue;
            }

            if ($especimen->estado()->equals(EstadoEspecimen::EnPrestamo)) {
                $yaEnPrestamo[] = $id;

                continue;
            }

            $especimen->marcarEnPrestamo();
            $this->repo->guardar($especimen);
            $actualizados[] = $id;
        }

        return new MarcarEspecimenesEnPrestamoOutput(
            actualizados: $actualizados,
            noEncontrados: $noEncontrados,
            yaEnPrestamo: $yaEnPrestamo,
        );
    }
}
