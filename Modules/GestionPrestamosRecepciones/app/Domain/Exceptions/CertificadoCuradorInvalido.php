<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Exceptions;

use DomainException;

/**
 * Excepción de dominio lanzada al registrar un certificado de firma cuando el archivo
 * PKCS#12 no puede abrirse con la contraseña indicada o está caducado.
 */
final class CertificadoCuradorInvalido extends DomainException
{
    public static function contrasenaIncorrecta(): self
    {
        return new self('No se pudo abrir el certificado: la contraseña es incorrecta o el archivo no es un PKCS#12 válido.');
    }

    public static function caducado(): self
    {
        return new self('El certificado de firma está caducado.');
    }
}
