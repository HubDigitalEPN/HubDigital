<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Exceptions;

use DomainException;

/**
 * Excepción de dominio lanzada cuando se intenta aprobar (y firmar) un acta pero el
 * curador aún no ha registrado su certificado de firma electrónica en el sistema.
 */
final class CertificadoCuradorNoConfigurado extends DomainException
{
    public static function paraCurador(string $curadorId): self
    {
        return new self("El curador '{$curadorId}' no tiene un certificado de firma electrónica configurado.");
    }
}
