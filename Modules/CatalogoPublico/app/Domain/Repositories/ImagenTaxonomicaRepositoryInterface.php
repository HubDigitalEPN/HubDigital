<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Domain\Repositories;

use Modules\CatalogoPublico\Domain\Entities\ImagenTaxonomica;
use Modules\CatalogoPublico\Domain\ValueObjects\ImagenTaxonomicaId;
use Modules\CatalogoPublico\Domain\ValueObjects\RangoTaxonomico;

interface ImagenTaxonomicaRepositoryInterface
{
    public function nextIdentity(): ImagenTaxonomicaId;

    public function guardar(ImagenTaxonomica $imagen): void;

    public function buscarPorId(ImagenTaxonomicaId $id): ?ImagenTaxonomica;

    /**
     * Imágenes existentes en el subárbol de un taxón (para la galería).
     *
     * @return list<ImagenTaxonomica>
     */
    public function listarPorSubarbol(RangoTaxonomico $nivel, string $valorTaxon): array;
}
