<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarIngresoCaja;

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\TransactionManagerPort;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\AlertaUbicacion;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\EventoCicloIot;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Notificacion;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\UbicacionCaja;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\AlertaUbicacionRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\CajaRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EventoCicloIotRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\NotificacionRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\RanuraGabineteRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UbicacionCajaRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\ActorRol;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;
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
    ) {}

    public function handle(RegistrarIngresoCajaInput $input): RegistrarIngresoCajaOutput
    {
        $cajaId = CajaId::desde($input->cajaId);
        $ranuraId = RanuraId::desde($input->ranuraId);
        $actorRol = ActorRol::from($input->actorRol);

        $caja = $this->cajaRepo->buscarPorId($cajaId);
        $ranura = $this->ranuraRepo->buscarPorId($ranuraId);

        [$ubicacion, $alertaGenerada] = $this->transactionManager->executeTransactional(
            function () use ($caja, $ranura, $cajaId, $ranuraId, $actorRol, $input): array {
                $caja->ingresarEnRanura($ranuraId);
                $ranura->asignarCaja($cajaId);

                $ubicacion = UbicacionCaja::registrar(
                    id: $this->ubicacionRepo->nextIdentity(),
                    cajaId: $cajaId,
                    ranuraGabineteId: $ranuraId,
                    ingresadaEn: new \DateTimeImmutable,
                );

                $eventoCiclo = EventoCicloIot::registrar(
                    tipoAgregado: 'caja',
                    agregadoId: (string) $cajaId,
                    tipoEvento: 'caja_ingresada',
                    versionEvento: 1,
                    datos: ['ranura_id' => (string) $ranuraId],
                    actorId: null,
                    actorRol: $actorRol,
                    ocurridoEn: new \DateTimeImmutable,
                );

                $alertaGenerada = false;

                if ($input->fueraDeHorario) {
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
                    event($evento);
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
}
