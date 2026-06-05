<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories;

use Illuminate\Support\Facades\DB;
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
                'epiteto_infraespecifico' => $taxon->epitetoInfraespecifico(),
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

    /**
     * @param  string[]  $ids
     * @return Taxon[]
     */
    public function buscarPorIds(array $ids): array
    {
        return TaxonEloquentModel::whereIn('id', $ids)
            ->get()
            ->map(fn ($m) => $this->toDomain($m))
            ->all();
    }

    /** @return string[] */
    public function listarDescendientesIds(string $taxonId): array
    {
        // CTE recursivo: incluye el taxón raíz + todos sus descendientes.
        $sql = 'WITH RECURSIVE descendientes(id) AS (
                    SELECT id FROM taxonomia.taxones WHERE id = ?
                    UNION ALL
                    SELECT t.id FROM taxonomia.taxones t
                    INNER JOIN descendientes d ON t.padre_id = d.id
                )
                SELECT id FROM descendientes';

        $rows = DB::select($sql, [$taxonId]);

        return array_map(fn ($r) => (string) $r->id, $rows);
    }

    private function toDomain(TaxonEloquentModel $model): Taxon
    {
        return Taxon::reconstituir(
            id: TaxonId::desde($model->id),
            nombreCientifico: $model->nombre_cientifico,
            rango: RangoTaxonomico::from($model->rango),
            autor: $model->autor,
            anioDescripcion: $model->anio_descripcion !== null ? (int) $model->anio_descripcion : null,
            estado: EstadoTaxon::from($model->estado),
            padreId: $model->padre_id ? TaxonId::desde($model->padre_id) : null,
            epitetoInfraespecifico: $model->epiteto_infraespecifico,
        );
    }
}
