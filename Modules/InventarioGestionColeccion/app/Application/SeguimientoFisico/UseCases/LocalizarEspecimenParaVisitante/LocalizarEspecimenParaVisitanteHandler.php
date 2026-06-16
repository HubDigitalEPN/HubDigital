<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\LocalizarEspecimenParaVisitante;

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\LocalizarEspecimen\LocalizarEspecimenHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\LocalizarEspecimen\LocalizarEspecimenInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\LocalizarEspecimen\LocalizarEspecimenOutput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\TaxonRepositoryInterface;

/**
 * Guía pública de ubicación para el visitante: resuelve un nombre científico al
 * espécimen y entrega su ruta física (Gabinete → Caja → Unit Tray) reutilizando
 * el traversal de {@see LocalizarEspecimenHandler}.
 *
 * Regla de visibilidad (sin flag): la ubicación es siempre compartible. Lo único
 * que la hace "no disponible públicamente" es que el espécimen no esté colocado en
 * un unit tray (ubicado=false): no hay custodia física resolvable que mostrar.
 *
 * Ante varias coincidencias del mismo nombre científico devuelve la del primer
 * espécimen ubicado y expone el total en `coincidencias` para que la UI avise.
 *
 * @see LocalizarEspecimenParaVisitanteInput
 * @see LocalizarEspecimenParaVisitanteOutput
 */
final class LocalizarEspecimenParaVisitanteHandler
{
    /**
     * Tope de especímenes que se recorren resolviendo su ruta: acota el coste
     * cuando un nombre parcial coincide con muchos taxones/especímenes.
     */
    private const MAX_ESPECIMENES_EVALUADOS = 50;

    public function __construct(
        private readonly TaxonRepositoryInterface $taxonRepo,
        private readonly EspecimenRepositoryInterface $especimenRepo,
        private readonly LocalizarEspecimenHandler $localizarEspecimen,
    ) {}

    public function handle(LocalizarEspecimenParaVisitanteInput $input): LocalizarEspecimenParaVisitanteOutput
    {
        $nombre = trim($input->nombreCientifico);
        if ($nombre === '') {
            return new LocalizarEspecimenParaVisitanteOutput(encontrado: false, disponiblePublicamente: false);
        }

        $taxones = $this->taxonRepo->buscarPorNombreContiene($nombre);
        if ($taxones === []) {
            return new LocalizarEspecimenParaVisitanteOutput(encontrado: false, disponiblePublicamente: false);
        }

        $taxonIds = array_map(static fn ($taxon) => (string) $taxon->id(), $taxones);
        $especimenes = $this->especimenRepo->buscarPorTaxonIds($taxonIds);
        if ($especimenes === []) {
            return new LocalizarEspecimenParaVisitanteOutput(encontrado: false, disponiblePublicamente: false);
        }

        $coincidencias = count($especimenes);
        $primeraRuta = null;

        foreach (array_slice($especimenes, 0, self::MAX_ESPECIMENES_EVALUADOS) as $especimen) {
            $ruta = $this->localizarEspecimen->handle(
                new LocalizarEspecimenInput((string) $especimen->id())
            );

            // El primer espécimen ubicado en un unit tray gana: es la ruta navegable.
            if ($ruta->ubicado) {
                return $this->aSalida($ruta, $coincidencias);
            }

            $primeraRuta ??= $ruta;
        }

        // Existen especímenes con ese nombre pero ninguno está colocado en un unit tray:
        // la ubicación no es resolvable, así que no está disponible públicamente.
        return new LocalizarEspecimenParaVisitanteOutput(
            encontrado: true,
            disponiblePublicamente: false,
            coincidencias: $coincidencias,
            especimenId: $primeraRuta?->especimenId,
            codigoCatalogo: $primeraRuta?->codigoCatalogo,
            nombreCientifico: $primeraRuta?->nombreCientifico,
        );
    }

    private function aSalida(LocalizarEspecimenOutput $ruta, int $coincidencias): LocalizarEspecimenParaVisitanteOutput
    {
        return new LocalizarEspecimenParaVisitanteOutput(
            encontrado: true,
            disponiblePublicamente: true,
            coincidencias: $coincidencias,
            especimenId: $ruta->especimenId,
            codigoCatalogo: $ruta->codigoCatalogo,
            nombreCientifico: $ruta->nombreCientifico,
            gabineteId: $ruta->gabineteId,
            gabineteCodigo: $ruta->gabineteCodigo,
            gabineteNombre: $ruta->gabineteNombre,
            ranuraId: $ruta->ranuraId,
            numeroRanura: $ruta->numeroRanura,
            cajaId: $ruta->cajaId,
            codigoCaja: $ruta->codigoCaja,
            unitTrayId: $ruta->unitTrayId,
            numeroUnitTray: $ruta->numeroUnitTray,
        );
    }
}
