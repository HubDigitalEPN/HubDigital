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
    case VerificacionNoDisponible = 'verificacion_no_disponible';
    case FirmaInvalida = 'firma_invalida';
    case DocumentoModificado = 'documento_modificado';
    case CertificadoCaducado = 'certificado_caducado';
    case CertificadoAunNoVigente = 'certificado_aun_no_vigente';
    case CertificadoRevocado = 'certificado_revocado';
    case CertificadoNoConfiable = 'certificado_no_confiable';
    case AlmacenIncompleto = 'almacen_incompleto';
    case RevocacionNoComprobable = 'revocacion_no_comprobable';
    case FirmadoSinRevocacion = 'firmado_sin_revocacion';
}
