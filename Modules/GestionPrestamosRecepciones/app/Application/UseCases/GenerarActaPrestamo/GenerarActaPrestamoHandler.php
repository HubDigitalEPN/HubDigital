<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\GenerarActaPrestamo;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Entities\ActaPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\SolicitudPrestamoNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\ActaPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\NumeroPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudPrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoPrestamo;

final class GenerarActaPrestamoHandler
{
    public function __construct(
        private readonly SolicitudPrestamoRepositoryInterface $solicitudRepo,
        private readonly ActaPrestamoRepositoryInterface $actaRepo,
        private readonly EventPublisherPort $publisher,
        private readonly TransactionManagerPort $transactionManager,
    ) {}

    public function handle(GenerarActaPrestamoInput $input): GenerarActaPrestamoOutput
    {
        $solicitudId = SolicitudPrestamoId::fromString($input->solicitudId);
        $solicitud = $this->solicitudRepo->buscarPorId($solicitudId);

        if ($solicitud === null) {
            throw SolicitudPrestamoNoEncontradaException::paraSolicitud($solicitudId);
        }

        $pdfRuta = 'actas/'.$input->solicitudId.'.pdf';

        $solicitud->aprobar(curadorId: $input->curadorId);
        $solicitud->emitirActa($pdfRuta);

        $ahora = new DateTimeImmutable;
        $meses = $solicitud->duracionPropuestaMeses() ?? 3;
        $fechaFin = $ahora->modify("+{$meses} months");

        $acta = ActaPrestamo::emitir(
            id: $this->actaRepo->nextIdentity(),
            numeroPrestamo: NumeroPrestamo::generate(),
            solicitudPrestamoId: $solicitudId,
            tipoPrestamo: TipoPrestamo::Temporal,
            alcancePrestamo: $solicitud->alcancePrestamo(),
            fechaInicio: $ahora,
            fechaFin: $fechaFin,
            pdfRuta: $pdfRuta,
        );

        // Transiciona a PendienteFirma para que el investigador pueda firmarla
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

        return GenerarActaPrestamoOutput::fromPrimitives(
            solicitudId: $input->solicitudId,
            acta: $acta,
            notificacionEnviada: true,
        );
    }
}
