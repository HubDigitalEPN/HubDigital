<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\UnitTray;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UnitTrayRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\ClasificacionTaxonomica;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\UnitTrayId;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models\UnitTrayEloquentModel;

class EloquentUnitTrayRepository implements UnitTrayRepository
{
    public function nextIdentity(): UnitTrayId
    {
        return UnitTrayId::generar();
    }

    public function siguienteNumero(CajaId $cajaId): int
    {
        $maximo = UnitTrayEloquentModel::where('caja_id', (string) $cajaId)->max('numero');

        return ((int) $maximo) + 1;
    }

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

    public function buscarPorId(UnitTrayId $id): ?UnitTray
    {
        $model = UnitTrayEloquentModel::find((string) $id);

        return $model ? $this->toDomain($model) : null;
    }

    /** @return UnitTray[] */
    public function buscarPorCaja(CajaId $cajaId): array
    {
        return UnitTrayEloquentModel::where('caja_id', (string) $cajaId)
            ->orderBy('numero')
            ->get()
            ->map(fn ($m) => $this->toDomain($m))
            ->all();
    }

    public function eliminar(UnitTrayId $id): void
    {
        UnitTrayEloquentModel::destroy((string) $id);
    }

    private function toDomain(UnitTrayEloquentModel $model): UnitTray
    {
        return UnitTray::reconstituir(
            id: UnitTrayId::desde($model->id),
            cajaId: CajaId::desde($model->caja_id),
            numero: $model->numero,
            clasificacionDominante: $this->arrayToClasificacion($model->clasificacion_dominante),
        );
    }

    private function clasificacionToArray(?ClasificacionTaxonomica $clasificacion): ?array
    {
        if ($clasificacion === null || $clasificacion->estaVacia()) {
            return null;
        }

        return [
            'orden' => $clasificacion->orden(),
            'suborden' => $clasificacion->suborden(),
            'superfamilia' => $clasificacion->superfamilia(),
            'familia' => $clasificacion->familia(),
            'subfamilia' => $clasificacion->subfamilia(),
            'genero' => $clasificacion->genero(),
            'especie' => $clasificacion->especie(),
        ];
    }

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
            subfamilia: $data['subfamilia'] ?? null,
            genero: $data['genero'] ?? null,
            especie: $data['especie'] ?? null,
        );

        return $clasificacion->estaVacia() ? null : $clasificacion;
    }
}
