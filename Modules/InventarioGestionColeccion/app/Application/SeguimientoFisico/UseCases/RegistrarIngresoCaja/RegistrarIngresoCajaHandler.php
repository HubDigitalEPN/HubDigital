<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarIngresoCaja;

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\ContextoEjecucionPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\EventPublisherPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\HorarioValidadorPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\TransactionManagerPort;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\AlertaUbicacion;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Caja;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\EventoCicloIot;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Notificacion;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\RanuraGabinete;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\UbicacionCaja;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\AlertaUbicacionRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\CajaRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EventoCicloIotRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\NotificacionRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\RanuraGabineteRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UbicacionCajaRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\ActorRol;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoCaja;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\RanuraId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TipoAlerta;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TipoNotificacion;

final class RegistrarIngresoCajaHandler
{
    public function __construct(
        private readonly CajaRepository $cajaRepo,
        private readonly RanuraGabineteRepository $ranuraRepo,
        private readonly UbicacionCajaRepository $ubicacionRepo,
        private readonly EventoCicloIotRepository $eventoRepo,
        private readonly AlertaUbicacionRepository $alertaRepo,
        private readonly NotificacionRepository $notificacionRepo,
        private readonly TransactionManagerPort $transactionManager,
        private readonly HorarioValidadorPort $horarioValidador,
        private readonly ContextoEjecucionPort $contextoEjecucion,
        private readonly EventPublisherPort $eventPublisher,
    ) {}

    public function handle(RegistrarIngresoCajaInput $input): RegistrarIngresoCajaOutput
    {
        $cajaId = CajaId::desde($input->cajaId);
        $ranuraId = RanuraId::desde($input->ranuraId);
        $actorRol = $this->contextoEjecucion->actorRol();
        $actorId = $this->contextoEjecucion->actorId();

        $caja = $this->cajaRepo->buscarPorId($cajaId);
        if ($caja === null) {
            throw new \DomainException("Caja '{$input->cajaId}' no encontrada.");
        }

        $ranura = $this->ranuraRepo->buscarPorId($ranuraId);
        if ($ranura === null) {
            throw new \DomainException("Ranura '{$input->ranuraId}' no encontrada.");
        }

        // ESP32 reset recovery: the sensor lost its in-memory state and sent an "ingreso"
        // event for a caja that is already registered as EnGabinete in the DB.
        if ($caja->estadoActual() === EstadoCaja::EnGabinete) {
            return $this->reconciliar($caja, $ranura, $cajaId, $ranuraId, $actorId, $actorRol);
        }

        // Normal flow: caja is EnTransito.
        [$ubicacion, $alertaGenerada] = $this->transactionManager->executeTransactional(
            function () use ($caja, $ranura, $cajaId, $ranuraId, $actorRol, $actorId): array {
                $caja->ingresarEnRanura($ranuraId);
                $ranura->asignarCaja($cajaId);

                $ocurridoEn = new \DateTimeImmutable;

                $ubicacion = UbicacionCaja::registrar(
                    id: $this->ubicacionRepo->nextIdentity(),
                    cajaId: $cajaId,
                    ranuraGabineteId: $ranuraId,
                    ingresadaEn: $ocurridoEn,
                );

                $eventoCiclo = EventoCicloIot::registrar(
                    tipoAgregado: 'caja',
                    agregadoId: (string) $cajaId,
                    tipoEvento: 'caja_ingresada',
                    versionEvento: 1,
                    datos: ['ranura_id' => (string) $ranuraId],
                    actorId: $actorId,
                    actorRol: $actorRol,
                    ocurridoEn: $ocurridoEn,
                );

                $alertaGenerada = false;

                if ($this->horarioValidador->esFueraDeHorario($ocurridoEn)) {
                    $alerta = AlertaUbicacion::generar(
                        id: $this->alertaRepo->nextIdentity(),
                        cajaId: $cajaId,
                        tipo: TipoAlerta::MovimientoNoAutorizado,
                        datosContexto: ['accion' => 'ingreso'],
                    );
                    $this->alertaRepo->guardar($alerta);

                    $notificacion = Notificacion::crear(
                        id: $this->notificacionRepo->nextIdentity(),
                        cajaId: $cajaId,
                        tipo: TipoNotificacion::MovimientoNoAutorizado,
                        datosContexto: ['accion' => 'ingreso'],
                    );
                    $this->notificacionRepo->guardar($notificacion);

                    $alertaGenerada = true;
                }

                $this->cajaRepo->guardar($caja);
                $this->ranuraRepo->guardar($ranura);
                $this->ubicacionRepo->guardar($ubicacion);
                $this->eventoRepo->guardar($eventoCiclo);

                foreach ($caja->pullEvents() as $evento) {
                    $this->eventPublisher->publish($evento);
                }

                return [$ubicacion, $alertaGenerada];
            }
        );

        return RegistrarIngresoCajaOutput::fromPrimitives([
            'cajaId' => (string) $cajaId,
            'ranuraId' => (string) $ranuraId,
            'estadoCaja' => $caja->estadoActual()->valor(),
            'ubicacionCajaId' => (string) $ubicacion->id(),
            'alertaGenerada' => $alertaGenerada,
        ]);
    }

