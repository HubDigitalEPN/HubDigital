<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ValidarActaFirmada;

use Modules\GestionPrestamosRecepciones\Application\Ports\CertificadoCuradorPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\FirmadorPdfPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\ActaPrestamoNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\CertificadoCuradorNoConfigurado;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\FirmaPdfFallida;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\ActaPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ActaPrestamoId;

/**
 * Valida (aprueba) un acta firmada y, en el mismo acto, la firma criptográficamente
 * de forma automática con el certificado del curador (PAdES).
 *
 * La firma ocurre antes de persistir el estado: si falla, la aprobación no se completa.
 *
 * {@see ValidarActaFirmadaInput}
 * {@see ValidarActaFirmadaOutput}
 */
final class ValidarActaFirmadaHandler
{
    public function __construct(
        private readonly ActaPrestamoRepositoryInterface $actaRepo,
        private readonly EventPublisherPort $publisher,
        private readonly TransactionManagerPort $transactionManager,
        private readonly CertificadoCuradorPort $certificados,
        private readonly FirmadorPdfPort $firmador,
    ) {}

    /**
     * @throws ActaPrestamoNoEncontradaException
     * @throws CertificadoCuradorNoConfigurado
     * @throws FirmaPdfFallida
     */
    public function handle(ValidarActaFirmadaInput $input): ValidarActaFirmadaOutput
    {
        $actaId = ActaPrestamoId::fromString($input->actaId);
        $acta = $this->actaRepo->buscarPorId($actaId);

        if ($acta === null) {
            throw ActaPrestamoNoEncontradaException::conId($actaId);
        }

        $certificado = $this->certificados->obtener($input->curadorId);

        if ($certificado === null) {
            throw CertificadoCuradorNoConfigurado::paraCurador($input->curadorId);
        }

        // pdfFirmadoRuta guarda el PDF firmado por el investigador en el flujo de subida,
        // pero la ruta de una imagen PNG en el flujo de canvas (que regenera el PDF final
        // en pdfRuta). Se firma el PDF real según el flujo.
        // ponytail: heurística por extensión; nace del doble uso de pdfFirmadoRuta.
        $firmadoInvestigador = $acta->pdfFirmadoRuta();
        $rutaEntrada = ($firmadoInvestigador !== null && str_ends_with(strtolower($firmadoInvestigador), '.pdf'))
            ? $firmadoInvestigador
            : $acta->pdfRuta();

        $rutaSalida = 'actas-firmadas-curador/'.((string) $acta->id()).'.pdf';

        $metadata = $this->firmador->firmar(
            rutaPdfEntrada: $rutaEntrada,
            certificado: $certificado,
            rutaPdfSalida: $rutaSalida,
            etiqueta: (string) $acta->codigoPrestamo(),
        );

        $acta->validarConFirmaCurador(
            curadorId: $input->curadorId,
            pdfFirmadoCuradorRuta: $rutaSalida,
            firma: $metadata,
        );

        $this->transactionManager->executeTransactional(function () use ($acta): void {
            $this->actaRepo->guardar($acta);
            foreach ($acta->pullEvents() as $event) {
                $this->publisher->publish($event);
            }
        });

        return ValidarActaFirmadaOutput::fromPrimitives($acta);
    }
}
