<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarRetiroCaja;

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
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\ActorRol;
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
    ) {}

    public function handle(RegistrarRetiroCajaInput $input): RegistrarRetiroCajaOutput
    {
        $cajaId = CajaId::desde($input->cajaId);
        $actorRol = ActorRol::from($input->actorRol);

        $caja = $this->cajaRepo->buscarPorId($cajaId);
        $ranuraActualId = $caja->ranuraActualId();
        $ranura = $this->ranuraRepo->buscarPorId($ranuraActualId);
        $ubicacion = $this->ubicacionRepo->buscarActivaPorCaja($cajaId);

        [$alertaGenerada, $notificacionEnviada] = $this->transactionManager->executeTransactional(
            function () use ($caja, $ranura, $ubicacion, $cajaId, $actorRol, $input): array {
                $caja->retirarDeRanura();
                $ranura->liberarCaja();
                $ubicacion->cerrar(new \DateTimeImmutable);

                $eventoCiclo = EventoCicloIot::registrar(
                    tipoAgregado: 'caja',
                    agregadoId: (string) $cajaId,
                    tipoEvento: 'caja_retirada',
                    versionEvento: 1,
                    datos: ['ranura_id' => (string) $ranura->id()],
                    actorId: null,
                    actorRol: $actorRol,
                    ocurridoEn: new \DateTimeImmutable,
                );

                $alertaGenerada = false;
                $notificacionEnviada = false;

                if ($input->fueraDeHorario) {
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
                    event($evento);
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
