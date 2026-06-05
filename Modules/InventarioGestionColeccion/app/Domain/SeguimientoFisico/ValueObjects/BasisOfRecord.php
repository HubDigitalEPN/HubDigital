<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects;

enum BasisOfRecord: string
{
    case PreservedSpecimen = 'PreservedSpecimen';
    case FossilSpecimen = 'FossilSpecimen';
    case LivingSpecimen = 'LivingSpecimen';
    case MaterialSample = 'MaterialSample';
    case HumanObservation = 'HumanObservation';
    case MachineObservation = 'MachineObservation';
    case Occurrence = 'Occurrence';

    public static function porDefecto(): self
    {
        return self::PreservedSpecimen;
    }

    public function equals(self $other): bool
    {
        return $this === $other;
    }
}
