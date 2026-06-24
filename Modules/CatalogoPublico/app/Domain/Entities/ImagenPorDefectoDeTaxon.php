<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Domain\Entities;

use Modules\CatalogoPublico\Domain\Exceptions\NivelNoSobrescribibleException;
use Modules\CatalogoPublico\Domain\ValueObjects\ImagenTaxonomicaId;
use Modules\CatalogoPublico\Domain\ValueObjects\RangoTaxonomico;

/**
 * Portada (imagen por defecto) de un nodo taxonómico. Solo existe para los
 * niveles que admiten imagen: género y especie.
 */
final class ImagenPorDefectoDeTaxon
{
    private function __construct(
        private readonly RangoTaxonomico $nivel,
        private readonly string $valorTaxon,
        private ImagenTaxonomicaId $imagenId,
    ) {}

    public static function asignar(
        RangoTaxonomico $nivel,
        string $valorTaxon,
        ImagenTaxonomicaId $imagenId,
    ): self {
        if (! $nivel->admiteImagen()) {
            throw new NivelNoSobrescribibleException(
                "El nivel '{$nivel->value}' no admite imagen por defecto; solo género y especie la admiten"
            );
        }

        if (trim($valorTaxon) === '') {
            throw new \InvalidArgumentException('El valor del taxón no puede ser vacío');
        }

        return new self($nivel, $valorTaxon, $imagenId);
    }

    public function sobrescribirCon(ImagenTaxonomicaId $imagenId): void
    {
        $this->imagenId = $imagenId;
    }

    public function nivel(): RangoTaxonomico
    {
        return $this->nivel;
    }

    public function valorTaxon(): string
    {
        return $this->valorTaxon;
    }

    public function imagenId(): ImagenTaxonomicaId
    {
        return $this->imagenId;
    }
}
