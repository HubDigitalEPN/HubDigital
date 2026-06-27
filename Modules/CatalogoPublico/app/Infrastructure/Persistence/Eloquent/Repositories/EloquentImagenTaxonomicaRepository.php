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

    /**
     * Normaliza el nombre de especie igual que el árbol: prefija el género si el
     * nombre científico no lo incluye, para que el valor case con el del portal.
     */
    private const SPECIES_EXPR = "(CASE WHEN tx_species.nombre_cientifico LIKE tx_genus.nombre_cientifico || ' %' THEN tx_species.nombre_cientifico ELSE tx_genus.nombre_cientifico || ' ' || tx_species.nombre_cientifico END)";

    /** @return list<ImagenTaxonomica> */
    public function listarPorSubarbol(RangoTaxonomico $nivel, string $valorTaxon): array
    {
        $query = ImagenTaxonomicaEloquentModel::query()
            ->join('taxonomia.especimenes as te', 'te.occurrence_id', '=', 'divulgacion.imagenes_taxonomicas.occurrence_id')
            ->join('taxonomia.taxones as tx_species', 'tx_species.id', '=', 'te.taxon_id')
            ->leftJoin('taxonomia.taxones as tx_genus', 'tx_genus.id', '=', 'tx_species.padre_id')
            ->leftJoin('taxonomia.taxones as tx_family', 'tx_family.id', '=', 'tx_genus.padre_id')
            ->leftJoin('taxonomia.taxones as tx_order', 'tx_order.id', '=', 'tx_family.padre_id')
            ->leftJoin('taxonomia.taxones as tx_class', 'tx_class.id', '=', 'tx_order.padre_id')
            ->leftJoin('taxonomia.taxones as tx_phylum', 'tx_phylum.id', '=', 'tx_class.padre_id');

        if ($nivel === RangoTaxonomico::Species) {
            $query->whereRaw(self::SPECIES_EXPR.' = ?', [$valorTaxon]);
        } else {
            $query->where($this->aliasDeNivel($nivel).'.nombre_cientifico', $valorTaxon);
        }

        $models = $query
            ->orderBy('divulgacion.imagenes_taxonomicas.created_at')
            ->select('divulgacion.imagenes_taxonomicas.*')
            ->get();

        return $models->map(fn (ImagenTaxonomicaEloquentModel $model) => $this->reconstituir($model))
            ->values()
            ->all();
    }

    private function aliasDeNivel(RangoTaxonomico $nivel): string
    {
        return match ($nivel) {
            RangoTaxonomico::Genus => 'tx_genus',
            RangoTaxonomico::Family => 'tx_family',
            RangoTaxonomico::Order => 'tx_order',
            RangoTaxonomico::Class_ => 'tx_class',
            RangoTaxonomico::Phylum => 'tx_phylum',
            RangoTaxonomico::Species => 'tx_species',
        };
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