    private function reconciliar(
        Caja $caja,
        RanuraGabinete $ranura,
        CajaId $cajaId,
        RanuraId $ranuraId,
        ?string $actorId,
        ActorRol $actorRol,
    ): RegistrarIngresoCajaOutput {
        $ubicacionActiva = $this->ubicacionRepo->buscarActivaPorCaja($cajaId);

        // Same ranura: ESP32 rediscovered a caja already registered here — purely idempotent.
        if ($ubicacionActiva !== null && $ubicacionActiva->ranuraGabineteId()->equals($ranuraId)) {
            return RegistrarIngresoCajaOutput::fromPrimitives([
                'cajaId' => (string) $cajaId,
                'ranuraId' => (string) $ranuraId,
                'estadoCaja' => $caja->estadoActual()->valor(),
                'ubicacionCajaId' => (string) $ubicacionActiva->id(),
                'alertaGenerada' => false,
            ]);
        }

        // Different ranura (or no active ubicacion): physical relocation happened while
        // the ESP32 was offline. Close the old ubicacion, free the old ranura, open a new one.
        [$ubicacion] = $this->transactionManager->executeTransactional(
            function () use ($caja, $ranura, $cajaId, $ranuraId, $actorId, $actorRol, $ubicacionActiva): array {
                $ocurridoEn = new \DateTimeImmutable;

                if ($ubicacionActiva !== null) {
                    $ranuraAnterior = $this->ranuraRepo->buscarPorId($ubicacionActiva->ranuraGabineteId());
                    if ($ranuraAnterior !== null) {
                        $ranuraAnterior->liberarCaja();
                        $this->ranuraRepo->guardar($ranuraAnterior);
                    }
                    $ubicacionActiva->cerrar($ocurridoEn);
                    $this->ubicacionRepo->guardar($ubicacionActiva);
                }

                $caja->reconciliarEnRanura($ranuraId);
                $ranura->asignarCaja($cajaId);

                $ubicacion = UbicacionCaja::registrar(
                    id: $this->ubicacionRepo->nextIdentity(),
                    cajaId: $cajaId,
                    ranuraGabineteId: $ranuraId,
                    ingresadaEn: $ocurridoEn,
                );

                $eventoCiclo = EventoCicloIot::registrar(
                    tipoAgregado: 'caja',
                    agregadoId: (string) $cajaId,
                    tipoEvento: 'caja_reubicada',
                    versionEvento: 1,
                    datos: ['ranura_id' => (string) $ranuraId],
                    actorId: $actorId,
                    actorRol: $actorRol,
                    ocurridoEn: $ocurridoEn,
                );

                $this->cajaRepo->guardar($caja);
                $this->ranuraRepo->guardar($ranura);
                $this->ubicacionRepo->guardar($ubicacion);
                $this->eventoRepo->guardar($eventoCiclo);

                foreach ($caja->pullEvents() as $evento) {
                    $this->eventPublisher->publish($evento);
                }

                return [$ubicacion];
            }
        );

        return RegistrarIngresoCajaOutput::fromPrimitives([
            'cajaId' => (string) $cajaId,
            'ranuraId' => (string) $ranuraId,
            'estadoCaja' => $caja->estadoActual()->valor(),
            'ubicacionCajaId' => (string) $ubicacion->id(),
            'alertaGenerada' => false,
        ]);
    }
}
