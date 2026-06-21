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

/**
 * Caso de uso: verificar cuánto tiempo lleva una caja fuera de su ranura y reaccionar según el
 * resultado: ninguna acción si está dentro del límite, una notificación preventiva si se acerca,
 * o una alerta de extracción prolongada (marcando la caja) si lo excede. Solo evalúa cajas en
 * tránsito o ya prolongadas y evita duplicar alertas activas.
 *
 * @see VerificarTiemposExtraccionInput
 * @see VerificarTiemposExtraccionOutput
 */
final class VerificarTiemposExtraccionHandler
{
    /**
     * @param  CajaRepository  $cajaRepo  Recupera la caja y persiste su transición a extracción prolongada.
     * @param  UbicacionCajaRepository  $ubicacionRepo  Aporta la última retirada para medir el tiempo fuera.
     * @param  AlertaUbicacionRepository  $alertaRepo  Genera y consulta la alerta de extracción prolongada.
     * @param  NotificacionRepository  $notificacionRepo  Crea la notificación preventiva al acercarse al límite.
     * @param  TransactionManagerPort  $transactionManager  Envuelve la generación de alertas/notificaciones en transacciones.
     * @param  EvaluadorTiempoExtraccion  $evaluador  Servicio de dominio que clasifica el tiempo transcurrido.
     */
    public function __construct(
        private readonly CajaRepository $cajaRepo,
        private readonly UbicacionCajaRepository $ubicacionRepo,
        private readonly AlertaUbicacionRepository $alertaRepo,
        private readonly NotificacionRepository $notificacionRepo,
        private readonly TransactionManagerPort $transactionManager,
        private readonly EvaluadorTiempoExtraccion $evaluador,
    ) {}

    /**
     * Mide el tiempo que la caja lleva fuera desde su última retirada y, según el veredicto del
     * evaluador, no hace nada, emite una notificación preventiva o genera la alerta de extracción
     * prolongada. Devuelve temprano si la caja no está fuera de su posición o no tiene retirada registrada.
     *
     * @throws \DomainException si la caja no existe.
     */
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

    /** Construye la salida de "ninguna acción": la caja está dentro del límite o no es evaluable. */
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

    /** Emite una notificación preventiva (sin alerta) cuando la caja se acerca al límite de tiempo fuera. */
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

    /**
     * Genera la alerta de extracción prolongada cuando el tiempo fuera excede el límite,
     * marcando además la caja como prolongada si venía en tránsito; no duplica si la caja ya
     * está prolongada con una alerta activa.
     */
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
