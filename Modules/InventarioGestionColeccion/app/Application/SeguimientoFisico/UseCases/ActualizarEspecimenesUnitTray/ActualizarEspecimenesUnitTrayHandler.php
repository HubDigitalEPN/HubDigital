<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarEspecimenesUnitTray;

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\ClasificacionTaxonomicaPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\TransactionManagerPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Support\PropagaClasificacionTaxonomica;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\CajaRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UnitTrayEspecimenRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UnitTrayRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\UnitTrayId;

/**
 * Caso de uso: redefinir el conjunto de especímenes asignados a un unit tray. Recalcula la
 * clasificación taxonómica dominante del tray, la propaga hacia su caja y advierte qué
 * especímenes no encajan taxonómicamente con el resto del tray.
 *
 * @see ActualizarEspecimenesUnitTrayInput
 * @see ActualizarEspecimenesUnitTrayOutput
 */
final class ActualizarEspecimenesUnitTrayHandler
{
    use PropagaClasificacionTaxonomica;

    /**
     * @param  UnitTrayRepository  $unitTrayRepo  Recupera y persiste el unit tray afectado.
     * @param  CajaRepository  $cajaRepo  Permite recalcular y persistir la clasificación de la caja contenedora.
     * @param  EspecimenRepositoryInterface  $especimenRepo  Resuelve cada espécimen para leer su taxón.
     * @param  UnitTrayEspecimenRepository  $asignacionRepo  Sincroniza la relación tray↔especímenes.
     * @param  ClasificacionTaxonomicaPort  $clasificacionPort  Resuelve la clasificación completa de cada taxón.
     * @param  TransactionManagerPort  $transactionManager  Envuelve la escritura en una transacción atómica.
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
     * Reemplaza por completo los especímenes del tray: recalcula su clasificación dominante,
     * sincroniza las asignaciones y propaga la clasificación a la caja, todo dentro de una
     * transacción. Luego detecta los especímenes cuya taxonomía discrepa del tray (alerta suave).
     *
     * @throws \DomainException si el unit tray o su caja asociada no existen.
     */
    public function handle(ActualizarEspecimenesUnitTrayInput $input): ActualizarEspecimenesUnitTrayOutput
    {
        $unitTrayId = UnitTrayId::desde($input->unitTrayId);

        $unitTray = $this->unitTrayRepo->buscarPorId($unitTrayId);
        if ($unitTray === null) {
            throw new \DomainException("UnitTray '{$input->unitTrayId}' no encontrado.");
        }

        $caja = $this->cajaRepo->buscarPorId($unitTray->cajaId());
        if ($caja === null) {
            throw new \DomainException('Caja asociada al UnitTray no encontrada.');
        }

        $this->transactionManager->executeTransactional(
            function () use ($input, $unitTray, $caja): void {
                if ($input->especimenIds === []) {
                    $unitTray->limpiarClasificacion();
                } else {
                    $clasificacion = $this->resolverDominantePorEspecimenes(
                        $input->especimenIds,
                        $this->especimenRepo,
                        $this->clasificacionPort,
                    );

                    $clasificacion !== null
                        ? $unitTray->actualizarClasificacion($clasificacion)
                        : $unitTray->limpiarClasificacion();
                }

                $this->unitTrayRepo->guardar($unitTray);
                $this->asignacionRepo->sincronizar($unitTray->id(), $input->especimenIds);

                $this->propagarClasificacionACaja(
                    $unitTray->cajaId(),
                    $this->unitTrayRepo,
                    $caja,
                    $this->cajaRepo,
                );
            }
        );

        $cls = $unitTray->clasificacionDominante();

        $fueraDeLugar = $this->detectarEspecimenesFueraDeLugar(
            $input->especimenIds,
            $cls,
            $this->especimenRepo,
            $this->clasificacionPort,
        );

        return ActualizarEspecimenesUnitTrayOutput::fromPrimitives([
            'unitTrayId' => (string) $unitTrayId,
            'tieneClasificacion' => $cls !== null && ! $cls->estaVacia(),
            'subfamiliaAsignada' => $cls?->subfamilia(),
            'generoAsignado' => $cls?->genero(),
            'especimenesFueraDeLugar' => $fueraDeLugar,
        ]);
    }
}
