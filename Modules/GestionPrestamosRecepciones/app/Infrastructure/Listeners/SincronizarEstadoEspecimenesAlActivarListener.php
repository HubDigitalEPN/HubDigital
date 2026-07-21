<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Listeners;

use Modules\GestionPrestamosRecepciones\Application\Ports\EstadoEspecimenCatalogoPort;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarItemsPrestamo\ConsultarItemsPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarItemsPrestamo\ConsultarItemsPrestamoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarItemsPrestamo\ItemPrestamoVista;
use Modules\GestionPrestamosRecepciones\Domain\Events\PrestamoActivado;

/**
 * Saca del catálogo los especímenes de un préstamo que acaba de activarse.
 *
 * IMPORTANTE: síncrono a propósito — no implementa ShouldQueue. `PrestamoActivado` se
 * publica dentro de la transacción de AprobarVerificacionEntregaHandler y ambos esquemas
 * (`prestamos.*` y `taxonomia.*`) viven en la misma conexión, así que el cambio de estado
 * en el catálogo entra en esa misma transacción y revierte con ella. Encolar este listener
 * rompería esa atomicidad.
 */
final class SincronizarEstadoEspecimenesAlActivarListener
{
    public function __construct(
        private readonly ConsultarItemsPrestamoHandler $consultarItems,
        private readonly EstadoEspecimenCatalogoPort $catalogo,
    ) {}

    public function handle(PrestamoActivado $event): void
    {
        $items = $this->consultarItems->handle(
            new ConsultarItemsPrestamoInput(prestamoId: (string) $event->prestamoId)
        )->items;

        // Los ítems anteriores al enlace con el catálogo no tienen espécimen asociado.
        $especimenIds = array_values(array_filter(
            array_map(fn (ItemPrestamoVista $item): ?string => $item->especimenId, $items)
        ));

        $this->catalogo->marcarEnPrestamo($especimenIds);
    }
}
