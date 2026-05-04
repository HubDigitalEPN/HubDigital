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
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TipoAlerta;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TipoNotificacion;

final class RegistrarRetiroCajaHandler
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

    public function handle(RegistrarRetiroCajaInput $input): RegistrarRetiroCajaOutput
    {
        $cajaId = CajaId::desde($input->cajaId);
        $actorRol = $this->contextoEjecucion->actorRol();
        $actorId = $this->contextoEjecucion->actorId();

        $caja = $this->cajaRepo->buscarPorId($cajaId);
        // Fallback to internal active location logic if ranura isn't provided or match it if we want to be strict.
        // We'll trust the domain validation from the actual entity if ranura mismatch happens.
        $ranuraActualId = $caja->ranuraActualId();
        $ranura = $this->ranuraRepo->buscarPorId($ranuraActualId);
        $ubicacion = $this->ubicacionRepo->buscarActivaPorCaja($cajaId);

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
        ]);
    }
}
