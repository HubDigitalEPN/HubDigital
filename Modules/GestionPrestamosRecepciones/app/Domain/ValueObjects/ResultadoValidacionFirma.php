<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\ValueObjects;

/**
 * Resultado de verificar la firma electrónica de un PDF: firmado, sin firma o no
 * verificado (no se pudo determinar). Lo devuelve el adaptador de validación de firma.
 */
enum ResultadoValidacionFirma: string
{
    case Firmado = 'firmado';
    case SinFirma = 'sin_firma';
    case NoVerificado = 'no_verificado';
}
