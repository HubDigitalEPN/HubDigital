<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects;

enum EstadoEspecimen: string
{
    case Disponible = 'disponible';
    case EnPrestamo = 'en_prestamo';
    case Observado = 'observado';
    case Extraviado = 'extraviado';

    public function equals(self $other): bool
    {
        return $this === $other;
    }
}
