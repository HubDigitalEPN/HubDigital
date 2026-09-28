<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Adapters;

use Illuminate\Support\Facades\Log;
use Modules\GestionPrestamosRecepciones\Application\Ports\ValidacionFirmaElectronicaPort;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\DetalleValidacionFirma;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ResultadoValidacionFirma;
use Modules\GestionPrestamosRecepciones\Infrastructure\Services\PrepararFuentesRevocacionFirma;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\DirectorioTemporalHubDigital;
use Symfony\Component\Process\Process;

/** Transporta al único motor Java los PDF y devuelve su dictamen, sin validadores alternativos. */
final class JavaValidacionFirmaElectronicaAdapter implements ValidacionFirmaElectronicaPort
{
    public function verificarFirma(string $rutaAbsoluta): ResultadoValidacionFirma
    {
        $resultado = $this->ejecutarJava($rutaAbsoluta);

        return ResultadoValidacionFirma::tryFrom((string) ($resultado['status'] ?? ''))
            ?? ResultadoValidacionFirma::VerificacionNoDisponible;
    }

    public function verificarFirmaDetallada(string $rutaFirmadaAbsoluta, string $rutaOriginalAbsoluta): DetalleValidacionFirma
    {
        $resultado = $this->ejecutarJava($rutaFirmadaAbsoluta, $rutaOriginalAbsoluta);

        return new DetalleValidacionFirma(
            resultado: ResultadoValidacionFirma::tryFrom((string) ($resultado['status'] ?? ''))
                ?? ResultadoValidacionFirma::VerificacionNoDisponible,
            integridadCriptografica: ($resultado['cryptographically_valid'] ?? false) === true,
            documentoCompletoFirmado: ($resultado['documento_completo_firmado'] ?? false) === true,
            contenidoOficialCoincide: ($resultado['contenido_oficial_coincide'] ?? false) === true,
            certificadoVigente: ($resultado['certificado_vigente'] ?? false) === true,
            certificadoConfiable: ($resultado['certificado_confiable'] ?? false) === true,
            certificado: is_array($resultado['certificado'] ?? null) ? $resultado['certificado'] : [],
            error: ($resultado['aceptable'] ?? false) === true ? null : (string) ($resultado['reason'] ?? 'Java no pudo completar la validación.'),
            aceptadaPorMotor: ($resultado['aceptable'] ?? false) === true,
            formatoFirmaAceptado: ($resultado['formato_firma_aceptado'] ?? false) === true,
        );
    }

    /** @return array<string, mixed> */
    private function ejecutarJava(string $ruta, ?string $original = null): array
    {
        $jar = (string) config('firma-electronica.java_signature_jar');
        if (! is_file($ruta) || ($original !== null && ! is_file($original)) || ! is_file($jar)) {
            return $this->noDisponible('No está disponible el PDF o el motor Java de validación.');
        }

        $inicio = microtime(true);
        try {
            // Las alternativas administradas son opcionales: el motor también usa
            // la evidencia del PDF y las fuentes publicadas en cada certificado.
            try {
                $fuentes = app(PrepararFuentesRevocacionFirma::class)->preparar();
            } catch (\Throwable $error) {
                Log::warning('No se pudieron cargar las fuentes excepcionales de revocación', ['error' => $error->getMessage()]);
                $fuentes = ['ocsp' => [], 'crl' => [], 'timeout' => 2];
            }
            $espera = max(1, min(5, $fuentes['timeout']));
            $comando = [
                (string) config('firma-electronica.java_binary', 'java'),
                '-Djava.awt.headless=true',
                '-Dcom.sun.security.ocsp.timeout='.$espera,
                '-Dcom.sun.security.crl.timeout='.$espera,
                '-Dhubdigital.revocation.timeout='.$espera,
                '-Dsun.net.client.defaultConnectTimeout='.($espera * 1000),
                '-Dsun.net.client.defaultReadTimeout='.($espera * 1000),
                '-Dhubdigital.pdf.max_pages='.(int) config('firma-electronica.max_pages', 40),
                '-Dhubdigital.pdf.max_page_points='.(int) config('firma-electronica.max_page_points', 1440),
                '-Dhubdigital.pdf.render_dpi='.(int) config('firma-electronica.render_dpi', 110),
                '-Dhubdigital.pdf.max_render_pixels='.(int) config('firma-electronica.max_render_pixels', 100_000_000),
                '-jar', $jar, 'verify', '--paths-stdin',
            ];
            $proceso = new Process($comando);
            $proceso->setInput($ruta.($original !== null ? "\0".$original : ''));
            $proceso->setEnv(array_merge(DirectorioTemporalHubDigital::entornoProcesos(), [
                'HUBDIGITAL_SIGNATURE_TRUST_DIR' => (string) config('firma-electronica.java_signature_trust_dir'),
                'HUBDIGITAL_SIGNATURE_OCSP_RESPONDERS' => implode('|', $fuentes['ocsp']),
                'HUBDIGITAL_SIGNATURE_CRL_OVERRIDES' => implode('|', $fuentes['crl']),
            ]));
            $proceso->setTimeout((int) config('firma-electronica.java_timeout', 60));
            $proceso->run();
            $resultado = json_decode(trim($proceso->getOutput()), true);
            if (! $proceso->isSuccessful() || ! is_array($resultado) || ! is_string($resultado['status'] ?? null)) {
                Log::warning('El motor Java no devolvió un dictamen de firma', [
                    'exit_code' => $proceso->getExitCode(),
                    'error' => $proceso->getErrorOutput(),
                ]);

                return $this->noDisponible('El motor Java no pudo completar la validación de firma.');
            }
            Log::info('Comprobación Java de firma PDF', [
                'duracion_ms' => (int) round((microtime(true) - $inicio) * 1000),
                'estado' => $resultado['status'],
                'motivo' => $resultado['reason'] ?? '',
            ]);

            return $resultado;
        } catch (\Throwable $error) {
            Log::warning('No se pudo ejecutar el motor Java de firmas', ['error' => $error->getMessage()]);

            return $this->noDisponible('No fue posible ejecutar el motor Java de validación de firmas.');
        }
    }

    /** @return array{status: string, reason: string} */
    private function noDisponible(string $motivo): array
    {
        return ['status' => ResultadoValidacionFirma::VerificacionNoDisponible->value, 'reason' => $motivo];
    }
}
