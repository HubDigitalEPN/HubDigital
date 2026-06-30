<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarTrazabilidadEspecimen;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\EventoCicloIot;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EventoCicloIotRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UnitTrayEspecimenRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UnitTrayRepository;

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

        return new MovimientoTrazabilidadOutput(
            tipo: $evento->tipoEvento(),
            origen: $this->extraer($datos, 'origen'),
            destino: $this->extraer($datos, 'destino'),
            ocurridoEn: $evento->ocurridoEn(),
            // ActorRol::Sistema->valor() ya es 'sistema', así que un evento sin actor humano
            // se identifica naturalmente como responsable "sistema".
            responsable: $evento->actorRol()->valor(),
        );
    }

    /**
     * Toma el primer valor cuyo nombre de clave empieza con el prefijo dado (origen_ o destino_),
     * independientemente del contenedor (unit_tray, caja, ranura).
     *
     * @param  array<string, mixed>  $datos
     */
    private function extraer(array $datos, string $prefijo): ?string
    {
        foreach ($datos as $clave => $valor) {
            if (str_starts_with((string) $clave, $prefijo) && $valor !== null) {
                return (string) $valor;
            }
        }

        return null;
    }
}
