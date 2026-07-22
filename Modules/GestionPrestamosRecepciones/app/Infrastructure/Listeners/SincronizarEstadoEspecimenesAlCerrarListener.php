<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Listeners;

use Modules\GestionPrestamosRecepciones\Application\Ports\EstadoEspecimenCatalogoPort;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarItemsPrestamo\ConsultarItemsPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarItemsPrestamo\ConsultarItemsPrestamoInput;
use Modules\GestionPrestamosRecepciones\Domain\Events\PrestamoCerrado;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\VerificacionEspecimenesRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ObservacionEspecimen;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoVerificacion;

/**
 * Devuelve al catálogo los especímenes de un préstamo que se cierra.
 *
 * La restauración es por espécimen, no por lote: el `condicionEspecimen` del evento es
 * el resumen del préstamo, mientras que el dato fino son las observaciones por ítem que
 * CerrarPrestamoHandler acaba de persistir en la verificación de devolución. Un espécimen
 * con novedad queda observado; el resto vuelve a disponible.
 *
 * IMPORTANTE: síncrono a propósito — no implementa ShouldQueue. Depende de leer la
 * verificación que se guardó unas líneas antes, dentro de la misma transacción, y de que
 * el cambio en `taxonomia.*` revierta junto con `prestamos.*` si algo falla.
 */
final class SincronizarEstadoEspecimenesAlCerrarListener
{
    public function __construct(
        private readonly ConsultarItemsPrestamoHandler $consultarItems,
        private readonly VerificacionEspecimenesRepositoryInterface $verificacionRepo,
        private readonly EstadoEspecimenCatalogoPort $catalogo,
    ) {}

    public function handle(PrestamoCerrado $event): void
    {
        $items = $this->consultarItems->handle(
            new ConsultarItemsPrestamoInput(prestamoId: (string) $event->prestamoId)
        )->items;

        $verificacion = $this->verificacionRepo->buscarPorPrestamoYTipo(
            $event->prestamoId,
            TipoVerificacion::Devolucion,
        );

        $itemsConNovedad = array_flip(array_map(
            fn (ObservacionEspecimen $obs): string => $obs->itemPrestamoId,
            $verificacion?->observaciones() ?? [],
        ));

        $especimenIds = [];
        $conNovedad = [];

        foreach ($items as $item) {
            // Los ítems anteriores al enlace con el catálogo no tienen espécimen asociado.
            if ($item->especimenId === null) {
                continue;
            }

            $especimenIds[] = $item->especimenId;

            if (isset($itemsConNovedad[$item->itemPrestamoId])) {
                $conNovedad[] = $item->especimenId;
            }
        }

        $this->catalogo->restaurar($especimenIds, $conNovedad);
    }
}
