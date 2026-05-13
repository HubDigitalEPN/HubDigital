<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Taxon;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\TaxonRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoTaxon;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\RangoTaxonomico;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TaxonId;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models\TaxonEloquentModel;

class EloquentTaxonRepository implements TaxonRepositoryInterface
{
    public function nextIdentity(): TaxonId
    {
        return TaxonId::generar();
    }

    public function guardar(Taxon $taxon): void
    {
        TaxonEloquentModel::updateOrCreate(
            ['id' => (string) $taxon->id()],
            [
                'nombre_cientifico' => $taxon->nombreCientifico(),
                'rango' => $taxon->rango()->value,
                'autor' => $taxon->autor(),
                'anio_descripcion' => $taxon->anioDescripcion(),
                'estado' => $taxon->estado()->value,
                'padre_id' => $taxon->padreId() ? (string) $taxon->padreId() : null,
            ]
        );
    }

    public function buscarPorId(TaxonId $id): ?Taxon
    {
        $model = TaxonEloquentModel::find((string) $id);

        return $model ? $this->toDomain($model) : null;
    }

    public function buscarPorNombreYRango(string $nombreCientifico, RangoTaxonomico $rango): ?Taxon
    {
        $model = TaxonEloquentModel::where('nombre_cientifico', $nombreCientifico)
            ->where('rango', $rango->value)
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    /** @return Taxon[] */
    public function buscarPorNombreContiene(string $nombre): array
    {
        return TaxonEloquentModel::where('nombre_cientifico', 'ilike', "%{$nombre}%")
            ->get()
            ->map(fn ($m) => $this->toDomain($m))
            ->all();
    }

    /** @return Taxon[] */
    public function buscarTodos(): array
    {
        return TaxonEloquentModel::all()
            ->map(fn ($m) => $this->toDomain($m))
            ->all();
    }

    private function toDomain(TaxonEloquentModel $model): Taxon
    {
        return Taxon::reconstituir(
            id: TaxonId::desde($model->id),
            nombreCientifico: $model->nombre_cientifico,
            rango: RangoTaxonomico::from($model->rango),
            autor: $model->autor,
            anioDescripcion: (int) $model->anio_descripcion,
            estado: EstadoTaxon::from($model->estado),
            padreId: $model->padre_id ? TaxonId::desde($model->padre_id) : null,
        );
    }
}
