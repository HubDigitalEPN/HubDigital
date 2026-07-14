<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\Ports;

use Modules\GestionPrestamosRecepciones\Domain\Exceptions\CertificadoCuradorInvalido;

/**
 * Puerto de la capa de aplicación para custodiar el certificado de firma electrónica
 * del curador (archivo PKCS#12) en un almacén cifrado.
 *
 * Lo implementa un adaptador en Infrastructure que cifra el .p12 y su contraseña en
 * reposo. La validación del PKCS#12 (contraseña correcta, vigencia) es responsabilidad
 * del adaptador al {@see guardar()}.
 */
interface CertificadoCuradorPort
{
    /**
     * Devuelve el certificado descifrado del curador para firmar, o null si no tiene.
     */
    public function obtener(string $curadorId): ?CertificadoCurador;

    /**
     * Valida el PKCS#12 contra su contraseña, extrae sus datos y lo persiste cifrado.
     *
     * @param  string  $contenidoP12  Bytes crudos del archivo .p12.
     * @return DatosCertificado Datos públicos del certificado registrado.
     *
     * @throws CertificadoCuradorInvalido Si el .p12 o la contraseña son inválidos.
     */
    public function guardar(string $curadorId, string $contenidoP12, string $contrasenia): DatosCertificado;
}
