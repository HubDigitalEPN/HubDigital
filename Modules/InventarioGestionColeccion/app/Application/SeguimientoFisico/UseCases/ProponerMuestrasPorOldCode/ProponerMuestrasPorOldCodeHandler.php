<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ProponerMuestrasPorOldCode;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;

/**
 * Agrupa especímenes con un mismo `old_code` que no tienen muestra enlazada y
 * los propone como una muestra de colecta tentativa.
 *
 * Decisión P0.1: oldCode (tipo "BT2F3") representa una muestra/trampa.
 * El curador revisa cada grupo y, si confirma, crea una `muestra_colecta`
 * vía `RegistrarMuestraColecta` + asocia los especímenes vía
 * `EnlazarEspecimenAMuestra`.
 */
final class ProponerMuestrasPorOldCodeHandler
{
    public function __construct(
        private readonly EspecimenRepositoryInterface $especimenRepo,
    ) {}

    public function handle(ProponerMuestrasPorOldCodeInput $input): ProponerMuestrasPorOldCodeOutput
    {
        $minimo = max(1, $input->minimoEspecimenes);

        /** @var array<string, Especimen[]> $grupos */
        $grupos = [];
        foreach ($this->especimenRepo->buscarTodos() as $especimen) {
            if ($especimen->muestraId() !== null) {
                continue;
            }
            $oldCode = $especimen->oldCode();
            if ($oldCode === null || $oldCode === '') {
                continue;
            }
            $grupos[$oldCode][] = $especimen;
        }

        $items = [];
        foreach ($grupos as $oldCode => $especimenes) {
            if (count($especimenes) < $minimo) {
                continue;
            }

            $items[] = new ProponerMuestrasPorOldCodeItem(
                oldCode: (string) $oldCode,
                totalEspecimenes: count($especimenes),
                especimenIds: array_map(fn (Especimen $e) => (string) $e->id(), $especimenes),
                colectorComun: $this->valorComun(array_map(fn (Especimen $e) => $e->colector(), $especimenes)),
                localidadVerbatimComun: $this->valorComun(array_map(fn (Especimen $e) => $e->localidadVerbatim() ?? '', $especimenes)),
            );
        }

        // Ordenar por total descendente para mostrar primero los grupos más grandes.
        usort($items, fn (ProponerMuestrasPorOldCodeItem $a, ProponerMuestrasPorOldCodeItem $b) => $b->totalEspecimenes <=> $a->totalEspecimenes);

        return new ProponerMuestrasPorOldCodeOutput(
            items: $items,
            totalGrupos: count($items),
        );
    }

    /**
     * Devuelve el valor si todos los elementos coinciden; null si difieren o están vacíos.
     *
     * @param  string[]  $valores
     */
    private function valorComun(array $valores): ?string
    {
        $unicos = array_values(array_unique(array_filter($valores, fn (string $v) => $v !== '')));
        if (count($unicos) !== 1) {
            return null;
        }

        return $unicos[0];
    }
}
