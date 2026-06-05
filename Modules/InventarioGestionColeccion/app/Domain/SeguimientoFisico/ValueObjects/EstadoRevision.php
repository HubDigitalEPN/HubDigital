<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects;

/**
 * Estado de revisión aplicable a entidades cuya validez se confirma
 * post-import (muestras de colecta, localidades enlazadas, especímenes).
 *
 * Transiciones soportadas:
 *  - pendiente → confirmada  (ConfirmarMuestra, ConfirmarLocalidad...)
 *  - cualquiera → pendiente  (MarcarParaRevision, con motivo)
 *  - pendiente → descartada  (DescartarMuestra — futuro)
 */
enum EstadoRevision: string
{
    case Pendiente = 'pendiente';
    case Confirmada = 'confirmada';
    case Descartada = 'descartada';

    public static function porDefecto(): self
    {
        return self::Pendiente;
    }

    public function puedeConfirmarse(): bool
    {
        return $this === self::Pendiente;
    }

    public function esConfirmada(): bool
    {
        return $this === self::Confirmada;
    }

    public function esDescartada(): bool
    {
        return $this === self::Descartada;
    }

    public function equals(self $other): bool
    {
        return $this === $other;
    }
}
