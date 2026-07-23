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
                str_contains((string) $clave, 'unit_tray') => $this->etiquetaUnitTray($valor),
                str_contains((string) $clave, 'caja') => $this->etiquetaCaja($valor),
                str_contains((string) $clave, 'ranura') => $this->etiquetaRanura($valor),
                default => $valor,
            };
        }

        return null;
    }

    /** Resuelve un unit tray a "Tray #N — Caja {código}"; cae al id crudo si no existe. */
    private function etiquetaUnitTray(string $unitTrayId): string
    {
        try {
            $tray = $this->unitTrayRepo->buscarPorId(UnitTrayId::desde($unitTrayId));
        } catch (\InvalidArgumentException) {
            return $unitTrayId;
        }

        return $tray !== null
            ? "Tray #{$tray->numero()} — {$this->etiquetaCaja((string) $tray->cajaId())}"
            : $unitTrayId;
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

    /** Resuelve una ranura a "Ranura #N — Gabinete {código}"; cae al id crudo si no existe. */
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

        return "Ranura #{$ranura->numeroRanura()} — {$etiquetaGabinete}";
    }
}
