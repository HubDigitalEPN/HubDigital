<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\ValueObjects;

/**
 * Resultado de normalizar un registro DwC.
 *
 * Contiene el registro con valores canónicos y la lista de campos
 * que fueron modificados, para trazabilidad del curador.
 */
final readonly class ResultadoNormalizacion
{
    /**
     * @param  array<string, mixed>  $registro  Registro con valores normalizados
     * @param  list<array{campo: string, original: mixed, normalizado: mixed}>  $cambios  Campos que fueron modificados
     */
    public function __construct(
        public array $registro,
        public array $cambios,
    ) {}

    /** Indica si la normalización modificó al menos un campo del registro. */
    public function tuvoCambios(): bool
    {
        return $this->cambios !== [];
    }
}
