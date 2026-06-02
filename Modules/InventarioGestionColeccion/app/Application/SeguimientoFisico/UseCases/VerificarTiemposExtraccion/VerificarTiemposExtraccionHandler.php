<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\VerificarTiemposExtraccion;

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\TransactionManagerPort;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\AlertaUbicacion;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Caja;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Notificacion;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\AlertaUbicacionRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\CajaRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\NotificacionRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UbicacionCajaRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services\EvaluadorTiempoExtraccion;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoCaja;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\ResultadoTiempoExtraccion;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TipoAlerta;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TipoNotificacion;

final class VerificarTiemposExtraccionHandler
{
    public function __construct(
        private readonly CajaRepository $cajaRepo,
        private readonly UbicacionCajaRepository $ubicacionRepo,
        private readonly AlertaUbicacionRepository $alertaRepo,
        private readonly NotificacionRepository $notificacionRepo,
        private readonly TransactionManagerPort $transactionManager,
        private readonly EvaluadorTiempoExtraccion $evaluador,
    ) {}

    public function handle(VerificarTiemposExtraccionInput $input): VerificarTiemposExtraccionOutput
    {
        $cajaId = CajaId::desde($input->cajaId);

        $caja = $this->cajaRepo->buscarPorId($cajaId);
        if ($caja === null) {
            throw new \DomainException("Caja '{$input->cajaId}' no encontrada.");
        }

        // Solo se evalúan cajas fuera de su posición (en tránsito o ya prolongadas).
        $estado = $caja->estadoActual();
        if (! $estado->equals(EstadoCaja::EnTransito) && ! $estado->equals(EstadoCaja::ExtraccionProlongada)) {
            return $this->sinAccion($cajaId, $caja->estadoActual());
        }

        $ultimaRetirada = $this->ubicacionRepo->buscarUltimaRetiradaPorCaja($cajaId);
        if ($ultimaRetirada === null || $ultimaRetirada->retiradaEn() === null) {
            return $this->sinAccion($cajaId, $caja->estadoActual());
        }

        $resultado = $this->evaluador->evaluar(
            retiradaEn: $ultimaRetirada->retiradaEn(),
            ahora: new \DateTimeImmutable,
            limiteHoras: $input->limiteDiasHabiles * 24,
        );

        return match ($resultado) {
            ResultadoTiempoExtraccion::DentroDelLimite => $this->sinAccion($cajaId, $caja->estadoActual()),
            ResultadoTiempoExtraccion::ProximaAlLimite => $this->notificarPreventivamente($cajaId, $caja->estadoActual()),
            ResultadoTiempoExtraccion::Excedida => $this->generarAlertaProlongada($caja, $cajaId),
        };
    }

    private function sinAccion(CajaId $cajaId, EstadoCaja $estado): VerificarTiemposExtraccionOutput
    {
        return VerificarTiemposExtraccionOutput::fromPrimitives([
            'cajaId' => (string) $cajaId,
            'estadoCaja' => $estado->valor(),
            'alertaGenerada' => false,
            'notificacionPreventiva' => false,
            'tipoAlerta' => null,
        ]);
    }

    private function notificarPreventivamente(CajaId $cajaId, EstadoCaja $estado): VerificarTiemposExtraccionOutput
    {
        $this->transactionManager->executeTransactional(function () use ($cajaId): void {
            $notificacion = Notificacion::crear(
                id: $this->notificacionRepo->nextIdentity(),
                cajaId: $cajaId,
                tipo: TipoNotificacion::ExtraccionProximaAlLimite,
                datosContexto: ['motivo' => 'tiempo_extraccion_proximo_al_limite'],
            );
            $this->notificacionRepo->guardar($notificacion);
        });

        return VerificarTiemposExtraccionOutput::fromPrimitives([
            'cajaId' => (string) $cajaId,
            'estadoCaja' => $estado->valor(),
            'alertaGenerada' => false,
            'notificacionPreventiva' => true,
            'tipoAlerta' => null,
        ]);
    }

    private function generarAlertaProlongada(Caja $caja, CajaId $cajaId): VerificarTiemposExtraccionOutput
    {
        // Si la caja ya está prolongada y tiene una alerta activa, no duplicar.
        if ($caja->estadoActual()->equals(EstadoCaja::ExtraccionProlongada)
            && $this->alertaRepo->buscarActivaPorCaja($cajaId) !== null) {
            return $this->sinAccion($cajaId, $caja->estadoActual());
        }

        $this->transactionManager->executeTransactional(function () use ($caja, $cajaId): void {
            if ($caja->estadoActual()->equals(EstadoCaja::EnTransito)) {
                $caja->marcarExtraccionProlongada();
                $this->cajaRepo->guardar($caja);
            }

            $alerta = AlertaUbicacion::generar(
                id: $this->alertaRepo->nextIdentity(),
                cajaId: $cajaId,
                tipo: TipoAlerta::ExtraccionProlongada,
                datosContexto: ['motivo' => 'tiempo_extraccion_excedido'],
            );
            $this->alertaRepo->guardar($alerta);
        });

        return VerificarTiemposExtraccionOutput::fromPrimitives([
            'cajaId' => (string) $cajaId,
            'estadoCaja' => $caja->estadoActual()->valor(),
            'alertaGenerada' => true,
            'notificacionPreventiva' => false,
            'tipoAlerta' => TipoAlerta::ExtraccionProlongada->valor(),
        ]);
    }
}
