<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Support;

/** Numeración visible compartida entre el wizard y sus borradores. */
final class PasosWizardDeposito
{
    public const DESTINOS = [1 => 0, 2 => 1, 3 => 2, 4 => 3, 5 => 4, 6 => 7, 7 => 8];

    public static function numeroVisible(int $pasoPersistido): int
    {
        // Compatibilidad con los dos pasos retirados y con el estado final.
        if ($pasoPersistido === 5) return 4;
        if ($pasoPersistido === 6) return 6;
        if ($pasoPersistido === 9) return self::total();

        $numero = array_search($pasoPersistido, self::DESTINOS, true);

        return $numero === false ? 1 : $numero;
    }

    public static function total(): int
    {
        return count(self::DESTINOS);
    }
}
