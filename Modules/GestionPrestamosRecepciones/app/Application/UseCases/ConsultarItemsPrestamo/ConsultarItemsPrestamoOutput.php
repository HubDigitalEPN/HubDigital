<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarItemsPrestamo;

/**
 * Output DTO con los ítems (especímenes) de un préstamo.
 */
final readonly class ConsultarItemsPrestamoOutput
{
    /**
     * @param list<ItemPrestamoVista> $items Ítems del préstamo.
     */
    public function __construct(
        public array $items,
    ) {}

    /** Cantidad de ítems (especímenes) del préstamo. */
    public function total(): int
    {
        return count($this->items);
    }
}
