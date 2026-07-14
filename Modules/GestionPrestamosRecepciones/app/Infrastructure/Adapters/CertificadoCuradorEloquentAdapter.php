<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Adapters;

use DateTimeImmutable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Modules\GestionPrestamosRecepciones\Application\Ports\CertificadoCurador;
use Modules\GestionPrestamosRecepciones\Application\Ports\CertificadoCuradorPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\DatosCertificado;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\CertificadoCuradorInvalido;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\CertificadoCuradorModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Support\Pkcs12Reader;

/**
 * Adaptador que custodia el certificado PKCS#12 del curador en la tabla
 * 'prestamos.certificados_curador', cifrando el archivo y la contraseña en reposo.
 *
 * Implementa {@see CertificadoCuradorPort}. La validación del PKCS#12 (contraseña,
 * vigencia) y la extracción de sus datos se hacen con OpenSSL aquí, manteniendo la
 * criptografía fuera del dominio.
 */
final class CertificadoCuradorEloquentAdapter implements CertificadoCuradorPort
{
    public function obtener(string $curadorId): ?CertificadoCurador
    {
        $model = CertificadoCuradorModel::where('curador_id', $curadorId)->first();

        if ($model === null) {
            return null;
        }

        return new CertificadoCurador(
            contenidoP12: Crypt::decryptString($model->p12_cifrado),
            contrasenia: Crypt::decryptString($model->contrasenia_cifrada),
        );
    }

    public function guardar(string $curadorId, string $contenidoP12, string $contrasenia): DatosCertificado
    {
        $datos = $this->extraerDatos($contenidoP12, $contrasenia);

        CertificadoCuradorModel::updateOrCreate(
            ['curador_id' => $curadorId],
            [
                'id' => (string) Str::uuid(),
                'p12_cifrado' => Crypt::encryptString($contenidoP12),
                'contrasenia_cifrada' => Crypt::encryptString($contrasenia),
                'common_name' => $datos->commonName,
                'serial' => $datos->serial,
                'valido_hasta' => $datos->validoHasta->format('Y-m-d H:i:s'),
            ],
        );

        return $datos;
    }

    /**
     * Abre el PKCS#12, verifica la contraseña y la vigencia, y extrae sus datos públicos.
     *
     * @throws CertificadoCuradorInvalido
     */
    private function extraerDatos(string $contenidoP12, string $contrasenia): DatosCertificado
    {
        // Pkcs12Reader tolera el cifrado legacy (RC2) de los certificados ecuatorianos.
        $certPem = Pkcs12Reader::certificadoPem($contenidoP12, $contrasenia);

        if ($certPem === null) {
            throw CertificadoCuradorInvalido::contrasenaIncorrecta();
        }

        $info = openssl_x509_parse($certPem);

        if ($info === false) {
            throw CertificadoCuradorInvalido::contrasenaIncorrecta();
        }

        $validoHasta = (new DateTimeImmutable)->setTimestamp((int) $info['validTo_time_t']);

        if ($validoHasta < new DateTimeImmutable) {
            throw CertificadoCuradorInvalido::caducado();
        }

        return new DatosCertificado(
            commonName: (string) ($info['subject']['CN'] ?? 'Desconocido'),
            serial: (string) ($info['serialNumberHex'] ?? $info['serialNumber'] ?? ''),
            validoHasta: $validoHasta,
        );
    }
}
