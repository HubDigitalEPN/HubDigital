<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\ValueObjects;

enum ResultadoValidacionFirma: string
{
    case Firmado = 'firmado';
    case SinFirma = 'sin_firma';
    case NoVerificado = 'no_verificado';
}
