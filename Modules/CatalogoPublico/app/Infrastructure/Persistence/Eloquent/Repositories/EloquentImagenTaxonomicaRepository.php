<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Repositories;

use DateTimeImmutable;
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
                'sha256' => $imagen->archivo()->sha256,
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

    public function rutaEstaReferenciada(string $ruta): bool
    {
        return ImagenTaxonomicaEloquentModel::query()->where('ruta', $ruta)->exists();
    }

    /** @return list<ImagenTaxonomica> */
    public function listarPorSubarbol(RangoTaxonomico $nivel, string $valorTaxon, int $limite = 12, ?ImagenTaxonomicaId $preferida = null): array
    {
        $consulta = ImagenTaxonomicaEloquentModel::query()->whereExists(function ($query) use ($nivel, $valorTaxon): void {
            $query->selectRaw('1')->from('taxonomia.especimenes as te')
                ->join('divulgacion.especimenes_divulgables as ed', 'ed.especimen_id', '=', 'te.id')
                ->whereColumn('te.occurrence_id', 'divulgacion.imagenes_taxonomicas.occurrence_id')
                ->where('ed.publicado', true)->where('te.coordenadas_otras_regiones', false)->where('ed.scientific_name_visible', true)
                ->whereRaw('(SELECT COUNT(*) FROM taxonomia.especimenes identidad WHERE identidad.occurrence_id = te.occurrence_id) = 1');
            if ($nivel === RangoTaxonomico::Family) $query->where('ed.family_visible', true);
            if ($nivel === RangoTaxonomico::Genus) $query->where('ed.genus_visible', true);
            $query->whereRaw(<<<'SQL'
                te.taxon_id IN (
                    WITH RECURSIVE descendientes AS (
                        SELECT t.id FROM taxonomia.taxones t
                        LEFT JOIN taxonomia.taxones g ON g.id = t.padre_id AND g.rango = 'genero'
                        WHERE t.rango = ? AND (t.nombre_cientifico = ? OR
                            (t.rango = 'especie' AND g.nombre_cientifico || ' ' || t.nombre_cientifico = ?))
                        UNION
                        SELECT t.id FROM taxonomia.taxones t JOIN descendientes d ON t.padre_id = d.id
                    ) SELECT id FROM descendientes
                )
                SQL, [$nivel->rangoBD(), $valorTaxon, $valorTaxon]);
        });
        if ($preferida !== null) $consulta->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$preferida->toString()]);
        $models = $consulta->orderBy('nombre_original')->orderBy('id')->limit(max(1, min(12, $limite)))->get();

        return $models->map(fn (ImagenTaxonomicaEloquentModel $model) => $this->reconstituir($model))
            ->values()
            ->all();
    }

    private function reconstituir(ImagenTaxonomicaEloquentModel $model): ImagenTaxonomica
    {
        return ImagenTaxonomica::reconstituir(
            id: ImagenTaxonomicaId::fromString($model->id),
            occurrenceID: $model->occurrence_id,
            archivo: ArchivoImagen::crear($model->nombre_original, $model->ruta, $model->disco, $model->sha256),
            autor: AutorImagen::crear($model->autor_nombre, $model->autor_apellido),
            marcaAguaAplicada: (bool) $model->marca_agua_aplicada,
            subidaEn: new DateTimeImmutable((string) $model->created_at),
        );
    }
}
