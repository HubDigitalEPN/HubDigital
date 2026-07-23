<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarTrazabilidadEspecimen;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\EventoCicloIot;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\CajaRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EventoCicloIotRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\GabineteRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\RanuraGabineteRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UnitTrayEspecimenRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UnitTrayRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\RanuraId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\UnitTrayId;

/**
 * Caso de uso: reconstruir la línea de tiempo de un espécimen reuniendo sus propios movimientos
 * más los del unit tray y la caja que lo contienen actualmente, ordenados cronológicamente.
 *
 * ponytail: usa la cadena de contención ACTUAL del espécimen (unit tray vigente → su caja). No
 * reconstruye contenedores históricos; el histórico de cada contenedor sí se incluye completo.
 * Excepción: si el evento trae un snapshot de caja embebido (p. ej. 'destino_caja_id' junto a
 * 'destino_unit_tray_id', escrito por ReubicarEspecimenesHandler), ese snapshot tiene prioridad
 * sobre la caja ACTUAL del tray — evita que un tray reubicado de caja *después* del movimiento
 * retrate el pasado con la caja de hoy. Ver etiquetaUnitTray().
 */
final class ConsultarTrazabilidadEspecimenHandler
{
    public function __construct(
        private readonly EventoCicloIotRepository $eventoRepo,
        private readonly UnitTrayEspecimenRepository $asignacionRepo,
        private readonly UnitTrayRepository $unitTrayRepo,
        private readonly CajaRepository $cajaRepo,
        private readonly RanuraGabineteRepository $ranuraRepo,
        private readonly GabineteRepository $gabineteRepo,
    ) {}

    public function handle(ConsultarTrazabilidadEspecimenInput $input): ConsultarTrazabilidadEspecimenOutput
    {
        $eventos = $this->eventoRepo->buscarPorAgregado('especimen', $input->especimenId);

        $unitTrayId = $this->asignacionRepo->unitTrayDeEspecimen($input->especimenId);
        if ($unitTrayId !== null) {
            $eventos = array_merge($eventos, $this->eventoRepo->buscarPorAgregado('unit_tray', (string) $unitTrayId));

            $tray = $this->unitTrayRepo->buscarPorId($unitTrayId);
            if ($tray !== null) {
                $eventos = array_merge($eventos, $this->eventoRepo->buscarPorAgregado('caja', (string) $tray->cajaId()));
            }
        }

        usort($eventos, static fn (EventoCicloIot $a, EventoCicloIot $b): int => $a->ocurridoEn() <=> $b->ocurridoEn());

        return new ConsultarTrazabilidadEspecimenOutput(
            movimientos: array_map($this->mapearMovimiento(...), $eventos),
        );
    }

    private function mapearMovimiento(EventoCicloIot $evento): MovimientoTrazabilidadOutput
    {
        $datos = $evento->datos();
        [$origen, $destino] = $this->resolverOrigenDestino($evento->tipoEvento(), $datos);

        return new MovimientoTrazabilidadOutput(
            tipo: $evento->tipoEvento(),
            origen: $origen,
            destino: $destino,
            ocurridoEn: $evento->ocurridoEn(),
            // ActorRol::Sistema->valor() ya es 'sistema', así que un evento sin actor humano
            // se identifica naturalmente como responsable "sistema".
            responsable: $evento->actorRol()->valor(),
        );
    }

    /**
     * Los eventos de caja detectados por el ESP32 (ingreso/retiro/reubicación) guardan una
     * sola 'ranura_id' en vez del par origen_/destino_: es el destino salvo en un retiro,
     * donde es de donde salió la caja.
     *
     * @param  array<string, mixed>  $datos
     * @return array{0: ?string, 1: ?string}
     */
    private function resolverOrigenDestino(string $tipoEvento, array $datos): array
    {
        if (array_key_exists('ranura_id', $datos) && $datos['ranura_id'] !== null) {
            $etiqueta = $this->etiquetaRanura((string) $datos['ranura_id']);

            return $tipoEvento === 'caja_retirada' ? [$etiqueta, null] : [null, $etiqueta];
        }

        return [$this->extraer($datos, 'origen'), $this->extraer($datos, 'destino')];
    }

