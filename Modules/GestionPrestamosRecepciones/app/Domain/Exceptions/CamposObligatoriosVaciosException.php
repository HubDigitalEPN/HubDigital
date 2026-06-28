<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Exceptions;

/**
 * Excepción de dominio lanzada cuando uno o más registros de la matriz tienen
 * campos DwC obligatorios (críticos) con valor vacío. A diferencia de
 * {@see CamposDwCFaltantesException} (que valida la ausencia de la columna),
 * esta valida que las celdas de columnas críticas presentes no estén vacías.
 * Construir con {@see porRegistros()}.
 */
final class CamposObligatoriosVaciosException extends \DomainException
{
    /**
     * Violaciones detectadas, cada una indicando la fila (nombre científico o
     * índice) y el campo DwC vacío.
     *
     * @var list<array{fila: string, campo: string}>
     */
    private array $violaciones = [];

    /**
     * @param  list<array{fila: string, campo: string}>  $violaciones
     */
    public static function porRegistros(array $violaciones): self
    {
        $detalle = implode('; ', array_map(
            fn (array $v): string => sprintf('%s → %s', $v['fila'], $v['campo']),
            $violaciones,
        ));

        $instancia = new self(
            sprintf(
                'Hay campos obligatorios vacíos que deben completarse antes de cargar la matriz: %s',
                $detalle,
            )
        );

        $instancia->violaciones = $violaciones;

        return $instancia;
    }

    /**
     * @return list<array{fila: string, campo: string}>
     */
    public function violaciones(): array
    {
        return $this->violaciones;
    }
}
