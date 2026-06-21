<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarComposicionGabinete;

/**
 * DTO de salida con las subfamilias y géneros distintos presentes en el gabinete.
 */
final readonly class ConsultarComposicionGabineteOutput
{
    /**
     * @param  string[]  $subfamilias  Subfamilias distintas presentes en el gabinete, orden alfabético.
     * @param  string[]  $generos  Géneros distintos presentes en el gabinete, orden alfabético.
     */
    public function __construct(
        public array $subfamilias,
        public array $generos,
    ) {}
}
