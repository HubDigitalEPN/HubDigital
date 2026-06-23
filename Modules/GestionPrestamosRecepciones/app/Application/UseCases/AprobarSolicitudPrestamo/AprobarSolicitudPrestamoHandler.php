<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarSolicitudPrestamo;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Entities\ActaPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\SolicitudPrestamoNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\ActaPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudPrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoPrestamo;

/**
 * Caso de uso: Aprueba una solicitud de préstamo y emite el acta correspondiente.
 *
 * El curador valida la solicitud, establece las condiciones finales (generales y por ítem)
 * y el sistema genera automáticamente un acta de préstamo en estado pendiente de firma.
 */
final class AprobarSolicitudPrestamoHandler
{
    public function __construct(
        private readonly SolicitudPrestamoRepositoryInterface $solicitudRepo,
        private readonly ActaPrestamoRepositoryInterface $actaRepo,
        private readonly EventPublisherPort $publisher,
        private readonly TransactionManagerPort $transactionManager,
    ) {}

    /**
     * Ejecuta el caso de uso.
     *
     * @param  AprobarSolicitudPrestamoInput  $input
     * @return AprobarSolicitudPrestamoOutput
     *
     * @throws SolicitudPrestamoNoEncontradaException Si la solicitud no existe.
     */
    public function handle(AprobarSolicitudPrestamoInput $input): AprobarSolicitudPrestamoOutput
    {
        $tipoPrestamo = TipoPrestamo::from($input->tipoPrestamo);

        $solicitudId = SolicitudPrestamoId::fromString($input->solicitudId);
        $solicitud = $this->solicitudRepo->buscarPorId($solicitudId);

        if ($solicitud === null) {
            throw SolicitudPrestamoNoEncontradaException::paraSolicitud($solicitudId);
        }

        foreach ($solicitud->items() as $item) {
            $itemIdStr = (string) $item->id();
            if (isset($input->condicionesPorItem[$itemIdStr])) {
                $item->establecerCondiciones($input->condicionesPorItem[$itemIdStr]);
            }
        }

        $solicitud->aprobar(curadorId: $input->curadorId);

        $ahora = new DateTimeImmutable;
        $fechaFin = $ahora->modify("+{$input->duracionMeses} months");
        $pdfRuta = 'actas/'.(string) $solicitudId.'.pdf';

        $acta = ActaPrestamo::emitir(
            id: $this->actaRepo->nextIdentity(),
            codigoPrestamo: $solicitud->codigoPrestamo(),
            solicitudPrestamoId: $solicitudId,
            tipoPrestamo: $tipoPrestamo,
            alcancePrestamo: $solicitud->alcancePrestamo(),
            fechaInicio: $ahora,
            fechaFin: $fechaFin,
            pdfRuta: $pdfRuta,
            condicionesGenerales: $input->condicionesGenerales,
        );

        $acta->marcarEnviada($solicitud->investigadorId());

        $this->transactionManager->executeTransactional(function () use ($solicitud, $acta): void {
            $this->solicitudRepo->guardar($solicitud);
            $this->actaRepo->guardar($acta);
            foreach ($solicitud->pullEvents() as $event) {
                $this->publisher->publish($event);
            }
            foreach ($acta->pullEvents() as $event) {
                $this->publisher->publish($event);
            }
        });

        return AprobarSolicitudPrestamoOutput::from($solicitud, $acta);
    }
}
