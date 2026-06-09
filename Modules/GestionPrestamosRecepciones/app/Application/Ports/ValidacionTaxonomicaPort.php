<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\Ports;

interface ValidacionTaxonomicaPort
{
    /**
     * Valida una lista de nombres científicos contra el catálogo de referencia.
     *
     * @param  string[]  $nombresCientificos  Lista de nombres a validar
     * @return array<int, array{nombreCientifico: string, estado: string, sugerencia: ?string}>
     *                                                                                          estado: 'catalogado' | 'inconsistencia_tipografica' | 'no_catalogado' | 'no_verificado'
     */
    public function validarEspecies(array $nombresCientificos): array;
}
