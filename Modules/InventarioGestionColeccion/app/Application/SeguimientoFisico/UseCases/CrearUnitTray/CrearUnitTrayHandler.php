<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\CrearUnitTray;

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\ClasificacionTaxonomicaPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\TransactionManagerPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Support\PropagaClasificacionTaxonomica;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\UnitTray;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\CajaRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UnitTrayEspecimenRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UnitTrayRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;

/**
 * Caso de uso: crear un nuevo unit tray dentro de una caja, asignándole opcionalmente
 * especímenes y calculando su clasificación taxonómica dominante, que luego se propaga a la caja.
 *
 * @see CrearUnitTrayInput
 * @see CrearUnitTrayOutput
 */
final class CrearUnitTrayHandler
{
    use PropagaClasificacionTaxonomica;

    /**
     * @param  UnitTrayRepository  $unitTrayRepo  Genera identidad/número de tray y lo persiste.
     * @param  CajaRepository  $cajaRepo  Verifica la caja contenedora y recalcula su clasificación.
     * @param  EspecimenRepositoryInterface  $especimenRepo  Resuelve cada espécimen para leer su taxón.
     * @param  UnitTrayEspecimenRepository  $asignacionRepo  Sincroniza la relación tray↔especímenes.
     * @param  ClasificacionTaxonomicaPort  $clasificacionPort  Resuelve la clasificación completa de cada taxón.
     * @param  TransactionManagerPort  $transactionManager  Envuelve la creación en una transacción atómica.
     */
    public function __construct(
        private readonly UnitTrayRepository $unitTrayRepo,
        private readonly CajaRepository $cajaRepo,
        private readonly EspecimenRepositoryInterface $especimenRepo,
        private readonly UnitTrayEspecimenRepository $asignacionRepo,
        private readonly ClasificacionTaxonomicaPort $clasificacionPort,
        private readonly TransactionManagerPort $transactionManager,
    ) {}

    /**
     * Crea el unit tray en la caja indicada con el siguiente número disponible, le asigna los
     * especímenes del input, calcula su clasificación dominante y la propaga a la caja, todo
     * dentro de una transacción.
     *
     * @throws \DomainException si la caja contenedora no existe.
     */
    public function handle(CrearUnitTrayInput $input): CrearUnitTrayOutput
    {
        $cajaId = CajaId::desde($input->cajaId);

        $caja = $this->cajaRepo->buscarPorId($cajaId);
        if ($caja === null) {
            throw new \DomainException("Caja '{$input->cajaId}' no encontrada.");
        }

        $unitTray = $this->transactionManager->executeTransactional(
            function () use ($input, $cajaId, $caja): UnitTray {
                $unitTray = UnitTray::crear(
                    id: $this->unitTrayRepo->nextIdentity(),
                    cajaId: $cajaId,
                    numero: $this->unitTrayRepo->siguienteNumero($cajaId),
                );

                if ($input->especimenIds !== []) {
                    $clasificacion = $this->resolverDominantePorEspecimenes(
                        $input->especimenIds,
                        $this->especimenRepo,
                        $this->clasificacionPort,
                    );

                    if ($clasificacion !== null) {
                        $unitTray->actualizarClasificacion($clasificacion);
                    }
                }

                $this->unitTrayRepo->guardar($unitTray);
                $this->asignacionRepo->sincronizar($unitTray->id(), $input->especimenIds);

                $this->propagarClasificacionACaja(
                    $cajaId,
                    $this->unitTrayRepo,
                    $caja,
                    $this->cajaRepo,
                );

                return $unitTray;
            }
        );

        $cls = $unitTray->clasificacionDominante();

        return CrearUnitTrayOutput::fromPrimitives([
            'unitTrayId' => (string) $unitTray->id(),
            'cajaId' => (string) $cajaId,
            'numero' => $unitTray->numero(),
            'tieneClasificacion' => $cls !== null && ! $cls->estaVacia(),
            'subfamiliaAsignada' => $cls?->subfamilia(),
            'generoAsignado' => $cls?->genero(),
        ]);
    }
}
