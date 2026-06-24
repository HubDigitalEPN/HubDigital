<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\ConsultarGaleriaTaxon;

use Modules\CatalogoPublico\Application\Ports\AlmacenamientoImagenesPort;
use Modules\CatalogoPublico\Domain\Entities\ImagenTaxonomica;
use Modules\CatalogoPublico\Domain\Repositories\ImagenPorDefectoRepositoryInterface;
use Modules\CatalogoPublico\Domain\Repositories\ImagenTaxonomicaRepositoryInterface;
use Modules\CatalogoPublico\Domain\ValueObjects\RangoTaxonomico;

final class ConsultarGaleriaTaxonHandler
{
    public function __construct(
        private readonly ImagenTaxonomicaRepositoryInterface $repoImagenes,
        private readonly ImagenPorDefectoRepositoryInterface $repoDefectos,
        private readonly AlmacenamientoImagenesPort $almacenamiento,
    ) {}

    public function handle(ConsultarGaleriaTaxonInput $input): ConsultarGaleriaTaxonOutput
    {
        $nivel = RangoTaxonomico::desde($input->nivel);

        $imagenes = $this->repoImagenes->listarPorSubarbol($nivel, $input->valorTaxon);
        $defecto = $this->repoDefectos->obtener($nivel, $input->valorTaxon);

        // R8: la portada por defecto se devuelve primero.
        $portada = [];
        $resto = [];

        foreach ($imagenes as $imagen) {
            if ($defecto !== null && $imagen->id()->equals($defecto->imagenId())) {
                $portada[] = $imagen;
            } else {
                $resto[] = $imagen;
            }
        }

        $ordenadas = array_merge($portada, $resto);

        $dto = array_map(fn (ImagenTaxonomica $imagen): array => [
            'imagenId' => $imagen->id()->toString(),
            'nombreArchivo' => $imagen->archivo()->nombreOriginal,
            'url' => $this->almacenamiento->url($imagen->archivo()),
            'esPortada' => $defecto !== null && $imagen->id()->equals($defecto->imagenId()),
        ], $ordenadas);

        return ConsultarGaleriaTaxonOutput::crear(array_values($dto));
    }
}
