<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Domain\Repositories;

use Modules\CatalogoPublico\Domain\Entities\ImagenPorDefectoDeTaxon;
use Modules\CatalogoPublico\Domain\ValueObjects\RangoTaxonomico;

interface ImagenPorDefectoRepositoryInterface
{
    public function obtener(RangoTaxonomico $nivel, string $valorTaxon): ?ImagenPorDefectoDeTaxon;

    public function guardar(ImagenPorDefectoDeTaxon $defecto): void;

    /**
     * Claves "nivel:valor" que ya tienen portada dentro de la línea taxonómica dada.
     *
     * @param  list<array{0: RangoTaxonomico, 1: string}>  $nivelesValor
     * @return list<string>
     */
    public function clavesConDefecto(array $nivelesValor): array;
}
