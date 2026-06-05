<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects;

enum PrioridadColumna: string
{
    case Critica = 'critica';
    case Recomendada = 'recomendada';
    case Opcional = 'opcional';

    public static function desdeValorFlexible(string $valor): ?self
    {
        return self::tryFrom(mb_strtolower(trim($valor)));
    }

    /** @return string[] */
    public static function valoresAceptados(): array
    {
        return array_column(self::cases(), 'value');
    }
}
