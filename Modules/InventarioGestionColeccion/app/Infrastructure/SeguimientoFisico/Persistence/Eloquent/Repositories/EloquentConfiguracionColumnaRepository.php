<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories;

use Illuminate\Support\Str;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\ConfiguracionColumna;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\ConfiguracionColumnaRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\PrioridadColumna;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models\ConfiguracionColumnaEloquentModel;

class EloquentConfiguracionColumnaRepository implements ConfiguracionColumnaRepositoryInterface
{
    public function nextIdentity(): string
    {
        return (string) Str::uuid();
    }

    public function buscarPorPantallaYClave(string $pantalla, string $claveColumna): ?ConfiguracionColumna
    {
        $model = ConfiguracionColumnaEloquentModel::where('pantalla', $pantalla)
            ->where('clave_columna', $claveColumna)
            ->first();

        return $model !== null ? $this->toDomain($model) : null;
    }

    /** @return ConfiguracionColumna[] */
    public function listarPorPantalla(string $pantalla): array
    {
        return ConfiguracionColumnaEloquentModel::where('pantalla', $pantalla)
            ->get()
            ->map(fn ($m) => $this->toDomain($m))
            ->all();
    }

    public function guardar(ConfiguracionColumna $config): void
    {
        ConfiguracionColumnaEloquentModel::updateOrCreate(
            ['pantalla' => $config->pantalla(), 'clave_columna' => $config->claveColumna()],
            [
                'id' => $config->id(),
                'prioridad' => $config->prioridad()->value,
                'actualizado_por_id' => $config->actualizadoPorId(),
            ]
        );
    }

    private function toDomain(ConfiguracionColumnaEloquentModel $model): ConfiguracionColumna
    {
        return ConfiguracionColumna::reconstituir(
            id: $model->id,
            pantalla: $model->pantalla,
            claveColumna: $model->clave_columna,
            prioridad: PrioridadColumna::from($model->prioridad),
            actualizadoPorId: $model->actualizado_por_id,
        );
    }
}
