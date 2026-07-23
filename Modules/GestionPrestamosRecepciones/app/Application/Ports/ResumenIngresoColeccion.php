<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\Ports;

/**
 * Lo que hay realmente en la colección para un lote depositado.
 *
 * Se consulta en vivo en lugar de guardarse: así la pantalla de recepción refleja el
 * estado real aunque el ingreso se reintente o alguien retire material a mano.
 */
final readonly class ResumenIngresoColeccion
{
    public function __construct(
        public int $especimenesEnColeccion,
        public int $pendientesRevision,
        public int $registrosEnMatriz,
    ) {}

    /** El lote entró completo si hay tantos especímenes como filas tenía la matriz. */
    public function ingresoCompleto(): bool
    {
        return $this->registrosEnMatriz > 0
            && $this->especimenesEnColeccion === $this->registrosEnMatriz;
    }
}
