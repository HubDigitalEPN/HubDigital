<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ReubicarEspecimenes;

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\ClasificacionTaxonomicaPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\ContextoEjecucionPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\TransactionManagerPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Support\PropagaClasificacionTaxonomica;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarEspecimenesUnitTray\ActualizarEspecimenesUnitTrayHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarEspecimenesUnitTray\ActualizarEspecimenesUnitTrayInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\EventoCicloIot;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Exceptions\ReubicacionNoAutorizadaException;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\CajaRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EventoCicloIotRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UnitTrayEspecimenRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UnitTrayRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\VisitanteRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\ActorRol;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\UnitTrayId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\VisitanteId;

/**
 * Caso de uso: reubicar uno o varios especímenes al unit tray de destino.
 *
 * La asignación (sincronización + reclasificación + propagación a la caja) se delega en
 * {@see ActualizarEspecimenesUnitTrayHandler}; este handler añade la autorización del actor,
 * la advertencia taxonómica previa (confirmar/cancelar) y el registro de un movimiento de
 * trazabilidad por cada espécimen reubicado.
 */
final class ReubicarEspecimenesHandler
{
    use PropagaClasificacionTaxonomica;

    public function __construct(
        private readonly ActualizarEspecimenesUnitTrayHandler $asignar,
        private readonly UnitTrayRepository $unitTrayRepo,
        private readonly CajaRepository $cajaRepo,
        private readonly EspecimenRepositoryInterface $especimenRepo,
        private readonly UnitTrayEspecimenRepository $asignacionRepo,
        private readonly ClasificacionTaxonomicaPort $clasificacionPort,
        private readonly EventoCicloIotRepository $eventoRepo,
        private readonly ContextoEjecucionPort $contextoEjecucion,
        private readonly VisitanteRepositoryInterface $visitanteRepo,
        private readonly TransactionManagerPort $transactionManager,
    ) {}

    public function handle(ReubicarEspecimenesInput $input): ReubicarEspecimenesOutput
    {
        $actorRol = $this->contextoEjecucion->actorRol();
        $actorId = $this->contextoEjecucion->actorId();
        $this->autorizar($actorRol, $actorId);

        $destinoId = UnitTrayId::desde($input->destinoUnitTrayId);
        $destino = $this->unitTrayRepo->buscarPorId($destinoId);
        if ($destino === null) {
            throw new \DomainException("UnitTray '{$input->destinoUnitTrayId}' no encontrado.");
        }

        $caja = $this->cajaRepo->buscarPorId($destino->cajaId());
        if ($caja === null) {
            throw new \DomainException('Caja del unit tray de destino no encontrada.');
        }

        // Origen de cada espécimen (para la trazabilidad); puede ser null si no estaba asignado.
        $origenes = $this->asignacionRepo->unitTraysDeEspecimenes($input->especimenIds);

        // El conjunto final del tray destino = sus especímenes actuales ∪ los movidos.
        $nuevos = array_values(array_unique([
            ...$this->asignacionRepo->especimenIdsPorUnitTray($destinoId),
            ...$input->especimenIds,
        ]));

        // Advertencia taxonómica suave: las cajas especiales están exentas.
        $fueraDeLugar = $caja->esEspecial()
            ? []
            : $this->detectarEspecimenesFueraDeLugar(
                $input->especimenIds,
                $this->resolverClasificacionAgregadaPorEspecimenes($nuevos, $this->especimenRepo, $this->clasificacionPort),
                $this->especimenRepo,
                $this->clasificacionPort,
            );

        if ($fueraDeLugar !== [] && ! $input->confirmar) {
            return new ReubicarEspecimenesOutput(
                reubicado: false,
                requiereConfirmacion: true,
                especimenesReubicados: [],
                especimenesFueraDeLugar: $fueraDeLugar,
                actorRol: $actorRol->valor(),
            );
        }

        $this->transactionManager->executeTransactional(function () use ($destinoId, $nuevos, $input, $origenes, $actorId, $actorRol): void {
            $this->asignar->handle(new ActualizarEspecimenesUnitTrayInput(
                unitTrayId: (string) $destinoId,
                especimenIds: $nuevos,
            ));

            // Los especímenes movidos ya se reubicaron (la asignación es 1:1 tray↔especimen), así
            // que sus trays de origen deben recalcular su clasificación dominante —y limpiarla si
            // quedaron vacíos— en vez de conservar la taxonomía de especímenes que ya no albergan.
            $origenTrayIds = array_unique(array_filter(
                array_values($origenes),
                static fn (string $origenId): bool => $origenId !== (string) $destinoId,
            ));

            foreach ($origenTrayIds as $origenTrayId) {
                $this->asignar->handle(new ActualizarEspecimenesUnitTrayInput(
                    unitTrayId: $origenTrayId,
                    especimenIds: $this->asignacionRepo->especimenIdsPorUnitTray(UnitTrayId::desde($origenTrayId)),
                ));
            }

            $ocurridoEn = new \DateTimeImmutable;
            foreach ($input->especimenIds as $especimenId) {
                $this->eventoRepo->guardar(EventoCicloIot::registrar(
                    tipoAgregado: 'especimen',
                    agregadoId: $especimenId,
                    tipoEvento: 'especimen_reubicado',
                    versionEvento: 1,
                    datos: [
                        'origen_unit_tray_id' => $origenes[$especimenId] ?? null,
                        'destino_unit_tray_id' => (string) $destinoId,
                    ],
                    actorId: $actorId,
                    actorRol: $actorRol,
                    ocurridoEn: $ocurridoEn,
                ));
            }
        });

        return new ReubicarEspecimenesOutput(
            reubicado: true,
            requiereConfirmacion: false,
            especimenesReubicados: $input->especimenIds,
            especimenesFueraDeLugar: $fueraDeLugar,
            actorRol: $actorRol->valor(),
        );
    }

    private function autorizar(ActorRol $actorRol, ?string $actorId): void
    {
        if ($actorRol !== ActorRol::Visitante) {
            return;
        }

        $visitante = $actorId !== null
            ? $this->visitanteRepo->buscarPorId(VisitanteId::desde($actorId))
            : null;

        if ($visitante === null || ! $visitante->puedeReubicar()) {
            throw ReubicacionNoAutorizadaException::visitanteSinHabilitacion((string) $actorId);
        }
    }
}
