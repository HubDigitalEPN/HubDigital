<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarItemsPrestamo;

use Modules\GestionPrestamosRecepciones\Domain\Entities\ItemPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\ActaPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\PrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ActaPrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;

/**
 * Manejador del caso de uso para consultar los ítems (especímenes) de un préstamo,
 * recorriendo la cadena Préstamo → Acta → Solicitud → ítems vía repositorios.
 *
 * {@see ConsultarItemsPrestamoInput}
 * {@see ConsultarItemsPrestamoOutput}
 */
final class ConsultarItemsPrestamoHandler
{
    /**
     * @param PrestamoRepositoryInterface $prestamoRepo Repositorio de préstamos.
     * @param ActaPrestamoRepositoryInterface $actaRepo Repositorio de actas de préstamo.
     * @param SolicitudPrestamoRepositoryInterface $solicitudRepo Repositorio de solicitudes de préstamo.
     */
    public function __construct(
        private readonly PrestamoRepositoryInterface $prestamoRepo,
        private readonly ActaPrestamoRepositoryInterface $actaRepo,
        private readonly SolicitudPrestamoRepositoryInterface $solicitudRepo,
    ) {}

    /**
     * Ejecuta el caso de uso.
     *
     * @param ConsultarItemsPrestamoInput $input Datos de entrada.
     * @return ConsultarItemsPrestamoOutput Ítems del préstamo (lista vacía si no se resuelve la cadena).
     */
    public function handle(ConsultarItemsPrestamoInput $input): ConsultarItemsPrestamoOutput
    {
        $prestamo = $this->prestamoRepo->buscarPorId(PrestamoId::fromString($input->prestamoId));

        if ($prestamo === null) {
            return new ConsultarItemsPrestamoOutput(items: []);
        }

        $acta = $this->actaRepo->buscarPorId(
            ActaPrestamoId::fromString((string) $prestamo->actaPrestamoId())
        );

        if ($acta === null) {
            return new ConsultarItemsPrestamoOutput(items: []);
        }

        $solicitud = $this->solicitudRepo->buscarPorId($acta->solicitudPrestamoId());

        if ($solicitud === null) {
            return new ConsultarItemsPrestamoOutput(items: []);
        }

        $items = array_map(
            fn (ItemPrestamo $item) => new ItemPrestamoVista(
                itemPrestamoId: (string) $item->id(),
                codigoExterno: $item->especimenCodigoExterno(),
                cantidadSolicitada: $item->cantidadSolicitada(),
                nombre: $item->especimenSnapshot()['nombre_cientifico'] ?? null,
                condicionesEspecificas: $item->condicionesEspecificas(),
                especimenId: $item->especimenId(),
                individualesDisponibles: $item->especimenSnapshot()['individuales_disponibles'] ?? null,
            ),
            $solicitud->items(),
        );

        return new ConsultarItemsPrestamoOutput(items: array_values($items));
    }
}
