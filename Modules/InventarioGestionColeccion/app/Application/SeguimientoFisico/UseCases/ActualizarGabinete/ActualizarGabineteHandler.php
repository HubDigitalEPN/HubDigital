<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarGabinete;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Gabinete;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\RanuraGabinete;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\GabineteRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\RanuraGabineteRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\GabineteId;

/**
 * Caso de uso: actualizar el nombre y el total de ranuras de un gabinete, ajustando su
 * conjunto de ranuras para que siempre sea exactamente {1..totalRanuras}.
 *
 * @see ActualizarGabineteInput
 * @see ActualizarGabineteOutput
 */
final class ActualizarGabineteHandler
{
    /**
     * @param  GabineteRepository  $gabineteRepo  Recupera y persiste el gabinete.
     * @param  RanuraGabineteRepository  $ranuraRepo  Crea, recupera y elimina las ranuras del gabinete.
     */
    public function __construct(
        private readonly GabineteRepository $gabineteRepo,
        private readonly RanuraGabineteRepository $ranuraRepo,
    ) {}

    /**
     * Actualiza el gabinete y reconcilia sus ranuras con el nuevo total: elimina las ranuras
     * sobrantes y crea las faltantes. Impide reducir el total si alguna ranura a eliminar
     * todavía tiene una caja, para no perder la trazabilidad de su contenido.
     *
     * @throws \DomainException si el gabinete no existe o si una ranura a eliminar está ocupada.
     */
    public function handle(ActualizarGabineteInput $input): ActualizarGabineteOutput
    {
        $id = GabineteId::desde($input->gabineteId);
        $gabinete = $this->gabineteRepo->buscarPorId($id);

        if ($gabinete === null) {
            throw new \DomainException('Gabinete no encontrado.');
        }

        $nuevoTotal = $input->totalRanuras;

        $existentes = [];
        foreach ($this->ranuraRepo->buscarPorGabinete($id) as $ranura) {
            $existentes[$ranura->numeroRanura()] = $ranura;
        }

        foreach ($existentes as $numero => $ranura) {
            if ($numero > $nuevoTotal && $ranura->estaOcupada()) {
                throw new \DomainException(
                    "No se puede reducir: la ranura {$numero} tiene una caja. Retira la caja primero."
                );
            }
        }

        $actualizado = Gabinete::reconstituir(
            id: $gabinete->id(),
            codigo: $gabinete->codigo(),
            nombre: $input->nombre,
            totalRanuras: $nuevoTotal,
            activo: $gabinete->activo(),
        );

        $this->gabineteRepo->guardar($actualizado);

        // Mantiene el invariante: las ranuras del gabinete son exactamente {1..nuevoTotal}.
        foreach ($existentes as $numero => $ranura) {
            if ($numero > $nuevoTotal) {
                $this->ranuraRepo->eliminar($ranura->id());
            }
        }

        for ($numero = 1; $numero <= $nuevoTotal; $numero++) {
            if (! isset($existentes[$numero])) {
                $this->ranuraRepo->guardar(RanuraGabinete::crear(
                    id: $this->ranuraRepo->nextIdentity(),
                    gabineteId: $id,
                    numeroRanura: $numero,
                ));
            }
        }

        return new ActualizarGabineteOutput;
    }
}
