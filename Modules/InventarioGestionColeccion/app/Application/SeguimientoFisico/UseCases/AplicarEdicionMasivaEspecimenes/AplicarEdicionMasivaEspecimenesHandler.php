<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\AplicarEdicionMasivaEspecimenes;

use DateTimeImmutable;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\TransactionManagerPort;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\DetalleEdicionMasiva;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\EdicionMasiva;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\BitacoraEdicionMasivaRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services\NormalizadorValorCampoEspecimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;

/**
 * Fija el mismo valor en un campo de todos los especímenes seleccionados.
 *
 * Sirve también para la edición de una sola celda: es esta misma operación con
 * un único id y otro tipo en la bitácora.
 *
 * Dos garantías que dan forma al código:
 *
 *  1. **Solo se registra lo que de verdad cambia.** Las filas que ya tenían el
 *     valor no entran en el detalle. Si entraran, deshacer las "revertiría" a un
 *     valor que nunca tuvieron que abandonar.
 *  2. **La instantánea del valor previo se lee dentro de la transacción**, nunca
 *     de lo que mandó el navegador. Así, si una fila cambió mientras el curador
 *     tenía el panel abierto, la bitácora guarda su valor real y el deshacer
 *     sigue siendo correcto.
 */
final class AplicarEdicionMasivaEspecimenesHandler
{
    public function __construct(
        private readonly EspecimenRepositoryInterface $especimenRepo,
        private readonly BitacoraEdicionMasivaRepositoryInterface $bitacoraRepo,
        private readonly NormalizadorValorCampoEspecimen $normalizador,
        private readonly TransactionManagerPort $transacciones,
    ) {}

    public function handle(AplicarEdicionMasivaEspecimenesInput $input): AplicarEdicionMasivaEspecimenesOutput
    {
        $ids = array_values(array_unique(array_filter(
            $input->especimenIds,
            fn ($id) => is_string($id) && EspecimenId::esValido($id),
        )));

        if ($ids === []) {
            throw new \InvalidArgumentException('No hay especímenes seleccionados.');
        }

        $valorNuevo = $input->vaciar
            ? $this->normalizador->normalizar($input->campo, null)
            : $this->normalizador->normalizar($input->campo, $input->valor);

        $ejecutar = function () use ($ids, $input, $valorNuevo): AplicarEdicionMasivaEspecimenesOutput {
            $previos = $this->especimenRepo->valoresDeCampoPorIds($ids, $input->campo);

            $aCambiar = array_filter($previos, fn (?string $previo) => $previo !== $valorNuevo);
            $sinCambio = count($previos) - count($aCambiar);

            if ($aCambiar === []) {
                return new AplicarEdicionMasivaEspecimenesOutput(0, $sinCambio);
            }

            $muestra = $this->construirMuestra(array_keys($aCambiar), $aCambiar, fn () => $valorNuevo);

            if ($input->simular) {
                return new AplicarEdicionMasivaEspecimenesOutput(count($aCambiar), $sinCambio, $muestra);
            }

            $edicionId = $this->registrarBitacora($input, $aCambiar, fn () => $valorNuevo, $valorNuevo);

            $this->especimenRepo->fijarCampoPorIds(array_keys($aCambiar), $input->campo, $valorNuevo);

            return new AplicarEdicionMasivaEspecimenesOutput(count($aCambiar), $sinCambio, $muestra, $edicionId);
        };

        // Una simulación no escribe: envolverla en una transacción solo tomaría
        // bloqueos sin motivo.
        return $input->simular ? $ejecutar() : $this->transacciones->executeTransactional($ejecutar);
    }

    /**
     * @param  array<string, string|null>  $previos
     * @param  callable(string): ?string  $valorDe
     */
    private function registrarBitacora(
        AplicarEdicionMasivaEspecimenesInput $input,
        array $previos,
        callable $valorDe,
        ?string $valorComun,
    ): string {
        $edicionId = $this->bitacoraRepo->nextIdentity();
        $ahora = new DateTimeImmutable;

        // Cabecera y detalle ANTES del UPDATE: si el catálogo se modificase y la
        // bitácora fallara después, el cambio quedaría imposible de deshacer.
        $this->bitacoraRepo->guardar(EdicionMasiva::registrar(
            id: $edicionId,
            tipo: $input->tipo,
            campo: $input->campo,
            valorAplicado: $valorComun,
            totalAfectados: count($previos),
            creadoEn: $ahora,
            actorId: $input->actorId,
            actorNombre: $input->actorNombre,
        ));

        $detalles = [];
        foreach ($previos as $especimenId => $previo) {
            $detalles[] = DetalleEdicionMasiva::registrar(
                id: $this->bitacoraRepo->nextIdentity(),
                edicionId: $edicionId,
                especimenId: $especimenId,
                valorPrevio: $previo,
                valorAplicado: $valorDe($especimenId),
            );
        }
        $this->bitacoraRepo->guardarDetalles($detalles);

        return $edicionId;
    }

    /**
     * @param  string[]  $ids
     * @param  array<string, string|null>  $previos
     * @param  callable(string): ?string  $valorDe
     * @return list<array{codigoCatalogo: string, previo: ?string, nuevo: ?string}>
     */
    private function construirMuestra(array $ids, array $previos, callable $valorDe): array
    {
        $primeros = array_slice($ids, 0, 20);
        $codigos = [];
        foreach ($this->especimenRepo->buscarPorIds($primeros) as $especimen) {
            $codigos[(string) $especimen->id()] = $especimen->codigoCatalogo();
        }

        return array_values(array_map(
            fn (string $id) => [
                'codigoCatalogo' => $codigos[$id] ?? $id,
                'previo' => $previos[$id] ?? null,
                'nuevo' => $valorDe($id),
            ],
            $primeros,
        ));
    }
}
