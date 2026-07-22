<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\Ports;

/**
 * Datos de un espécimen del catálogo de inventario, en el lenguaje de este módulo.
 *
 * Contrato del {@see CatalogoEspecimenesPort}: solo los campos que el proceso de
 * préstamos necesita para que el investigador identifique un espécimen y sepa
 * cuántos individuos puede solicitar.
 */
final readonly class EspecimenCatalogoDto
{
    /**
     * @param  string  $especimenId  Identificador estable del espécimen en el inventario; es la referencia que se persiste.
     * @param  string  $codigoCatalogo  Código legible por humanos que el investigador reconoce.
     * @param  string|null  $nombreCientifico  Null si el espécimen aún no tiene determinación taxonómica.
     * @param  int|null  $individualesDisponibles  Tope de la cantidad solicitable. Null cuando el
     *                                             inventario nunca registró el conteo: no significa
     *                                             cero, significa que no hay tope conocido.
     */
    public function __construct(
        public string $especimenId,
        public string $codigoCatalogo,
        public ?string $nombreCientifico,
        public ?int $individualesDisponibles,
        public string $estado,
    ) {}

    /**
     * Snapshot a congelar en el ítem de la solicitud, para que el acta pueda
     * mostrar los datos tal como estaban al solicitarse sin volver a consultar
     * el catálogo.
     *
     * @return array<string, mixed>
     */
    public function aSnapshot(): array
    {
        return [
            'codigo_catalogo' => $this->codigoCatalogo,
            'nombre_cientifico' => $this->nombreCientifico,
            'individuales_disponibles' => $this->individualesDisponibles,
            'capturado_en' => (new \DateTimeImmutable)->format(DATE_ATOM),
        ];
    }
}
