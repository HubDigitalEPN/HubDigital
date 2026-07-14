<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\FirmarActaDigitalmente;

use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\PdfGeneratorPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarActaDocumento\ConsultarActaDocumentoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarActaDocumento\ConsultarActaDocumentoInput;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\ActaNoPerteneceAlInvestigador;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\ActaPrestamoNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\FirmaBase64Invalida;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\PatenteAnualNoConfigurada;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\ActaPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\PatenteAnualRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ActaPrestamoId;

/**
 * Firma digitalmente un acta de préstamo.
 *
 * {@see FirmarActaDigitalmenteInput}
 * {@see FirmarActaDigitalmenteOutput}
 */
final class FirmarActaDigitalmenteHandler
{
    public function __construct(
        private readonly ActaPrestamoRepositoryInterface $actaRepo,
        private readonly SolicitudPrestamoRepositoryInterface $solicitudRepo,
        private readonly PdfGeneratorPort $pdfGenerator,
        private readonly EventPublisherPort $publisher,
        private readonly TransactionManagerPort $transactionManager,
        private readonly ConsultarActaDocumentoHandler $actaDocumento,
        private readonly PatenteAnualRepositoryInterface $patentes,
    ) {}

    /**
     * @param FirmarActaDigitalmenteInput $input
     * @return FirmarActaDigitalmenteOutput
     * @throws ActaPrestamoNoEncontradaException
     * @throws ActaNoPerteneceAlInvestigador
     * @throws FirmaBase64Invalida
     */
    public function handle(FirmarActaDigitalmenteInput $input): FirmarActaDigitalmenteOutput
    {
        $actaId = ActaPrestamoId::fromString($input->actaId);
        $acta = $this->actaRepo->buscarPorId($actaId);

        if ($acta === null) {
            throw ActaPrestamoNoEncontradaException::conId($actaId);
        }

        $solicitud = $this->solicitudRepo->buscarPorId($acta->solicitudPrestamoId());

        if ($solicitud === null || $solicitud->investigadorId() !== $input->investigadorId) {
            throw ActaNoPerteneceAlInvestigador::conActaId($actaId);
        }

        $this->validarFirmaBase64($input->firmaBase64);

        $anioPatente = (int) $acta->fechaInicio()->format('Y');
        $patente = $this->patentes->buscarCodigoPorAnio($anioPatente);

        if ($patente === null) {
            throw PatenteAnualNoConfigurada::paraAnio($anioPatente);
        }

        $firmaImagenRuta = 'firmas-investigador/'.(string) $actaId.'.png';

        $this->pdfGenerator->almacenarImagenPng(
            base64: $input->firmaBase64,
            rutaDestino: $firmaImagenRuta,
        );

        // Se firma sobre la MISMA plantilla estandarizada que se descarga
        // (acta-documento), con la firma dibujada del investigador ya incrustada.
        // El sello PAdES del curador se estampa después en ValidarActaFirmada.
        $documento = $this->actaDocumento->handle(
            new ConsultarActaDocumentoInput(actaId: (string) $actaId),
        );

        $this->pdfGenerator->generarYAlmacenar(
            vista: 'gestionprestamosrecepciones::pdf.acta-documento',
            datos: [
                'acta' => $documento,
                'firmaBase64' => $input->firmaBase64,
            ],
            rutaDestino: $acta->pdfRuta(),
        );

        $acta->firmarDigitalmente($firmaImagenRuta);

        $this->transactionManager->executeTransactional(function () use ($acta): void {
            $this->actaRepo->guardar($acta);
            foreach ($acta->pullEvents() as $event) {
                $this->publisher->publish($event);
            }
        });

        return FirmarActaDigitalmenteOutput::fromPrimitives($acta);
    }

    private function validarFirmaBase64(string $firmaBase64): void
    {
        if (! str_starts_with($firmaBase64, 'data:image/png;base64,')) {
            throw FirmaBase64Invalida::formatoInvalido();
        }

        $base64Data = substr($firmaBase64, strpos($firmaBase64, ',') + 1);
        $base64Data = str_replace(' ', '+', $base64Data);

        if (base64_decode($base64Data, strict: true) === false) {
            throw FirmaBase64Invalida::decodificacionFallida();
        }
    }
}
