<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Repositories;

use Illuminate\Support\Str;
use Modules\CatalogoPublico\Domain\Entities\ImagenPorDefectoDeTaxon;
use Modules\CatalogoPublico\Domain\Repositories\ImagenPorDefectoRepositoryInterface;
use Modules\CatalogoPublico\Domain\ValueObjects\ImagenTaxonomicaId;
use Modules\CatalogoPublico\Domain\ValueObjects\RangoTaxonomico;
use Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Models\ImagenPorDefectoEloquentModel;

final class EloquentImagenPorDefectoRepository implements ImagenPorDefectoRepositoryInterface
{
    public function obtener(RangoTaxonomico $nivel, string $valorTaxon): ?ImagenPorDefectoDeTaxon
    {
        $model = ImagenPorDefectoEloquentModel::query()
            ->where('nivel', $nivel->value)
            ->where('valor_taxon', $valorTaxon)
            ->first();

        if ($model === null) {
            return null;
        }

        return ImagenPorDefectoDeTaxon::asignar(
            RangoTaxonomico::desde($model->nivel),
            $model->valor_taxon,
            ImagenTaxonomicaId::fromString($model->imagen_id),
        );
    }

    public function guardar(ImagenPorDefectoDeTaxon $defecto): void
    {
        ImagenPorDefectoEloquentModel::updateOrCreate(
            [
                'nivel' => $defecto->nivel()->value,
                'valor_taxon' => $defecto->valorTaxon(),
            ],
            [
                'id' => (string) Str::uuid(),
                'imagen_id' => $defecto->imagenId()->toString(),
            ]
        );
    }

    /**
     * @param  list<array{0: RangoTaxonomico, 1: string}>  $nivelesValor
     * @return list<string>
     */
    public function clavesConDefecto(array $nivelesValor): array
    {
        if ($nivelesValor === []) {
            return [];
        }

        $query = ImagenPorDefectoEloquentModel::query();

        foreach ($nivelesValor as [$nivel, $valor]) {
            $query->orWhere(function ($q) use ($nivel, $valor): void {
                $q->where('nivel', $nivel->value)->where('valor_taxon', $valor);
            });
        }

        return $query->get()
            ->map(fn (ImagenPorDefectoEloquentModel $model): string => $model->nivel.':'.$model->valor_taxon)
            ->values()
            ->all();
    }
}
