<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarEspecimenesUnitTray;

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\ClasificacionTaxonomicaPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\ContextoEjecucionPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\TransactionManagerPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Support\PropagaClasificacionTaxonomica;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ReubicarEspecimenes\ReubicarEspecimenesHandler;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Caja;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\EventoCicloIot;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\CajaRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EventoCicloIotRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UnitTrayEspecimenRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UnitTrayRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\ActorRol;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\UnitTrayId;

/**
 * Caso de uso: redefinir el conjunto de especímenes asignados a un unit tray. Recalcula la
 * clasificación taxonómica dominante del tray, la propaga hacia su caja, registra en la
 * trazabilidad cada espécimen que realmente cambió de contenedor y advierte cuáles no
 * encajan taxonómicamente con el resto del tray.
 *
 * Este es el ÚNICO punto donde se registra la trazabilidad de asignación de especímenes:
 * tanto la pantalla de "asignación" (que lo llama directo) como {@see ReubicarEspecimenesHandler}
 * (que delega aquí la sincronización) pasan por esta misma reclasificación, así que ningún
 * flanco queda sin trazabilidad ni la duplica.
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
     * @param  EventoCicloIotRepository  $eventoRepo  Registra el movimiento de trazabilidad de cada espécimen afectado.
     * @param  ContextoEjecucionPort  $contextoEjecucion  Aporta el actor (rol e id) que origina la asignación.
     * @param  TransactionManagerPort  $transactionManager  Envuelve la escritura en una transacción atómica.
     */
    public function __construct(
        private readonly UnitTrayRepository $unitTrayRepo,
        private readonly CajaRepository $cajaRepo,
        private readonly EspecimenRepositoryInterface $especimenRepo,
        private readonly UnitTrayEspecimenRepository $asignacionRepo,
        private readonly ClasificacionTaxonomicaPort $clasificacionPort,
        private readonly EventoCicloIotRepository $eventoRepo,
        private readonly ContextoEjecucionPort $contextoEjecucion,
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

        $actorRol = $this->contextoEjecucion->actorRol();
        $actorId = $this->contextoEjecucion->actorId();

        $this->transactionManager->executeTransactional(
            function () use ($input, $unitTray, $caja, $unitTrayId, $actorId, $actorRol): void {
                // Snapshot ANTES de mutar: de qué tray venía cada espécimen entrante (para
                // detectar llegadas desde otro contenedor) y quiénes eran los miembros actuales
                // de ESTE tray (para detectar salidas sin reemplazo, que quedan sin asignación).
                $origenes = $this->asignacionRepo->unitTraysDeEspecimenes($input->especimenIds);
                $actualesAntes = $this->asignacionRepo->especimenIdsPorUnitTray($unitTrayId);

                if ($input->especimenIds === []) {
                    $unitTray->limpiarClasificacion();
                } else {
                    $clasificacion = $this->resolverClasificacionAgregadaPorEspecimenes(
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

                $this->registrarMovimientos($input->especimenIds, $origenes, $actualesAntes, $unitTrayId, $caja, $actorId, $actorRol);
            }
        );

        $cls = $unitTray->clasificacionDominante();

        // Una caja especial está exenta de toda verificación taxonómica (igual que en el orden
        // entre cajas vecinas): sus trays no albergan una expectativa taxonómica, así que no
        // tiene sentido marcar especímenes como "fuera de lugar".
        $fueraDeLugar = $caja->esEspecial()
            ? []
            : $this->detectarEspecimenesFueraDeLugar(
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

    /**
     * Registra un evento 'especimen_reubicado' por cada espécimen que REALMENTE cambió de
     * contenedor con esta llamada: llegó desde otro tray (o desde ningún lado) hacia este, o
     * salió de este tray sin ser reemplazado en la misma lista (queda sin asignación). No emite
     * nada para los que ya estaban aquí y siguen aquí — evita ruido en la reclasificación que
     * {@see ReubicarEspecimenesHandler}
     * dispara sobre los trays de origen con su propia lista de miembros ya vigente.
     *
     * @param  string[]  $especimenIds
     * @param  array<string, string>  $origenes  especimenId => unitTrayId anterior (bulk, antes de sincronizar)
     * @param  string[]  $actualesAntes  miembros de ESTE tray antes de sincronizar
     */
    private function registrarMovimientos(
        array $especimenIds,
        array $origenes,
        array $actualesAntes,
        UnitTrayId $unitTrayId,
        Caja $caja,
        ?string $actorId,
        ActorRol $actorRol,
    ): void {
        $trayIdActual = (string) $unitTrayId;
        $cajaIdPorTray = [$trayIdActual => (string) $caja->id()];
        $ocurridoEn = new \DateTimeImmutable;

        foreach ($especimenIds as $especimenId) {
            $origenTrayId = $origenes[$especimenId] ?? null;
            if ($origenTrayId === $trayIdActual) {
                continue;
            }

            if ($origenTrayId !== null && ! array_key_exists($origenTrayId, $cajaIdPorTray)) {
                $trayOrigen = $this->unitTrayRepo->buscarPorId(UnitTrayId::desde($origenTrayId));
                $cajaIdPorTray[$origenTrayId] = $trayOrigen !== null ? (string) $trayOrigen->cajaId() : null;
            }

            $this->eventoRepo->guardar(EventoCicloIot::registrar(
                tipoAgregado: 'especimen',
                agregadoId: $especimenId,
                tipoEvento: 'especimen_reubicado',
                versionEvento: 1,
                datos: [
                    'origen_unit_tray_id' => $origenTrayId,
                    'origen_caja_id' => $origenTrayId !== null ? $cajaIdPorTray[$origenTrayId] : null,
                    'destino_unit_tray_id' => $trayIdActual,
                    'destino_caja_id' => $cajaIdPorTray[$trayIdActual],
                ],
                actorId: $actorId,
                actorRol: $actorRol,
                ocurridoEn: $ocurridoEn,
            ));
        }

        foreach (array_diff($actualesAntes, $especimenIds) as $especimenId) {
            $this->eventoRepo->guardar(EventoCicloIot::registrar(
                tipoAgregado: 'especimen',
                agregadoId: $especimenId,
                tipoEvento: 'especimen_reubicado',
                versionEvento: 1,
                datos: [
                    'origen_unit_tray_id' => $trayIdActual,
                    'origen_caja_id' => $cajaIdPorTray[$trayIdActual],
                    'destino_unit_tray_id' => null,
                    'destino_caja_id' => null,
                ],
                actorId: $actorId,
                actorRol: $actorRol,
                ocurridoEn: $ocurridoEn,
            ));
        }
    }
}
