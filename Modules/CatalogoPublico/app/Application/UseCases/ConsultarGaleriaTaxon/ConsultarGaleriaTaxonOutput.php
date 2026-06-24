<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\ConsultarGaleriaTaxon;

final readonly class ConsultarGaleriaTaxonOutput
{
    /**
     * @param  list<array{imagenId: string, nombreArchivo: string, url: string, esPortada: bool}>  $imagenes
     *                                                                                                        ordenadas con la portada por defecto primero
     */
    private function __construct(
        public array $imagenes,
    ) {}

    /**
     * @param  list<array{imagenId: string, nombreArchivo: string, url: string, esPortada: bool}>  $imagenes
     */
    public static function crear(array $imagenes): self
    {
        return new self($imagenes);
    }
}
