<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ProcesarSincronizacionEsp32;

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\ContextoEjecucionPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\EventPublisherPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\HorarioValidadorPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\TransactionManagerPort;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\AlertaUbicacion;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Caja;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\EventoCicloIot;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Notificacion;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\RanuraGabinete;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\SincronizacionEsp32;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\UbicacionCaja;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\AlertaUbicacionRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\CajaRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EventoCicloIotRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\GabineteRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\NotificacionRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\OrdenEsperadoFamiliasRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\RanuraGabineteRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\SincronizacionEsp32Repository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UbicacionCajaRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services\EvaluadorOrdenTaxonomico;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CodigoRfid;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoCaja;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\GabineteId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\LecturaRanura;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\RanuraId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TipoAlerta;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TipoNotificacion;

/**
 * Reconcilia el estado de un gabinete contra el snapshot completo enviado por el ESP32.
 *
 * El ESP32 es la fuente de verdad del estado físico. El servidor infiere qué cajas
 * ingresaron, se retiraron o se reubicaron comparando el snapshot con el estado persitido,
 * y ejecuta todas las transiciones en una sola transacción atómica (liberar-primero,
 * asignar-después) para soportar swaps y reinicios del ESP32 sin excepciones de colisión.
 *
 * Secuencia de reconciliación por ranura presente en el payload:
 *   - tag_uid válido = caja debe quedar alojada en esa ranura.
 *   - tag_uid null   = ranura vacía → retirar la caja que la BD tenía allí.
 *   - tag_uid desconocido (no mapea a caja) → no tocar el slot; reportar en tagsDesconocidos.
 *   - slot ausente del payload → no tocar (protección ante barrido parcial por falla de lector).
 *
 * @see ProcesarSincronizacionEsp32Input
 * @see ProcesarSincronizacionEsp32Output
 */
final class ProcesarSincronizacionEsp32Handler
{
    public function __construct(
        private readonly GabineteRepository $gabineteRepo,
        private readonly CajaRepository $cajaRepo,
        private readonly RanuraGabineteRepository $ranuraRepo,
        private readonly UbicacionCajaRepository $ubicacionRepo,
        private readonly EventoCicloIotRepository $eventoRepo,
        private readonly AlertaUbicacionRepository $alertaRepo,
        private readonly NotificacionRepository $notificacionRepo,
        private readonly SincronizacionEsp32Repository $sincronizacionRepo,
        private readonly TransactionManagerPort $transactionManager,
        private readonly HorarioValidadorPort $horarioValidador,
        private readonly ContextoEjecucionPort $contextoEjecucion,
        private readonly EventPublisherPort $eventPublisher,
        private readonly EvaluadorOrdenTaxonomico $evaluadorTaxonomico,
        private readonly OrdenEsperadoFamiliasRepository $ordenFamiliasRepo,
    ) {}

