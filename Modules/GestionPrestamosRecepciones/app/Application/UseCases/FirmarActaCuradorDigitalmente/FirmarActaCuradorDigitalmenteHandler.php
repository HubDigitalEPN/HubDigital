<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\FirmarActaCuradorDigitalmente;

use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\PdfGeneratorPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarActaDocumento\ConsultarActaDocumentoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarActaDocumento\ConsultarActaDocumentoInput;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\ActaPrestamoNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\FirmaBase64Invalida;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\ActaPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ActaPrestamoId;

/**
 * El curador firma el acta dibujando su firma en canvas y, con ello, la valida.
 *
 * Regenera el PDF final desde la plantilla estándar del acta incrustando tanto la
 * firma canvas del investigador (si firmó por esa vía) como la del curador, y lo
 * almacena como el documento firmado por el curador. Luego valida el acta.
 *
 * {@see FirmarActaCuradorDigitalmenteInput}
 * {@see FirmarActaCuradorDigitalmenteOutput}
 */
final class FirmarActaCuradorDigitalmenteHandler
{
    public function __construct(
        private readonly ActaPrestamoRepositoryInterface $actaRepo,
        private readonly PdfGeneratorPort $pdfGenerator,
        private readonly EventPublisherPort $publisher,
        private readonly TransactionManagerPort $transactionManager,
        private readonly ConsultarActaDocumentoHandler $actaDocumento,
    ) {}

    /**
     * @throws ActaPrestamoNoEncontradaException
     * @throws FirmaBase64Invalida
     */
    public function handle(FirmarActaCuradorDigitalmenteInput $input): FirmarActaCuradorDigitalmenteOutput
    {
        $actaId = ActaPrestamoId::fromString($input->actaId);
        $acta = $this->actaRepo->buscarPorId($actaId);

        if ($acta === null) {
            throw ActaPrestamoNoEncontradaException::conId($actaId);
        }

        $this->validarFirmaBase64($input->firmaBase64);

        // La firma canvas del investigador, cuando existe, se guardó como PNG en
        // 'firmas-investigador/...'. Se re-incrusta en la plantilla junto a la del
        // curador. Si el investigador subió un PDF (no hay PNG), su firma vive en su
        // propio documento y aquí solo se estampa la del curador.
        $firmadoInvestigador = $acta->pdfFirmadoRuta();
        $firmaInvestigadorBase64 = ($firmadoInvestigador !== null
            && str_starts_with($firmadoInvestigador, 'firmas-investigador/'))
            ? $this->pdfGenerator->leerImagenBase64($firmadoInvestigador)
            : null;

        $firmaCuradorRuta = 'firmas-curador/'.(string) $actaId.'.png';
        $this->pdfGenerator->almacenarImagenPng(
            base64: $input->firmaBase64,
            rutaDestino: $firmaCuradorRuta,
        );

        $documento = $this->actaDocumento->handle(
            new ConsultarActaDocumentoInput(actaId: (string) $actaId),
        );

        $rutaSalida = 'actas-firmadas-curador/'.(string) $actaId.'.pdf';

        $this->pdfGenerator->generarActaYAlmacenar(
            datos: [
                'acta' => $documento,
                'firmaBase64' => $firmaInvestigadorBase64,
                'firmaCuradorBase64' => $input->firmaBase64,
            ],
            rutaDestino: $rutaSalida,
        );

        $acta->validarConFirmaCurador(
            curadorId: $input->curadorId,
            pdfFirmadoCuradorRuta: $rutaSalida,
        );

        $this->transactionManager->executeTransactional(function () use ($acta): void {
            $this->actaRepo->guardar($acta);
            foreach ($acta->pullEvents() as $event) {
                $this->publisher->publish($event);
            }
        });

        return FirmarActaCuradorDigitalmenteOutput::fromPrimitives($acta);
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
