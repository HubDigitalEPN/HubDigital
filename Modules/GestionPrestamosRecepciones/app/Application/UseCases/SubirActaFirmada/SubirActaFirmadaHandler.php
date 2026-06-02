<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\SubirActaFirmada;

use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\ActaPrestamoNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\ActaPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudPrestamoId;

final class SubirActaFirmadaHandler
{
    public function __construct(
        private readonly ActaPrestamoRepositoryInterface $actaRepo,
        private readonly EventPublisherPort $publisher,
        private readonly TransactionManagerPort $transactionManager,
    ) {}

    public function handle(SubirActaFirmadaInput $input): SubirActaFirmadaOutput
    {
        $solicitudId = SolicitudPrestamoId::fromString($input->solicitudId);
        $acta        = $this->actaRepo->buscarPorSolicitudId($solicitudId);

        if ($acta === null) {
            throw ActaPrestamoNoEncontradaException::paraSolicitud($solicitudId);
        }

        $acta->subirFirma(
            pdfFirmadoRuta: $input->pdfFirmadoRuta,
            documentoIdentidadRuta: $input->documentoIdentidadRuta,
        );

        $this->transactionManager->executeTransactional(function () use ($acta): void {
            $this->actaRepo->guardar($acta);
            foreach ($acta->pullEvents() as $event) {
                $this->publisher->publish($event);
            }
        });

        return SubirActaFirmadaOutput::fromPrimitives($acta);
    }
}
