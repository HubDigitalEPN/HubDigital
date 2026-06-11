<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarRetiroCaja;

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\ContextoEjecucionPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\EventPublisherPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\HorarioValidadorPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\TransactionManagerPort;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\AlertaUbicacion;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\EventoCicloIot;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Notificacion;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\AlertaUbicacionRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\CajaRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EventoCicloIotRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\NotificacionRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\RanuraGabineteRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UbicacionCajaRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoCaja;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TipoAlerta;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TipoNotificacion;

/**
 * Caso de uso: registrar el retiro de una caja de su ranura. Saca la caja, libera la ranura,
 * cierra su ubicación activa y registra el evento del ciclo IoT; si el retiro ocurre fuera de
 * horario hábil genera la alerta y la notificación de movimiento no autorizado. Es idempotente:
 * si la caja ya está en tránsito o no tiene ubicación activa, no hace nada.
 *
 * @see RegistrarRetiroCajaInput
 * @see RegistrarRetiroCajaOutput
 */
final class RegistrarRetiroCajaHandler
{
    /**
     * @param  CajaRepository  $cajaRepo  Recupera y persiste la caja que se retira.
     * @param  RanuraGabineteRepository  $ranuraRepo  Recupera la ranura ocupada y la libera.
     * @param  UbicacionCajaRepository  $ubicacionRepo  Consulta y cierra la ubicación activa de la caja.
     * @param  EventoCicloIotRepository  $eventoRepo  Registra el evento del ciclo IoT del retiro.
     * @param  AlertaUbicacionRepository  $alertaRepo  Genera la alerta de movimiento fuera de horario.
     * @param  NotificacionRepository  $notificacionRepo  Crea la notificación de movimiento fuera de horario.
     * @param  TransactionManagerPort  $transactionManager  Envuelve las escrituras en una transacción atómica.
     * @param  HorarioValidadorPort  $horarioValidador  Determina si el retiro ocurre fuera de horario hábil.
     * @param  ContextoEjecucionPort  $contextoEjecucion  Aporta el actor (rol e id) que origina el retiro.
     * @param  EventPublisherPort  $eventPublisher  Publica los eventos de dominio acumulados por la caja.
     */
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

    /**
     * Registra el retiro de la caja usando su ubicación activa como fuente de verdad de la
     * ranura ocupada: saca la caja, libera la ranura, cierra la ubicación y registra el evento;
     * si el retiro es fuera de horario genera alerta y notificación. Devuelve temprano (sin
     * efectos) si la caja ya está en tránsito o no tiene ubicación activa.
     *
     * @throws \DomainException si la caja no existe o la ranura de su ubicación activa no se encuentra.
     */
    public function handle(RegistrarRetiroCajaInput $input): RegistrarRetiroCajaOutput
    {
        $cajaId = CajaId::desde($input->cajaId);
        $actorRol = $this->contextoEjecucion->actorRol();
        $actorId = $this->contextoEjecucion->actorId();

        $caja = $this->cajaRepo->buscarPorId($cajaId);
        if ($caja === null) {
            throw new \DomainException("Caja '{$input->cajaId}' no encontrada.");
        }

        // Idempotency: if the caja is already EnTransito, the retiro already happened (or ingreso never persisted).
        if ($caja->estadoActual() === EstadoCaja::EnTransito) {
            return RegistrarRetiroCajaOutput::fromPrimitives([
                'cajaId' => (string) $cajaId,
                'estadoCaja' => $caja->estadoActual()->valor(),
                'alertaGenerada' => false,
                'notificacionEnviada' => false,
            ]);
        }

        // Use the active ubicacion as source of truth for which ranura the caja occupies.
        $ubicacion = $this->ubicacionRepo->buscarActivaPorCaja($cajaId);
        if ($ubicacion === null) {
            // No active ubicacion: ingreso was never persisted — nothing to retire.
            return RegistrarRetiroCajaOutput::fromPrimitives([
                'cajaId' => (string) $cajaId,
                'estadoCaja' => $caja->estadoActual()->valor(),
                'alertaGenerada' => false,
                'notificacionEnviada' => false,
            ]);
        }

        $ranura = $this->ranuraRepo->buscarPorId($ubicacion->ranuraGabineteId());
        if ($ranura === null) {
            throw new \DomainException("Ranura de la ubicación activa de la caja '{$input->cajaId}' no encontrada — integridad de datos comprometida.");
        }

        [$alertaGenerada, $notificacionEnviada] = $this->transactionManager->executeTransactional(
            function () use ($caja, $ranura, $ubicacion, $cajaId, $actorRol, $actorId): array {
                $ocurridoEn = new \DateTimeImmutable;

                $caja->retirarDeRanura();
                $ranura->liberarCaja();
                $ubicacion->cerrar($ocurridoEn);

                $eventoCiclo = EventoCicloIot::registrar(
                    tipoAgregado: 'caja',
                    agregadoId: (string) $cajaId,
                    tipoEvento: 'caja_retirada',
                    versionEvento: 1,
                    datos: ['ranura_id' => (string) $ranura->id()],
                    actorId: $actorId,
                    actorRol: $actorRol,
                    ocurridoEn: $ocurridoEn,
                );

                $alertaGenerada = false;
                $notificacionEnviada = false;

                if ($this->horarioValidador->esFueraDeHorario($ocurridoEn)) {
                    $alerta = AlertaUbicacion::generar(
                        id: $this->alertaRepo->nextIdentity(),
                        cajaId: $cajaId,
                        tipo: TipoAlerta::MovimientoNoAutorizado,
                        datosContexto: ['accion' => 'retiro'],
                    );
                    $this->alertaRepo->guardar($alerta);

                    $notificacion = Notificacion::crear(
                        id: $this->notificacionRepo->nextIdentity(),
                        cajaId: $cajaId,
                        tipo: TipoNotificacion::MovimientoNoAutorizado,
                        datosContexto: ['accion' => 'retiro'],
                    );
                    $this->notificacionRepo->guardar($notificacion);

                    $alertaGenerada = true;
                    $notificacionEnviada = true;
                }

                $this->cajaRepo->guardar($caja);
                $this->ranuraRepo->guardar($ranura);
                $this->ubicacionRepo->guardar($ubicacion);
                $this->eventoRepo->guardar($eventoCiclo);

                foreach ($caja->pullEvents() as $evento) {
                    $this->eventPublisher->publish($evento);
                }

                return [$alertaGenerada, $notificacionEnviada];
            }
        );

        return RegistrarRetiroCajaOutput::fromPrimitives([
            'cajaId' => (string) $cajaId,
            'estadoCaja' => $caja->estadoActual()->valor(),
            'alertaGenerada' => $alertaGenerada,
            'notificacionEnviada' => $notificacionEnviada,
            'ranuraId' => (string) $ranura->id(),
        ]);
    }
}
