<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\Ports;

/**
 * Puerto de la capa de aplicación para resolver el correo electrónico de un
 * investigador a partir de su identificador.
 *
 * Lo implementa un adaptador en Infrastructure que consulta el modelo de usuarios.
 */
interface InvestigadorEmailPort
{
    /** Retorna el correo electrónico del investigador indicado. */
    public function obtenerEmail(string $investigadorId): string;
}
