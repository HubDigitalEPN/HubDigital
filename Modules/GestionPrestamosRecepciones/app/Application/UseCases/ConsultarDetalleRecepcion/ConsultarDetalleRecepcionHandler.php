<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDetalleRecepcion;

use Modules\GestionPrestamosRecepciones\Domain\Repositories\RecepcionLoteRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudDepositoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ItemVerificacionRecepcion;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;

/**
 * Query de lectura del detalle de recepción física de un lote para la pantalla del
 * curador. Devuelve los datos de la solicitud aprobada junto con el estado actual de
 * su recepción (si ya fue iniciada). Retorna null si la solicitud no existe.
 */
final class ConsultarDetalleRecepcionHandler
{
    public function __construct(
        private readonly SolicitudDepositoRepositoryInterface $solicitudRepo,
        private readonly RecepcionLoteRepositoryInterface $recepcionRepo,
    ) {}

    public function handle(ConsultarDetalleRecepcionInput $input): ?ConsultarDetalleRecepcionOutput
    {
        $solicitud = $this->solicitudRepo->buscarPorId(SolicitudDepositoId::from($input->solicitudId));

        if ($solicitud === null) {
            return null;
        }

        $lote = $this->recepcionRepo->buscarPorSolicitudId($solicitud->id());

        $items = $lote !== null
            ? array_map(
                fn (ItemVerificacionRecepcion $item): array => ['item' => $item->item, 'resultado' => $item->resultado],
                $lote->itemsVerificacion(),
            )
            : [];

        return new ConsultarDetalleRecepcionOutput(
            numeroSolicitud: (string) $solicitud->numero(),
            tipoTramite: $solicitud->tipoTramite(),
            investigadorId: $solicitud->investigadorId(),
            codigoQR: $solicitud->codigoQR() !== null ? (string) $solicitud->codigoQR() : null,
            nroLotes: $solicitud->nroLotes(),
            nroIndividuos: $solicitud->nroIndividuos(),
            nroMorfoespecies: $solicitud->nroMorfoespecies(),
            grupoAnimal: $solicitud->grupoAnimal(),
            localidad: $solicitud->localidad(),
            aprobadaEn: $solicitud->aprobadaEn(),
            recepcionIniciada: $lote !== null,
            estadoRecepcion: $lote?->estado()->value,
            estadoColeccion: $lote?->estadoColeccion()?->value,
            itemsVerificacion: array_values($items),
            observaciones: $lote !== null ? array_values($lote->observaciones()) : [],
            motivoFallo: $lote?->motivoFallo()?->value,
            accionCorrectiva: $lote?->accionCorrectiva()?->value,
            actaEmitida: $lote?->actaEmitida() ?? false,
        );
    }
}