    /**
     * @throws \DomainException si el gabinete no existe.
     */
    public function handle(ProcesarSincronizacionEsp32Input $input): ProcesarSincronizacionEsp32Output
    {
        $gabineteId = GabineteId::desde($input->gabineteId);

        if ($this->gabineteRepo->buscarPorId($gabineteId) === null) {
            throw new \DomainException("Gabinete '{$input->gabineteId}' no encontrado.");
        }

        // Indexar ranuras por número (base 1).
        /** @var array<int, RanuraGabinete> $ranuras */
        $ranuras = [];
        foreach ($this->ranuraRepo->buscarPorGabinete($gabineteId) as $r) {
            $ranuras[$r->numeroRanura()] = $r;
        }

        // Resolver tags a cajas y construir el mapa deseado.
        // numeroRanura → ?CajaId  (solo ranuras presentes en el payload y resolubles).
        /** @var array<int, ?CajaId> $deseado */
        $deseado = [];
        /** @var array<string, Caja> $cajasDeseadas  CajaId string → Caja */
        $cajasDeseadas = [];
        $tagsDesconocidos = [];
        $slotsDesconocidos = [];

        foreach ($input->lecturas as ['slotIndex' => $slotIndex, 'tagUid' => $tagUid]) {
            $numeroRanura = $slotIndex + 1;

            if (! isset($ranuras[$numeroRanura])) {
                $slotsDesconocidos[] = $slotIndex;

                continue;
            }

            if ($tagUid === null) {
                $deseado[$numeroRanura] = null;

                continue;
            }

            $caja = $this->cajaRepo->buscarPorCodigoRfid(CodigoRfid::desde($tagUid));
            if ($caja === null) {
                $tagsDesconocidos[] = $tagUid;

                continue;
            }

            $cajaIdStr = (string) $caja->id();
            $deseado[$numeroRanura] = $caja->id();
            $cajasDeseadas[$cajaIdStr] = $caja;
        }

        // Set de CajaId string de cajas que el barrido quiere colocar en alguna ranura.
        // Usado en pass 1 para distinguir retiro real de reubicación.
        $cajasDestino = array_map(
            fn (CajaId $id) => (string) $id,
            array_filter(array_values($deseado)),
        );

        // ── Transacción principal: pass 1 (liberar + retirar) + pass 2 (asignar) ────────
        [$ingresos, $retiros, $reubicaciones, $sinCambios, $alertasEnTx, $cajasAsignadas] =
            $this->transactionManager->executeTransactional(
                function () use ($ranuras, $deseado, $cajasDeseadas, $cajasDestino): array {
                    $ocurridoEn = new \DateTimeImmutable;
                    $actorId = $this->contextoEjecucion->actorId();
                    $actorRol = $this->contextoEjecucion->actorRol();
                    $ingresos = $retiros = $reubicaciones = $sinCambios = $alertasEnTx = 0;
                    /** @var array<array{Caja, RanuraGabinete, CajaId, RanuraId}> */
                    $cajasAsignadas = [];

                    // ── PASS 1: Liberar ranuras que cambian + procesar retiros ─────────
                    // Se ejecuta por COMPLETO antes del pass 2 para que los swaps (A↔B)
                    // nunca encuentren una ranura ocupada al intentar asignar.
                    /** @var array<string, Caja> $cajasARetirar */
                    $cajasARetirar = [];

                    foreach ($deseado as $nr => $cajaIdDeseada) {
                        $ranura = $ranuras[$nr];
                        $cajaIdActual = $ranura->cajaActualId();

                        $deseadaStr = $cajaIdDeseada !== null ? (string) $cajaIdDeseada : null;
                        $actualStr = $cajaIdActual !== null ? (string) $cajaIdActual : null;

                        if ($deseadaStr === $actualStr) {
                            $sinCambios++;

                            continue;
                        }

                        // Liberar la ranura (garantiza que pass 2 la encuentre vacía).
                        if ($cajaIdActual !== null) {
                            $ranura->liberarCaja();
                            $this->ranuraRepo->guardar($ranura);

                            // Si la caja actual no aparece en ningún destino → retiro real.
                            if (! in_array($actualStr, $cajasDestino, true)
                                && ! isset($cajasARetirar[$actualStr])
                            ) {
                                $cajaRetirada = $this->cajaRepo->buscarPorId($cajaIdActual);
                                if ($cajaRetirada !== null && $cajaRetirada->estadoActual()->estaAlojadaEnRanura()) {
                                    $cajasARetirar[$actualStr] = $cajaRetirada;
                                }
                            }
                        }
                    }

                    // Transicionar a EnTransito y cerrar ubicacion de las cajas retiradas.
                    foreach ($cajasARetirar as $cajaIdStr => $caja) {
                        $cajaId = $caja->id();
                        $ubicacion = $this->ubicacionRepo->buscarActivaPorCaja($cajaId);

                        $caja->retirarDeRanura();

                        if ($ubicacion !== null) {
                            $ubicacion->cerrar($ocurridoEn);
                            $this->ubicacionRepo->guardar($ubicacion);
                        }

                        $this->eventoRepo->guardar(EventoCicloIot::registrar(
                            tipoAgregado: 'caja',
                            agregadoId: $cajaIdStr,
                            tipoEvento: 'caja_retirada',
                            versionEvento: 1,
                            datos: [],
                            actorId: $actorId,
                            actorRol: $actorRol,
                            ocurridoEn: $ocurridoEn,
                        ));

                        if ($this->generarAlertaFueraDeHorario($cajaId, 'retiro', $ocurridoEn)) {
                            $alertasEnTx++;
                        }

                        $this->cajaRepo->guardar($caja);
                        foreach ($caja->pullEvents() as $evento) {
                            $this->eventPublisher->publish($evento);
                        }

                        $retiros++;
                    }

                    // ── PASS 2: Asignar cajas a sus nuevas ranuras ────────────────────
                    // Las ranuras cambiantes están garantizadas vacías por el pass 1.
                    foreach ($deseado as $nr => $cajaIdDeseada) {
                        if ($cajaIdDeseada === null) {
                            // Slot marcado vacío — ya gestionado en pass 1 como retiro.
                            continue;
                        }

                        $ranura = $ranuras[$nr];
                        $cajaIdActual = $ranura->cajaActualId(); // ya null si fue liberada
                        $deseadaStr = (string) $cajaIdDeseada;
                        $actualStr = $cajaIdActual !== null ? (string) $cajaIdActual : null;

                        if ($deseadaStr === $actualStr) {
                            // Sin cambio (también contado en pass 1, no doble-contar).
                            continue;
                        }

                        $caja = $cajasDeseadas[$deseadaStr];
                        $cajaId = $caja->id();
                        $ranuraId = $ranura->id();

                        // Cerrar ubicacion anterior si la caja viene de otra ranura.
                        $ubicacionAnterior = $this->ubicacionRepo->buscarActivaPorCaja($cajaId);
                        if ($ubicacionAnterior !== null
                            && ! $ubicacionAnterior->ranuraGabineteId()->equals($ranuraId)
                        ) {
                            $ubicacionAnterior->cerrar($ocurridoEn);
                            $this->ubicacionRepo->guardar($ubicacionAnterior);
                        }

                        // Resolver alerta de extracción prolongada si la caja regresaba.
                        if ($caja->estadoActual()->equals(EstadoCaja::ExtraccionProlongada)) {
                            $this->resolverAlertaExtraccionProlongada($cajaId);
                        }

                        // Transición de estado y tipo de evento.
                        if ($caja->estadoActual()->estaAlojadaEnRanura()) {
                            // Reubicación: la caja ya estaba en el gabinete, ahora en otra ranura.
                            $caja->reconciliarEnRanura($ranuraId);
                            $tipoEvento = 'caja_reubicada';
                            $reubicaciones++;
                        } else {
                            // Ingreso normal (EnTransito) o devolución (ExtraccionProlongada).
                            $caja->ingresarEnRanura($ranuraId);
                            $tipoEvento = 'caja_ingresada';
                            $ingresos++;
                        }

                        // La ranura está garantizadamente vacía → asignar sin riesgo de colisión.
                        $ranura->asignarCaja($cajaId);

                        $nuevaUbicacion = UbicacionCaja::registrar(
                            id: $this->ubicacionRepo->nextIdentity(),
                            cajaId: $cajaId,
                            ranuraGabineteId: $ranuraId,
                            ingresadaEn: $ocurridoEn,
                        );

                        $this->eventoRepo->guardar(EventoCicloIot::registrar(
                            tipoAgregado: 'caja',
                            agregadoId: $deseadaStr,
                            tipoEvento: $tipoEvento,
                            versionEvento: 1,
                            datos: ['ranura_id' => (string) $ranuraId],
                            actorId: $actorId,
                            actorRol: $actorRol,
                            ocurridoEn: $ocurridoEn,
                        ));

                        if ($this->generarAlertaFueraDeHorario($cajaId, 'ingreso', $ocurridoEn)) {
                            $alertasEnTx++;
                        }

                        $this->cajaRepo->guardar($caja);
                        $this->ranuraRepo->guardar($ranura);
                        $this->ubicacionRepo->guardar($nuevaUbicacion);

                        foreach ($caja->pullEvents() as $evento) {
                            $this->eventPublisher->publish($evento);
                        }

                        $cajasAsignadas[] = [$caja, $ranura, $cajaId, $ranuraId];
                    }

                    return [$ingresos, $retiros, $reubicaciones, $sinCambios, $alertasEnTx, $cajasAsignadas];
                },
            );

        // ── PASS 3: Orden taxonómico ──────────────────────────────────────────────────
        // Se evalúa FUERA de la transacción principal (igual que en RegistrarIngresoCaja)
        // para que todas las asignaciones del barrido estén ya persistidas: así buscar
        // vecinas devuelve el estado final, no el estado a media reconciliación.
        $alertasTaxonomicas = 0;
        /** @var array<int, bool> $incongruentePorNr  numeroRanura → bool */
        $incongruentePorNr = [];

        foreach ($cajasAsignadas as [$caja, $ranura, $cajaId, $ranuraId]) {
            if ($this->evaluarOrdenTaxonomico($caja, $ranura, $cajaId, $ranuraId)) {
                $alertasTaxonomicas++;
                $incongruentePorNr[$ranura->numeroRanura()] = true;
            }
        }

        // ── Persistir snapshot del barrido ────────────────────────────────────────────
        $lecturasSincronizacion = [];
        foreach ($input->lecturas as ['slotIndex' => $slotIndex, 'tagUid' => $tagUid]) {
            $numeroRanura = $slotIndex + 1;
            if (! isset($ranuras[$numeroRanura])) {
                continue;
            }
            $lecturasSincronizacion[] = LecturaRanura::crear(
                numeroRanura: $numeroRanura,
                rfidDetectado: $tagUid !== null ? CodigoRfid::desde($tagUid) : null,
                esIncongruente: $incongruentePorNr[$numeroRanura] ?? false,
            );
        }

        $sincronizacion = SincronizacionEsp32::registrar(
            id: $this->sincronizacionRepo->nextIdentity(),
            gabineteId: $gabineteId,
            lecturas: $lecturasSincronizacion,
            realizadaEn: new \DateTimeImmutable,
        );
        $this->sincronizacionRepo->guardar($sincronizacion);

        $alertasTotal = $alertasEnTx + $alertasTaxonomicas;
        $conIncongruencias = $sincronizacion->tieneIncongruencias();

        return new ProcesarSincronizacionEsp32Output(
            ingresos: $ingresos,
            retiros: $retiros,
            reubicaciones: $reubicaciones,
            sinCambios: $sinCambios,
            tagsDesconocidos: $tagsDesconocidos,
            slotsDesconocidos: $slotsDesconocidos,
            alertasGeneradas: $alertasTotal,
            conIncongruencias: $conIncongruencias,
            sincronizacionId: (string) $sincronizacion->id(),
        );
    }

