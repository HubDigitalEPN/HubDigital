<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Tests\Support;

use Modules\CatalogoPublico\Domain\Entities\ImagenTaxonomica;
use Modules\CatalogoPublico\Domain\Repositories\ImagenTaxonomicaRepositoryInterface;
use Modules\CatalogoPublico\Domain\ValueObjects\ImagenTaxonomicaId;
use Modules\CatalogoPublico\Domain\ValueObjects\RangoTaxonomico;

/**
 * Repositorio volátil de imágenes para Behat. Para resolver el subárbol de un
 * taxón se apoya en el proveedor de jerarquías sembrado en los antecedentes
 * (espejo del JOIN entre divulgacion.imagenes_taxonomicas y especimenes).
 */
final class InMemoryImagenTaxonomicaRepository implements ImagenTaxonomicaRepositoryInterface
{
    /** @var array<string, ImagenTaxonomica> indexado por id */
    private array $imagenes = [];

    public function __construct(
        private readonly FakeProveedorJerarquiaDeEspecimen $jerarquias,
    ) {}

    public function nextIdentity(): ImagenTaxonomicaId
    {
        return ImagenTaxonomicaId::generate();
    }

    public function guardar(ImagenTaxonomica $imagen): void
    {
        $this->imagenes[$imagen->id()->toString()] = $this->copiar($imagen);
    }

    public function buscarPorId(ImagenTaxonomicaId $id): ?ImagenTaxonomica
    {
        $almacenada = $this->imagenes[$id->toString()] ?? null;

        return $almacenada === null ? null : $this->copiar($almacenada);
    }

    /** @return list<ImagenTaxonomica> */
    public function listarPorSubarbol(RangoTaxonomico $nivel, string $valorTaxon): array
    {
        $result = [];

        foreach ($this->imagenes as $imagen) {
            if ($this->valorDeImagenEnNivel($imagen, $nivel) === $valorTaxon) {
                $result[] = $this->copiar($imagen);
            }
        }

        return $result;
    }

    public function buscarPorNombreArchivo(string $nombreArchivo): ?ImagenTaxonomica
    {
        foreach ($this->imagenes as $imagen) {
            if ($imagen->archivo()->nombreOriginal === $nombreArchivo) {
                return $this->copiar($imagen);
            }
        }

        return null;
    }

    private function valorDeImagenEnNivel(ImagenTaxonomica $imagen, RangoTaxonomico $nivel): ?string
    {
        $jerarquia = $this->jerarquias->obtener($imagen->occurrenceID());

        if ($jerarquia === null) {
            return null;
        }

        return $nivel === RangoTaxonomico::Species
            ? $jerarquia->nombreEspecie()
            : $jerarquia->valorEnRango($nivel);
    }

    private function copiar(ImagenTaxonomica $imagen): ImagenTaxonomica
    {
        return ImagenTaxonomica::reconstituir(
            id: $imagen->id(),
            occurrenceID: $imagen->occurrenceID(),
            archivo: $imagen->archivo(),
            autor: $imagen->autor(),
            marcaAguaAplicada: $imagen->marcaAguaAplicada(),
            subidaEn: $imagen->subidaEn(),
        );
    }
}
