<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ObtenerFichaEspecimen;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\TaxonRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services\MapeadorFilaEspecimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services\RegistroColumnasEspecimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;

/**
 * Devuelve la ficha COMPLETA de un espécimen: todas sus columnas aplanadas con
 * las claves de {@see RegistroColumnasEspecimen}.
 *
 * Alimenta el modal "Ver ficha" que el curador abre desde cualquier bandeja de
 * revisión para inspeccionar toda la información de un espécimen sin salir del
 * flujo de control de calidad.
 */
final class ObtenerFichaEspecimenHandler
{
    public function __construct(
        private readonly EspecimenRepositoryInterface $especimenRepo,
        private readonly TaxonRepositoryInterface $taxonRepo,
    ) {}

    public function handle(ObtenerFichaEspecimenInput $input): ObtenerFichaEspecimenOutput
    {
        $id = trim($input->especimenId);
        if ($id === '' || ! EspecimenId::esValido($id)) {
            return new ObtenerFichaEspecimenOutput([], false);
        }

        $especimen = $this->especimenRepo->buscarPorId(EspecimenId::desde($id));
        if ($especimen === null) {
            return new ObtenerFichaEspecimenOutput([], false);
        }

        $nombreTaxon = null;
        if ($especimen->taxonId() !== null) {
            $taxones = $this->taxonRepo->buscarPorIds([$especimen->taxonId()]);
            if ($taxones !== []) {
                $nombreTaxon = $taxones[0]->nombreCientifico();
            }
        }

        return new ObtenerFichaEspecimenOutput(
            MapeadorFilaEspecimen::mapear($especimen, $nombreTaxon),
            true,
        );
    }
}
