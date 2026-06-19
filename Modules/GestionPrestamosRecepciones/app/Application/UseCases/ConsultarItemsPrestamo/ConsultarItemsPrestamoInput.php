<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarItemsPrestamo;

/**
 * Input DTO para consultar los ítems (especímenes) de un préstamo.
 */
final readonly class ConsultarItemsPrestamoInput
{
    /**
     * @param string $prestamoId Identificador del préstamo.
     */
    public function __construct(
        public string $prestamoId,
    ) {}
}
