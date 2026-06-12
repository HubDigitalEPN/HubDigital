<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\UnitTray;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UnitTrayRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\ClasificacionTaxonomica;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\UnitTrayId;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models\UnitTrayEloquentModel;

/**
 * Implementación Eloquent del repositorio de unit trays: traduce entre la entidad UnitTray y
 * su modelo persistido (incluyendo el aplanado de la clasificación dominante a JSON) y resuelve
 * la numeración correlativa de bandejas dentro de cada caja.
 */
class EloquentUnitTrayRepository implements UnitTrayRepository
{
    /** Genera un nuevo identificador de unit tray antes de persistirlo. */
    public function nextIdentity(): UnitTrayId
    {
        return UnitTrayId::generar();
    }

    /** Calcula el siguiente número correlativo de bandeja dentro de una caja (máximo actual + 1). */
    public function siguienteNumero(CajaId $cajaId): int
    {
        $maximo = UnitTrayEloquentModel::where('caja_id', (string) $cajaId)->max('numero');

        return ((int) $maximo) + 1;
    }

    /** Inserta o actualiza el unit tray según su id, serializando su clasificación dominante. */
    public function guardar(UnitTray $unitTray): void
    {
        UnitTrayEloquentModel::updateOrCreate(
            ['id' => (string) $unitTray->id()],
            [
                'caja_id' => (string) $unitTray->cajaId(),
                'numero' => $unitTray->numero(),
                'clasificacion_dominante' => $this->clasificacionToArray($unitTray->clasificacionDominante()),
            ]
        );
    }

    /** Recupera un unit tray por su identificador. */
    public function buscarPorId(UnitTrayId $id): ?UnitTray
    {
        $model = UnitTrayEloquentModel::find((string) $id);

        return $model ? $this->toDomain($model) : null;
    }

    /**
     * Lista los unit trays de una caja ordenados por su número.
     *
     * @return UnitTray[]
     */
    public function buscarPorCaja(CajaId $cajaId): array
    {
        return UnitTrayEloquentModel::where('caja_id', (string) $cajaId)
            ->orderBy('numero')
            ->get()
            ->map(fn ($m) => $this->toDomain($m))
            ->all();
    }

    /** Elimina el unit tray indicado de la persistencia. */
    public function eliminar(UnitTrayId $id): void
    {
        UnitTrayEloquentModel::destroy((string) $id);
    }

    /** Reconstituye la entidad UnitTray a partir de la fila persistida. */
    private function toDomain(UnitTrayEloquentModel $model): UnitTray
    {
        return UnitTray::reconstituir(
            id: UnitTrayId::desde($model->id),
            cajaId: CajaId::desde($model->caja_id),
            numero: $model->numero,
            clasificacionDominante: $this->arrayToClasificacion($model->clasificacion_dominante),
        );
    }

    /** Aplana la clasificación dominante a un array asociativo para guardarla como JSON, o null si está vacía. */
    private function clasificacionToArray(?ClasificacionTaxonomica $clasificacion): ?array
    {
        if ($clasificacion === null || $clasificacion->estaVacia()) {
            return null;
        }

        // La clasificación dominante del tray es de un solo taxón; aun así se serializa como
        // conjunto para mantener el mismo formato que la caja y no duplicar el escalar.
        return [
            'orden' => $clasificacion->orden(),
            'suborden' => $clasificacion->suborden(),
            'superfamilia' => $clasificacion->superfamilia(),
            'familia' => $clasificacion->familia(),
            'especie' => $clasificacion->especie(),
            'subfamilias' => $clasificacion->subfamilias(),
            'generos' => $clasificacion->generos(),
        ];
    }

    /** Rehidrata la clasificación dominante desde el array persistido, o null si no hay datos. */
    private function arrayToClasificacion(?array $data): ?ClasificacionTaxonomica
    {
        if ($data === null) {
            return null;
        }

        $clasificacion = ClasificacionTaxonomica::desde(
            orden: $data['orden'] ?? null,
            suborden: $data['suborden'] ?? null,
            superfamilia: $data['superfamilia'] ?? null,
            familia: $data['familia'] ?? null,
            especie: $data['especie'] ?? null,
        )->conSubfamiliasYGeneros(
            $data['subfamilias'] ?? [],
            $data['generos'] ?? [],
        );

        return $clasificacion->estaVacia() ? null : $clasificacion;
    }
}
