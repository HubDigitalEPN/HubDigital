<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Domain\Services;

use Modules\CatalogoPublico\Domain\Entities\ImagenPorDefectoDeTaxon;
use Modules\CatalogoPublico\Domain\ValueObjects\ImagenTaxonomicaId;
use Modules\CatalogoPublico\Domain\ValueObjects\JerarquiaTaxonomica;
use Modules\CatalogoPublico\Domain\ValueObjects\RangoTaxonomico;

/**
 * Decide qué portadas por defecto deben crearse cuando se sube una imagen a un
 * espécimen. La portada solo bubbleea a especie y género, y únicamente a los
 * niveles que aún no tienen una (la primera imagen del subárbol gana).
 */
final class PropagadorImagenPorDefecto
{
    /**
     * @param  list<string>  $nivelesConDefecto  claves "nivel:valor" que ya tienen portada
     * @return list<ImagenPorDefectoDeTaxon>
     */
    public function nuevosDefectos(
        JerarquiaTaxonomica $jerarquia,
        ImagenTaxonomicaId $imagenId,
        array $nivelesConDefecto,
    ): array {
        $candidatos = [
            [RangoTaxonomico::Species, $jerarquia->nombreEspecie()],
            [RangoTaxonomico::Genus, $jerarquia->padreDeEspecie()],
        ];

        $nuevos = [];

        foreach ($candidatos as [$nivel, $valor]) {
            $clave = $nivel->value.':'.$valor;

            if (in_array($clave, $nivelesConDefecto, true)) {
                continue;
            }

            $nuevos[] = ImagenPorDefectoDeTaxon::asignar($nivel, $valor, $imagenId);
        }

        return $nuevos;
    }
}