    // ── Helpers privados ──────────────────────────────────────────────────────────────

    private function generarAlertaFueraDeHorario(
        CajaId $cajaId,
        string $accion,
        \DateTimeImmutable $ocurridoEn,
    ): bool {
        if (! $this->horarioValidador->esFueraDeHorario($ocurridoEn)) {
            return false;
        }

        $this->alertaRepo->guardar(AlertaUbicacion::generar(
            id: $this->alertaRepo->nextIdentity(),
            cajaId: $cajaId,
            tipo: TipoAlerta::MovimientoNoAutorizado,
            datosContexto: ['accion' => $accion],
        ));

        $this->notificacionRepo->guardar(Notificacion::crear(
            id: $this->notificacionRepo->nextIdentity(),
            cajaId: $cajaId,
            tipo: TipoNotificacion::MovimientoNoAutorizado,
            datosContexto: ['accion' => $accion],
        ));

        return true;
    }

    private function resolverAlertaExtraccionProlongada(CajaId $cajaId): void
    {
        $alerta = $this->alertaRepo->buscarActivaPorCaja($cajaId);
        if ($alerta !== null && $alerta->tipo()->equals(TipoAlerta::ExtraccionProlongada)) {
            $alerta->resolver('Caja devuelta a su ranura (barrido ESP32)');
            $this->alertaRepo->guardar($alerta);
        }
    }

