<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\Ports;

use Modules\GestionPrestamosRecepciones\Domain\Exceptions\FirmaPdfFallida;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\FirmaCuradorMetadata;

/**
 * Puerto de la capa de aplicación para firmar criptográficamente (PAdES) un PDF con
 * el certificado del curador.
 *
 * Lo implementa un adaptador en Infrastructure (p. ej. basado en pyHanko). El adaptador
 * lee el PDF de entrada del storage, lo firma y escribe el PDF firmado en la ruta de
 * salida, devolviendo la evidencia de la firma.
 */
interface FirmadorPdfPort
{
    /**
     * @param  string  $rutaPdfEntrada  Ruta relativa (storage) del PDF a firmar.
     * @param  CertificadoCurador  $certificado  Material del certificado del curador.
     * @param  string  $rutaPdfSalida  Ruta relativa (storage) donde escribir el PDF firmado.
     * @param  string  $etiqueta  Texto de contexto para la firma visible (p. ej. el código del préstamo).
     * @return FirmaCuradorMetadata Evidencia verificable de la firma aplicada.
     *
     * @throws FirmaPdfFallida Si la firma no se pudo completar.
     */
    public function firmar(
        string $rutaPdfEntrada,
        CertificadoCurador $certificado,
        string $rutaPdfSalida,
        string $etiqueta,
    ): FirmaCuradorMetadata;
}
