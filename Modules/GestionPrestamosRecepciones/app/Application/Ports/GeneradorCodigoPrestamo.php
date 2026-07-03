<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\Ports;

use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\CodigoPrestamo;

/**
 * Puerto de la capa de aplicación para obtener el siguiente código de negocio
 * correlativo de un préstamo.
 *
 * El dominio no puede generar un número incremental (requiere estado persistido),
 * por eso la generación se expresa como esta abstracción. La implementa un
 * adaptador en Infrastructure que reserva el siguiente número de forma atómica.
 */
interface GeneradorCodigoPrestamo
{
    /**
     * Reserva y retorna el siguiente código correlativo global, usando el año actual.
     *
     * Debe ser seguro ante concurrencia: dos llamadas simultáneas nunca devuelven
     * el mismo número.
     */
    public function siguiente(): CodigoPrestamo;
}
