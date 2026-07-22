<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ReubicarUnitTray;

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\ContextoEjecucionPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\TransactionManagerPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Support\PropagaClasificacionTaxonomica;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\EventoCicloIot;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Exceptions\ReubicacionNoAutorizadaException;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\CajaRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EventoCicloIotRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UnitTrayRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\VisitanteRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\ActorRol;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\UnitTrayId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\VisitanteId;

/**
 * Caso de uso: reubicar un unit tray completo a otra caja, registrando el movimiento en la
 * trazabilidad y repropagando la clasificación dominante a las cajas de origen y destino
 * (el origen puede quedar sin trays clasificados y debe limpiar su taxonomía cacheada).
 * La caja de destino se selecciona/escanea (NFC) en la presentación.
 */
final class ReubicarUnitTrayHandler
{
    use PropagaClasificacionTaxonomica;

    public function __construct(
        private readonly UnitTrayRepository $unitTrayRepo,
        private readonly CajaRepository $cajaRepo,
        private readonly EventoCicloIotRepository $eventoRepo,
        private readonly ContextoEjecucionPort $contextoEjecucion,
        private readonly VisitanteRepositoryInterface $visitanteRepo,
        private readonly TransactionManagerPort $transactionManager,
    ) {}

    public function handle(ReubicarUnitTrayInput $input): ReubicarUnitTrayOutput
    {
        $actorRol = $this->contextoEjecucion->actorRol();
        $actorId = $this->contextoEjecucion->actorId();
        $this->autorizar($actorRol, $actorId);

        $tray = $this->unitTrayRepo->buscarPorId(UnitTrayId::desde($input->unitTrayId));
        if ($tray === null) {
            throw new \DomainException("UnitTray '{$input->unitTrayId}' no encontrado.");
        }

        $cajaDestino = $this->cajaRepo->buscarPorId(CajaId::desde($input->cajaDestinoId));
        if ($cajaDestino === null) {
            throw new \DomainException("Caja de destino '{$input->cajaDestinoId}' no encontrada.");
        }

        $origenCajaId = (string) $tray->cajaId();

        $this->transactionManager->executeTransactional(function () use ($tray, $cajaDestino, $origenCajaId, $input, $actorId, $actorRol): void {
            // La numeración es correlativa por caja: al cambiar de caja el tray toma el
            // siguiente número libre del destino para no chocar con las bandejas existentes.
            $numeroEnDestino = $origenCajaId === $input->cajaDestinoId
                ? $tray->numero()
                : $this->unitTrayRepo->siguienteNumero($cajaDestino->id());

            $tray->moverACaja($cajaDestino->id(), $numeroEnDestino);
            $this->unitTrayRepo->guardar($tray);

            // Repropaga la clasificación de ambas cajas: el origen pudo quedar sin trays
            // clasificados (limpia su taxonomía cacheada) y el destino absorbe la del tray.
            if ($origenCajaId !== $input->cajaDestinoId) {
                $cajaOrigen = $this->cajaRepo->buscarPorId(CajaId::desde($origenCajaId));
                if ($cajaOrigen !== null) {
                    $this->propagarClasificacionACaja($cajaOrigen->id(), $this->unitTrayRepo, $cajaOrigen, $this->cajaRepo);
                }
            }
            $this->propagarClasificacionACaja($cajaDestino->id(), $this->unitTrayRepo, $cajaDestino, $this->cajaRepo);

            $this->eventoRepo->guardar(EventoCicloIot::registrar(
                tipoAgregado: 'unit_tray',
                agregadoId: $input->unitTrayId,
                tipoEvento: 'unit_tray_reubicado',
                versionEvento: 1,
                datos: [
                    'origen_caja_id' => $origenCajaId,
                    'destino_caja_id' => $input->cajaDestinoId,
                ],
                actorId: $actorId,
                actorRol: $actorRol,
                ocurridoEn: new \DateTimeImmutable,
            ));
        });

        return new ReubicarUnitTrayOutput(
            reubicado: true,
            unitTrayId: $input->unitTrayId,
            cajaDestinoId: $input->cajaDestinoId,
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