    /**
     * Toma el primer valor cuyo nombre de clave empieza con el prefijo dado (origen_ o destino_)
     * y lo resuelve a una etiqueta legible según el contenedor que nombra la clave (unit_tray,
     * caja, ranura). Si el id no resuelve a una entidad real (p. ej. datos de prueba), cae al
     * valor crudo.
     *
     * @param  array<string, mixed>  $datos
     */
    private function extraer(array $datos, string $prefijo): ?string
    {
        foreach ($datos as $clave => $valor) {
            if (! str_starts_with((string) $clave, $prefijo) || $valor === null) {
                continue;
            }

            $valor = (string) $valor;

            return match (true) {
                str_contains((string) $clave, 'unit_tray') => $this->etiquetaUnitTray($valor, $this->snapshotCaja($datos, $prefijo)),
                str_contains((string) $clave, 'caja') => $this->etiquetaCaja($valor),
                str_contains((string) $clave, 'ranura') => $this->etiquetaRanura($valor),
                default => $valor,
            };
        }

        return null;
    }

    /**
     * Lee el snapshot "<prefijo>_caja_id" que algunos handlers (p. ej. ReubicarEspecimenesHandler)
     * ya escriben junto al id del tray, para no depender de la caja ACTUAL del tray al mostrarlo.
     * Los eventos escritos antes de este fix no lo traen; en ese caso devuelve null y
     * etiquetaUnitTray() cae a la caja actual del tray (comportamiento previo).
     *
     * @param  array<string, mixed>  $datos
     */
    private function snapshotCaja(array $datos, string $prefijo): ?string
    {
        $valor = $datos["{$prefijo}_caja_id"] ?? null;

        return $valor !== null ? (string) $valor : null;
    }

    /**
     * Resuelve un unit tray a "Caja {código} — Tray #N"; cae al id crudo si no existe.
     *
     * La caja va primero: el número de tray es correlativo POR CAJA (reinicia en 1 en cada
     * una), así que dos trays de cajas distintas suelen compartir número — anteponer el tray
     * ("Tray #1 — Caja A" vs "Tray #1 — Caja B") hacía parecer que origen y destino eran el
     * mismo lugar. Con la caja al frente el cambio real salta a la vista de inmediato.
     *
     * $cajaIdAlMomento (opcional) prioriza la caja que tenía el tray CUANDO ocurrió el evento
     * sobre su caja actual: un tray puede reubicarse de caja después de este movimiento, y
     * mostrar la caja de hoy haría ver origen y destino como el mismo lugar aunque en su
     * momento fueran distintos (ver docblock de la clase). El número de tray sigue siendo el
     * actual — no hay snapshot para ese dato (limitación aceptada).
     */
    private function etiquetaUnitTray(string $unitTrayId, ?string $cajaIdAlMomento = null): string
    {
        try {
            $tray = $this->unitTrayRepo->buscarPorId(UnitTrayId::desde($unitTrayId));
        } catch (\InvalidArgumentException) {
            return $unitTrayId;
        }

        if ($tray === null) {
            return $unitTrayId;
        }

        $cajaId = $cajaIdAlMomento ?? (string) $tray->cajaId();

        return "{$this->etiquetaCaja($cajaId)} — Tray #{$tray->numero()}";
    }

    /** Resuelve una caja a "Caja {código}"; cae al id crudo si no existe. */
    private function etiquetaCaja(string $cajaId): string
    {
        try {
            $caja = $this->cajaRepo->buscarPorId(CajaId::desde($cajaId));
        } catch (\InvalidArgumentException) {
            return $cajaId;
        }

        return $caja !== null ? "Caja {$caja->codigo()}" : $cajaId;
    }

    /**
     * Resuelve una ranura a "Gabinete {código} — Ranura #N"; cae al id crudo si no existe.
     * El gabinete va primero por la misma razón que en {@see etiquetaUnitTray()}: el número
     * de ranura reinicia por gabinete.
     */
    private function etiquetaRanura(string $ranuraId): string
    {
        try {
            $ranura = $this->ranuraRepo->buscarPorId(RanuraId::desde($ranuraId));
        } catch (\InvalidArgumentException) {
            return $ranuraId;
        }

        if ($ranura === null) {
            return $ranuraId;
        }

        $gabinete = $this->gabineteRepo->buscarPorId($ranura->gabineteId());
        $etiquetaGabinete = $gabinete !== null ? "Gabinete {$gabinete->codigo()}" : 'gabinete desconocido';

        return "{$etiquetaGabinete} — Ranura #{$ranura->numeroRanura()}";
    }
}
