<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Services;

use Illuminate\Http\Request;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\ArchivoLocalDeposito;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\DirectorioTemporalHubDigital;
use Symfony\Component\Process\Process;

/** Transporte efímero: la clave privada y toda operación criptográfica pertenecen a Java. */
final class FirmaPdfJava
{
    public static function reglas(): array
    {
        return [
            'pdf_firmado' => ['required_without:certificado', 'prohibits:certificado', 'file', 'mimes:pdf', 'max:15360'],
            'certificado' => ['required_without:pdf_firmado', 'prohibits:pdf_firmado', 'file', 'extensions:p12,pfx', 'max:5120'],
            'clave_certificado' => ['required_with:certificado', 'string', 'max:512', 'not_regex:/\x00/'],
        ];
    }

    public function preparar(Request $request, string $original, string $perfil, bool $validarOriginal = false): ArchivoLocalDeposito
    {
        if (! $request->hasFile('certificado')) {
            return new ArchivoLocalDeposito($request->file('pdf_firmado')->getRealPath(), false);
        }
        $directorio = DirectorioTemporalHubDigital::crear('firma-java', 24 * 1024 * 1024);
        $p12 = $directorio.DIRECTORY_SEPARATOR.'credencial.p12';
        $salida = $directorio.DIRECTORY_SEPARATOR.'firmado.pdf';
        $proceso = null;
        try {
            if (! copy($request->file('certificado')->getRealPath(), $p12)) {
                throw new \RuntimeException('No se pudo preparar el certificado para Java.');
            }
            @chmod($p12, 0600);
            $fuentes = ['ocsp' => [], 'crl' => [], 'timeout' => 2];
            if ($validarOriginal) {
                try { $fuentes = app(PrepararFuentesRevocacionFirma::class)->preparar(); }
                catch (\Throwable $error) {
                    \Illuminate\Support\Facades\Log::warning('No se pudieron cargar las fuentes excepcionales de revocación', ['error_tipo' => $error::class]);
                }
            }
            $espera = max(1, min(5, $fuentes['timeout']));
            $resultado = null;
            if ($validarOriginal && $fuentes['ocsp'] === [] && $fuentes['crl'] === [] && $espera === 2) {
                // Una salida distinta impide que un servicio tardío sobrescriba el respaldo CLI.
                $salidaServicio = $directorio.DIRECTORY_SEPARATOR.'servicio-'.bin2hex(random_bytes(8)).'.pdf';
                $resultado = app(ClienteFirmadorPreparado::class)->firmar($original, $salidaServicio, $p12,
                    (string) $request->input('clave_certificado'), $perfil);
                if ($resultado !== null && is_file($salidaServicio) && ! rename($salidaServicio, $salida)) {
                    throw new \RuntimeException('No se pudo recuperar el PDF del firmador preparado.');
                }
            }
            if ($resultado === null) {
                $proceso = new Process([
                    (string) config('firma-electronica.java_binary', 'java'),
                    '-Djava.awt.headless=true', '-Xmx384m',
                    '-Dcom.sun.security.ocsp.timeout='.$espera,
                    '-Dcom.sun.security.crl.timeout='.$espera,
                    '-Dhubdigital.revocation.timeout='.$espera,
                    '-Dsun.net.client.defaultConnectTimeout='.($espera * 1000),
                    '-Dsun.net.client.defaultReadTimeout='.($espera * 1000),
                    '-Dhubdigital.pdf.max_pages='.(int) config('firma-electronica.max_pages', 40),
                    '-Dhubdigital.pdf.max_page_points='.(int) config('firma-electronica.max_page_points', 1440),
                    '-Dhubdigital.pdf.render_dpi='.(int) config('firma-electronica.render_dpi', 110),
                    '-Dhubdigital.pdf.max_render_pixels='.(int) config('firma-electronica.max_render_pixels', 100000000),
                    '-jar', (string) config('firma-electronica.java_signature_jar'),
                    $validarOriginal ? 'sign-verify' : 'sign', '--request-stdin',
                ]);
                $proceso->setEnv(array_merge(DirectorioTemporalHubDigital::entornoProcesos(), [
                    'HUBDIGITAL_SIGNATURE_TRUST_DIR' => (string) config('firma-electronica.java_signature_trust_dir'),
                    'HUBDIGITAL_SIGNATURE_OCSP_RESPONDERS' => implode('|', $fuentes['ocsp']),
                    'HUBDIGITAL_SIGNATURE_CRL_OVERRIDES' => implode('|', $fuentes['crl']),
                ]));
                $proceso->setInput(implode("\0", [$original, $salida, $p12,
                    (string) $request->input('clave_certificado'), $perfil]));
                $proceso->setTimeout((int) config('firma-electronica.java_timeout', 60));
                $proceso->run();
                $resultado = json_decode(trim($proceso->getOutput()), true);
            }
            if (($proceso !== null && ! $proceso->isSuccessful()) || ! is_array($resultado)
                || (! $validarOriginal && ($resultado['status'] ?? null) !== 'firmado') || ! is_file($salida)) {
                throw new \InvalidArgumentException($resultado['reason'] ?? 'No se pudo firmar. Verifica el certificado, su contraseña y su vigencia.');
            }
            @chmod($salida, 0600);
            @unlink($p12);

            return new ArchivoLocalDeposito($salida, true, $directorio, $validarOriginal ? $resultado : null);
        } catch (\Throwable $error) {
            DirectorioTemporalHubDigital::eliminar($directorio);
            throw $error;
        } finally {
            $proceso?->setInput(null);
            $request->request->remove('clave_certificado');
            $temporalCredencial = $request->file('certificado')?->getRealPath();
            if (is_string($temporalCredencial)) @unlink($temporalCredencial);
            $request->files->remove('certificado');
        }
    }
}
