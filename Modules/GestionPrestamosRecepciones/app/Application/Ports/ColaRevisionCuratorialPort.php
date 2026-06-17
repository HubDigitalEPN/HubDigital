<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\Ports;

/**
 * Puerto de lectura (read-model) de la cola de revisión curatorial. Calcula la
 * posición de una solicitud en la cola tras una clasificación de prioridad.
 *
 * Lo implementa un adaptador en Infrastructure que consulta la persistencia ordenada
 * por prioridad. El dominio solo garantiza la clasificación; la posición es lectura.
 */
interface ColaRevisionCuratorialPort
{
    /** Retorna la posición (1-indexada) de la solicitud en la cola de revisión. */
    public function posicionEnLaCola(string $solicitudId): int;
}
