<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Adapters;

use DateTimeImmutable;
use Illuminate\Support\Facades\Storage;
use Modules\GestionPrestamosRecepciones\Application\Ports\CertificadoCurador;
use Modules\GestionPrestamosRecepciones\Application\Ports\FirmadorPdfPort;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\FirmaPdfFallida;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\FirmaCuradorMetadata;
use Modules\GestionPrestamosRecepciones\Infrastructure\Support\Pkcs12Reader;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Adaptador que firma criptográficamente (PAdES) un PDF con el certificado del curador
 * invocando la herramienta de línea de comandos pyHanko, igual que
 * {@see PdfsigValidacionFirmaElectronicaAdapter} invoca pdfsig.
 *
 * La contraseña del PKCS#12 se pasa por stdin (nunca por argumentos ni logs). El
 * archivo .p12 se escribe temporalmente con permisos restringidos y se elimina al
 * finalizar. La evidencia de la firma se deriva del propio certificado con OpenSSL.
 */
final class PyHankoFirmadorPdfAdapter implements FirmadorPdfPort
{
    /**
     * @param  string  $binario  Ruta o nombre del ejecutable pyhanko.
     * @param  string  $campoFirma  Especificación del campo de firma visible pyHanko: "pagina/x1,y1,x2,y2/Nombre".
     * @param  string|null  $tsaUrl  URL del servidor de sellado de tiempo (TSA); null para PAdES-B-B sin TSA.
     */
    public function __construct(
        private readonly string $binario,
        private readonly string $campoFirma,
        private readonly ?string $tsaUrl,
    ) {}

    public function firmar(
        string $rutaPdfEntrada,
        CertificadoCurador $certificado,
        string $rutaPdfSalida,
        string $etiqueta,
    ): FirmaCuradorMetadata {
        $rutaEntradaAbs = Storage::path($rutaPdfEntrada);

        if (! file_exists($rutaEntradaAbs)) {
            throw FirmaPdfFallida::con("no se encontró el PDF de entrada '{$rutaPdfEntrada}'.");
        }

        $p12Tmp = tempnam(sys_get_temp_dir(), 'p12_');
        $passTmp = tempnam(sys_get_temp_dir(), 'pass_');
        $salidaTmp = tempnam(sys_get_temp_dir(), 'firmado_').'.pdf';

        try {
            @chmod($p12Tmp, 0600);
            @chmod($passTmp, 0600);
            file_put_contents($p12Tmp, $certificado->contenidoP12);
            // Sin salto de línea final: pyHanko toma el contenido del archivo tal cual.
            file_put_contents($passTmp, $certificado->contrasenia);

            $this->ejecutarPyHanko($rutaEntradaAbs, $salidaTmp, $p12Tmp, $passTmp);

            $contenidoFirmado = file_get_contents($salidaTmp);

            if ($contenidoFirmado === false || $contenidoFirmado === '') {
                throw FirmaPdfFallida::con('pyHanko no produjo un PDF firmado.');
            }

            Storage::put($rutaPdfSalida, $contenidoFirmado);

            return $this->metadataDesdeCertificado($certificado);
        } finally {
            @unlink($p12Tmp);
            @unlink($passTmp);
            @unlink($salidaTmp);
        }
    }

    private function ejecutarPyHanko(string $entrada, string $salida, string $p12, string $passfile): void
    {
        $binary = (new ExecutableFinder)->find('pyhanko', $this->binario);

        $comando = [$binary, 'sign', 'addsig', '--field', $this->campoFirma];

        if ($this->tsaUrl !== null && $this->tsaUrl !== '') {
            $comando[] = '--timestamp-url';
            $comando[] = $this->tsaUrl;
        }

        // Subcomando pkcs12: la contraseña se pasa por archivo (--passfile), no por
        // stdin: en algunos entornos (p. ej. Windows) pyHanko la pide del terminal y
        // el proceso se cuelga esperando entrada interactiva.
        array_push($comando, 'pkcs12', '--passfile', $passfile, $entrada, $salida, $p12);

        $process = new Process($comando);
        $process->setEnv($this->entorno());
        $process->setTimeout(60);
        $process->run();

        if (! $process->isSuccessful()) {
            // No se incluye la salida cruda para no arrastrar posibles secretos al log del dominio.
            throw FirmaPdfFallida::con('el proceso de firma pyHanko terminó con error (código '.$process->getExitCode().').');
        }
    }

    /**
     * Deriva la evidencia verificable de la firma leyendo el propio certificado.
     */
    private function metadataDesdeCertificado(CertificadoCurador $certificado): FirmaCuradorMetadata
    {
        $certPem = Pkcs12Reader::certificadoPem($certificado->contenidoP12, $certificado->contrasenia);

        if ($certPem === null) {
            throw FirmaPdfFallida::con('no se pudo leer el certificado para registrar la evidencia de firma.');
        }

        $info = openssl_x509_parse($certPem) ?: [];
        $huella = openssl_x509_fingerprint($certPem, 'sha256') ?: 'desconocida';

        return FirmaCuradorMetadata::crear(
            commonName: (string) ($info['subject']['CN'] ?? 'Desconocido'),
            serialCertificado: (string) ($info['serialNumberHex'] ?? $info['serialNumber'] ?? ''),
            huella: (string) $huella,
            algoritmo: (string) ($info['signatureTypeSN'] ?? 'SHA256withRSA'),
            selloDeTiempo: ($this->tsaUrl !== null && $this->tsaUrl !== '') ? new DateTimeImmutable : null,
        );
    }

    /**
     * Variables de entorno para el proceso. HOME es necesario cuando el worker de
     * Supervisor no hereda el entorno (mismo caso que pdfsig).
     *
     * @return array<string, string>
     */
    private function entorno(): array
    {
        $home = getenv('HOME') ?: sys_get_temp_dir();

        if (function_exists('posix_getpwuid') && function_exists('posix_getuid')) {
            $home = posix_getpwuid(posix_getuid())['dir'] ?? $home;
        }

        return ['LANG' => 'C', 'HOME' => $home];
    }
}
