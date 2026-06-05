<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Exceptions;

use DomainException;

final class CadenciaRecordatoriosInvalidaException extends DomainException
{
    public static function paraListaVacia(): self
    {
        return new self('La cadencia de recordatorios no puede estar vacía.');
    }

    /**
     * @param  list<int>  $dias
     */
    public static function paraDiasInvalidos(array $dias): self
    {
        $invalidos = implode(', ', array_filter($dias, fn (int $d) => $d < 1));

        return new self("Los siguientes días de cadencia son inválidos (deben ser ≥ 1): {$invalidos}.");
    }
}
