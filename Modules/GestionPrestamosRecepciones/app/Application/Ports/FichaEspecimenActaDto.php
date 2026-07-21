<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\Ports;

/**
 * Ficha descriptiva de un espécimen para la hoja de especímenes del acta.
 *
 * Contrato de {@see CatalogoEspecimenesPort::obtenerFichasParaActa()}. Separado de
 * {@see EspecimenCatalogoDto} a propósito: aquel describe un espécimen *prestable*
 * (con tope de individuos y estado) y lo consume la revalidación de seguridad de la
 * solicitud; este solo identifica al espécimen ya prestado en el documento.
 */
final readonly class FichaEspecimenActaDto
{
    /**
     * @param  string|null  $familia  Familia taxonómica, resuelta subiendo por la jerarquía. Null si el espécimen no tiene determinación o su rama no llega a familia.
     * @param  string|null  $sexo  Sexo del espécimen (el `sex` Darwin Core), ya traducido al español.
     * @param  string|null  $especie  Nombre científico cuando la determinación llega a género o especie; null en rangos superiores.
     */
    public function __construct(
        public string $especimenId,
        public string $codigoCatalogo,
        public ?string $familia,
        public ?string $sexo,
        public ?string $especie,
        public ?string $provincia,
        public ?string $localidad,
    ) {}
}
