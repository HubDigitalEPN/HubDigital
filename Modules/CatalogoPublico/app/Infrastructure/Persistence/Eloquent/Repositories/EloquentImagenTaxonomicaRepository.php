<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Repositories;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Modules\CatalogoPublico\Domain\Entities\ImagenTaxonomica;
use Modules\CatalogoPublico\Domain\Repositories\ImagenTaxonomicaRepositoryInterface;
use Modules\CatalogoPublico\Domain\ValueObjects\ArchivoImagen;
use Modules\CatalogoPublico\Domain\ValueObjects\AutorImagen;
use Modules\CatalogoPublico\Domain\ValueObjects\ImagenTaxonomicaId;
use Modules\CatalogoPublico\Domain\ValueObjects\RangoTaxonomico;
use Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Models\ImagenTaxonomicaEloquentModel;

final class EloquentImagenTaxonomicaRepository implements ImagenTaxonomicaRepositoryInterface
{
    public function nextIdentity(): ImagenTaxonomicaId
    {
        return ImagenTaxonomicaId::generate();
    }

    public function guardar(ImagenTaxonomica $imagen): void
    {
        ImagenTaxonomicaEloquentModel::updateOrCreate(
            ['id' => $imagen->id()->toString()],
            [
                'occurrence_id' => $imagen->occurrenceID(),
                'nombre_original' => $imagen->archivo()->nombreOriginal,
                'ruta' => $imagen->archivo()->ruta,
                'disco' => $imagen->archivo()->disco,
                'autor_nombre' => $imagen->autor()->nombre,
                'autor_apellido' => $imagen->autor()->apellido,
                'autor_nombre_completo' => $imagen->nombreAutor(),
                'marca_agua_aplicada' => $imagen->marcaAguaAplicada(),
            ]
        );
    }

    public function buscarPorId(ImagenTaxonomicaId $id): ?ImagenTaxonomica
    {
        $model = ImagenTaxonomicaEloquentModel::query()->find($id->toString());

        return $model === null ? null : $this->reconstituir($model);
    }

    /** @return list<ImagenTaxonomica> */
    public function listarPorSubarbol(RangoTaxonomico $nivel, string $valorTaxon): array
    {
        $ocurrenceIDs = $this->occurrenceIDsBajo($nivel, $valorTaxon);

        if ($ocurrenceIDs === []) {
            return [];
        }

        $models = ImagenTaxonomicaEloquentModel::query()
            ->whereIn('occurrence_id', $ocurrenceIDs)
            ->orderBy('created_at')
            ->get();

        return $models->map(fn (ImagenTaxonomicaEloquentModel $model) => $this->reconstituir($model))
            ->values()
            ->all();
    }

    /**
     * Occurrence IDs cuyo taxón asignado desciende (o es) del taxón indicado.
     *
     * Species: match por nombre científico compuesto (género + epíteto), igual
     * que el árbol del portal.
     * Cualquier otro rango canónico: se ubica el UUID del taxón por (rango, nombre)
     * y se resuelven descendientes con CTE recursivo, así se respetan rangos
     * intermedios (suborden, subfamilia, tribu).
     *
     * @return list<string>
     */
    private function occurrenceIDsBajo(RangoTaxonomico $nivel, string $valorTaxon): array
    {
        if ($nivel === RangoTaxonomico::Species) {
            $sql = <<<'SQL'
                SELECT te.occurrence_id
                FROM taxonomia.especimenes te
                JOIN taxonomia.taxones tx_species ON tx_species.id = te.taxon_id
                LEFT JOIN taxonomia.taxones tx_genus ON tx_genus.id = tx_species.padre_id
                WHERE tx_species.rango = 'especie'
                  AND tx_genus.rango = 'genero'
                  AND (CASE
                        WHEN tx_species.nombre_cientifico LIKE tx_genus.nombre_cientifico || ' %'
                        THEN tx_species.nombre_cientifico
                        ELSE tx_genus.nombre_cientifico || ' ' || tx_species.nombre_cientifico
                       END) = ?
            SQL;

            return array_column(DB::select($sql, [$valorTaxon]), 'occurrence_id');
        }

        $sql = <<<'SQL'
            WITH RECURSIVE descendientes AS (
                SELECT id FROM taxonomia.taxones
                WHERE rango = ? AND nombre_cientifico = ?
                UNION ALL
                SELECT t.id FROM taxonomia.taxones t
                JOIN descendientes d ON t.padre_id = d.id
            )
            SELECT te.occurrence_id
            FROM taxonomia.especimenes te
            WHERE te.taxon_id IN (SELECT id FROM descendientes)
        SQL;

        return array_column(DB::select($sql, [$nivel->rangoBD(), $valorTaxon]), 'occurrence_id');
    }

    private function reconstituir(ImagenTaxonomicaEloquentModel $model): ImagenTaxonomica
    {
        return ImagenTaxonomica::reconstituir(
            id: ImagenTaxonomicaId::fromString($model->id),
            occurrenceID: $model->occurrence_id,
            archivo: ArchivoImagen::crear($model->nombre_original, $model->ruta, $model->disco),
            autor: AutorImagen::crear($model->autor_nombre, $model->autor_apellido),
            marcaAguaAplicada: (bool) $model->marca_agua_aplicada,
            subidaEn: new DateTimeImmutable((string) $model->created_at),
        );
    }
}