    private function evaluarOrdenTaxonomico(
        Caja $caja,
        RanuraGabinete $ranura,
        CajaId $cajaId,
        RanuraId $ranuraId,
    ): bool {
        $vecinas = $this->ranuraRepo->buscarVecinasOcupadas(
            $ranura->gabineteId(),
            $ranura->numeroRanura(),
        );

        $cajaAnterior = null;
        $cajaSiguiente = null;

        foreach ($vecinas as $ranuraVecina) {
            $cajaVecina = $this->cajaRepo->buscarPorId($ranuraVecina->cajaActualId());
            if ($ranuraVecina->numeroRanura() < $ranura->numeroRanura()) {
                $cajaAnterior = $cajaVecina;
            } else {
                $cajaSiguiente = $cajaVecina;
            }
        }

        $ordenFamilias = $this->ordenFamiliasRepo->obtener();
        $tipoAlerta = $this->evaluadorTaxonomico->evaluar(
            $caja, $cajaAnterior, $cajaSiguiente, $ordenFamilias,
        );

        if ($tipoAlerta === null) {
            return false;
        }

        $this->transactionManager->executeTransactional(
            function () use ($caja, $cajaId, $ranuraId, $tipoAlerta): void {
                $alerta = AlertaUbicacion::generar(
                    id: $this->alertaRepo->nextIdentity(),
                    cajaId: $cajaId,
                    tipo: $tipoAlerta,
                    datosContexto: ['ranura_id' => (string) $ranuraId],
                );
                $this->alertaRepo->guardar($alerta);

                if ($tipoAlerta->equals(TipoAlerta::FamiliaNoAsignada)) {
                    $caja->marcarPendienteClasificacion();
                    $this->cajaRepo->guardar($caja);
                }
            },
        );

        return true;
    }
}
