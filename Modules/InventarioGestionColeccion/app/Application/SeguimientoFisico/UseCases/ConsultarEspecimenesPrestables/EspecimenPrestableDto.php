<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarEspecimenesPrestables;

/**
 * Un espécimen que la colección declara prestable, en primitivos.
 *
 * Es lo que cruza hacia GestionPrestamosRecepciones: ni entidades ni value objects de
 * este módulo, para que el otro contexto no dependa de nuestro dominio.
 */
final readonly class EspecimenPrestableDto
{
    public function __construct(
        public string $especimenId,
        public string $codigoCatalogo,
        public ?int $individualCount,
        public string $estado,
        public ?string $nombreCientifico,
    ) {}
}
