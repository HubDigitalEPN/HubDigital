<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Tests\Support;

use Modules\CatalogoPublico\Domain\Entities\ImagenPorDefectoDeTaxon;
use Modules\CatalogoPublico\Domain\Repositories\ImagenPorDefectoRepositoryInterface;
use Modules\CatalogoPublico\Domain\ValueObjects\RangoTaxonomico;

/**
 * Repositorio volátil de portadas por defecto para Behat, indexado por la
 * clave "nivel:valor".
 */
final class InMemoryImagenPorDefectoRepository implements ImagenPorDefectoRepositoryInterface
{
    /** @var array<string, ImagenPorDefectoDeTaxon> */
    private array $defectos = [];

    public function obtener(RangoTaxonomico $nivel, string $valorTaxon): ?ImagenPorDefectoDeTaxon
    {
        return $this->defectos[$this->clave($nivel, $valorTaxon)] ?? null;
    }

    public function guardar(ImagenPorDefectoDeTaxon $defecto): void
    {
        $this->defectos[$this->clave($defecto->nivel(), $defecto->valorTaxon())] = $defecto;
    }

    /**
     * @param  list<array{0: RangoTaxonomico, 1: string}>  $nivelesValor
     * @return list<string>
     */
    public function clavesConDefecto(array $nivelesValor): array
    {
        $claves = [];

        foreach ($nivelesValor as [$nivel, $valor]) {
            $clave = $this->clave($nivel, $valor);

            if (array_key_exists($clave, $this->defectos)) {
                $claves[] = $clave;
            }
        }

        return $claves;
    }

    private function clave(RangoTaxonomico $nivel, string $valorTaxon): string
    {
        return $nivel->value.':'.$valorTaxon;
    }
}
