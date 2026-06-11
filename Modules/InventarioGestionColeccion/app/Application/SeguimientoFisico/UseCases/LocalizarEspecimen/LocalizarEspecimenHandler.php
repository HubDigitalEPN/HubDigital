<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\LocalizarEspecimen;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\CajaRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\GabineteRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\RanuraGabineteRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\TaxonRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UnitTrayEspecimenRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UnitTrayRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TaxonId;

/**
 * Resuelve la ruta física Gabinete → Ranura → Caja → Unit Tray de un espécimen,
 * para que el mapa del curador navegue y haga zoom hasta su ubicación. No aplica
 * regla de visibilidad pública: el curador ve toda la colección. La variante para
 * visitantes (con visibilidad) se construye aparte.
 *
 * @see LocalizarEspecimenInput
 * @see LocalizarEspecimenOutput
 */
final class LocalizarEspecimenHandler
{
    /**
     * @param  EspecimenRepositoryInterface  $especimenRepo  Recupera el espécimen a localizar.
     * @param  TaxonRepositoryInterface  $taxonRepo  Resuelve su nombre científico.
     * @param  UnitTrayEspecimenRepository  $asignacionRepo  Resuelve el unit tray que contiene al espécimen.
     * @param  UnitTrayRepository  $unitTrayRepo  Recupera el unit tray y su caja contenedora.
     * @param  CajaRepository  $cajaRepo  Recupera la caja y su ranura actual.
     * @param  RanuraGabineteRepository  $ranuraRepo  Recupera la ranura y su gabinete.
     * @param  GabineteRepository  $gabineteRepo  Recupera el gabinete que cierra la ruta física.
     */
    public function __construct(
        private readonly EspecimenRepositoryInterface $especimenRepo,
        private readonly TaxonRepositoryInterface $taxonRepo,
        private readonly UnitTrayEspecimenRepository $asignacionRepo,
        private readonly UnitTrayRepository $unitTrayRepo,
        private readonly CajaRepository $cajaRepo,
        private readonly RanuraGabineteRepository $ranuraRepo,
        private readonly GabineteRepository $gabineteRepo,
    ) {}

    /**
     * Resuelve la cadena física del espécimen (unit tray → caja → ranura → gabinete) para que el
     * mapa navegue hasta él. Distingue tres casos: no encontrado, encontrado pero sin ubicar (sin
     * tray asignado) y ubicado, devolviendo en este último la ruta tan lejos como sea rastreable
     * (los niveles pueden quedar en null si la caja está fuera de su ranura).
     */
    public function handle(LocalizarEspecimenInput $input): LocalizarEspecimenOutput
    {
        $especimen = $this->especimenRepo->buscarPorId(EspecimenId::desde($input->especimenId));
        if ($especimen === null) {
            return new LocalizarEspecimenOutput(encontrado: false, ubicado: false);
        }

        $nombreCientifico = $this->nombreCientifico($especimen->taxonId());

        $unitTrayId = $this->asignacionRepo->unitTrayDeEspecimen($input->especimenId);
        $unitTray = $unitTrayId !== null ? $this->unitTrayRepo->buscarPorId($unitTrayId) : null;
        if ($unitTray === null) {
            // El espécimen existe pero no está asignado a ningún unit tray físico.
            return new LocalizarEspecimenOutput(
                encontrado: true,
                ubicado: false,
                especimenId: (string) $especimen->id(),
                codigoCatalogo: $especimen->codigoCatalogo(),
                nombreCientifico: $nombreCientifico,
            );
        }

        $caja = $this->cajaRepo->buscarPorId($unitTray->cajaId());
        $ranura = $caja !== null && $caja->ranuraActualId() !== null
            ? $this->ranuraRepo->buscarPorId($caja->ranuraActualId())
            : null;
        $gabinete = $ranura !== null ? $this->gabineteRepo->buscarPorId($ranura->gabineteId()) : null;

        return new LocalizarEspecimenOutput(
            encontrado: true,
            ubicado: true,
            especimenId: (string) $especimen->id(),
            codigoCatalogo: $especimen->codigoCatalogo(),
            nombreCientifico: $nombreCientifico,
            gabineteId: $gabinete !== null ? (string) $gabinete->id() : null,
            gabineteCodigo: $gabinete !== null ? (string) $gabinete->codigo() : null,
            gabineteNombre: $gabinete?->nombre(),
            ranuraId: $ranura !== null ? (string) $ranura->id() : null,
            numeroRanura: $ranura?->numeroRanura(),
            cajaId: $caja !== null ? (string) $caja->id() : null,
            codigoCaja: $caja !== null ? (string) $caja->codigo() : null,
            unitTrayId: (string) $unitTray->id(),
            numeroUnitTray: $unitTray->numero(),
        );
    }

    private function nombreCientifico(?string $taxonId): ?string
    {
        if ($taxonId === null || $taxonId === '') {
            return null;
        }

        return $this->taxonRepo->buscarPorId(TaxonId::desde($taxonId))?->nombreCientifico();
    }
}
