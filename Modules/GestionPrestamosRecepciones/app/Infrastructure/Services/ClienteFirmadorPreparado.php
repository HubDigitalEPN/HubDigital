<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Services;

use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\DirectorioTemporalHubDigital;

/** Transporte privado al JAR de esta release. null permite el respaldo CLI. */
final class ClienteFirmadorPreparado
{
    /** La preparación es opcional: la firma siempre conserva su comprobación completa. */
    public function prepararOriginal(string $contenido): void
    {
        if (! $this->habilitado()) return;
        foreach ([FirmaPdfJava::class,
            \Modules\GestionPrestamosRecepciones\Infrastructure\Adapters\JavaValidacionFirmaElectronicaAdapter::class,
            \Modules\GestionPrestamosRecepciones\Domain\ValueObjects\DetalleValidacionFirma::class,
            \Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ResultadoValidacionFirma::class,
            \Modules\GestionPrestamosRecepciones\Infrastructure\Storage\ArchivoLocalDeposito::class,
            \Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador\FirmarSolicitudDeposito::class,
        ] as $clase) class_exists($clase);
        $archivo = null;
        try {
            $archivo = DirectorioTemporalHubDigital::crearArchivo('preparar-original-firma', 'original-', 16 * 1024 * 1024);
            if (file_put_contents($archivo, $contenido, LOCK_EX) === false) return;
            $this->firmar($archivo, '', '', '', 'prepare-only', 3);
        } catch (\Throwable) {
            // El original continúa disponible; una caché ausente nunca acredita una firma.
        } finally {
            if ($archivo !== null) DirectorioTemporalHubDigital::eliminar(dirname($archivo));
        }
    }

    public function validar(string $original, string $firmado): ?array
    {
        return $this->firmar($original, $firmado, '', '', 'verify-only');
    }

    public function validarExterno(string $ruta, float $espera): ?array
    {
        if (! $this->habilitado()) return null;
        $copia = null;
        $limite = microtime(true) + $espera;
        try {
            $tamano = filesize($ruta);
            if ($tamano === false || $tamano > 20 * 1024 * 1024) return null;
            $copia = DirectorioTemporalHubDigital::crearArchivo('validar-pdf-externo', 'pdf-', $tamano);
            if (! copy($ruta, $copia)) return null;
            $restante = $limite - microtime(true);
            return $restante > 0 ? $this->firmar($copia, '', '', '', 'verify-external', $restante) : null;
        } finally {
            if ($copia !== null) DirectorioTemporalHubDigital::eliminar(dirname($copia));
        }
    }

    public function firmar(string $original, string $salida, string $p12, string $clave, string $perfil, ?float $espera = null): ?array
    {
        if (! $this->habilitado()) {
            return null;
        }
        $limite = microtime(true) + ($espera ?? (int) config('firma-electronica.java_timeout', 60));
        $jar = (string) config('firma-electronica.java_signature_jar');
        $trust = realpath((string) config('firma-electronica.java_signature_trust_dir'));
        $hash = is_file($jar) ? hash_file('sha256', $jar) : false;
        if ($hash === false || $trust === false) return null;
        $limites = implode(':', [config('firma-electronica.max_pages', 40),
            config('firma-electronica.max_page_points', 1440), config('firma-electronica.render_dpi', 110),
            config('firma-electronica.max_render_pixels', 100000000)]);
        $entrada = implode("\0", [$hash, $trust, $limites, $original, $salida, $p12, $clave, $perfil]);
        $socket = null;
        $claveLocal = '';
        try {
            if (strlen($entrada) > 65536) return null;
            $local = PHP_OS_FAMILY === 'Windows';
            if ($local) {
                $rutaClave = (string) config('firma-electronica.worker_local_key');
                if (! is_file($rutaClave) || is_link($rutaClave) || filesize($rutaClave) !== 32) return null;
                $claveLocal = (string) @file_get_contents($rutaClave);
                if (strlen($claveLocal) !== 32) return null;
            }
            $socket = @stream_socket_client($local ? 'tcp://127.0.0.1:17655'
                : 'unix://'.config('firma-electronica.worker_socket'), $codigo, $error, 0.2);
            if ($socket === false) return null;
            if (! $this->plazo($socket, $limite)) return null;
            $prueba = null;
            if ($local) {
                $nonce = bin2hex(random_bytes(32));
                $this->escribir($socket, 'hello:'.$nonce.':'.hash_hmac('sha256', $nonce, $claveLocal));
                $prueba = $this->respuesta($socket, $limite);
                if (($prueba['status'] ?? '') !== 'ready' || ! hash_equals($hash, (string) ($prueba['jar_sha256'] ?? ''))
                    || ! hash_equals(hash_hmac('sha256', $nonce."\0".$hash, $claveLocal), (string) ($prueba['proof'] ?? ''))) return null;
            }
            if ($perfil === 'verify-external') {
                if (! $local) {
                    $this->escribir($socket, 'health');
                    $prueba = $this->respuesta($socket, $limite);
                }
                if (($prueba['jar_sha256'] ?? '') !== $hash
                    || ! in_array('verify-external', $prueba['operations'] ?? [], true)) return null;
            }
            $paquete = pack('N', strlen($entrada)).$entrada;
            for ($offset = 0; $offset < strlen($paquete); $offset += $n) {
                if (! $this->plazo($socket, $limite)) return null;
                $n = fwrite($socket, substr($paquete, $offset));
                if ($n === false || $n === 0) return null;
            }
            $cabecera = $this->leer($socket, 4, $limite);
            if ($cabecera === null) return null;
            $tamano = unpack('N', $cabecera)[1];
            if ($tamano < 1 || $tamano > 65536) return null;
            $respuesta = $this->leer($socket, $tamano, $limite);
            $resultado = $respuesta === null ? null : json_decode($respuesta, true);
            if (! is_array($resultado) || ($resultado['status'] ?? null) === 'worker_incompatible') return null;

            return $resultado;
        } finally {
            $entrada = $paquete = $clave = $claveLocal = '';
            if (is_resource($socket)) fclose($socket);
        }
    }

    private function habilitado(): bool
    {
        return config('firma-electronica.worker_enabled', false)
            && (PHP_OS_FAMILY === 'Linux' || (PHP_OS_FAMILY === 'Windows' && app()->environment('local')));
    }

    /** @param resource $socket */
    private function escribir($socket, string $valor): void
    {
        $bytes = pack('N', strlen($valor)).$valor;
        for ($offset = 0; $offset < strlen($bytes); $offset += $n) {
            $n = fwrite($socket, substr($bytes, $offset));
            if ($n === false || $n === 0) throw new \RuntimeException('No se pudo conectar al motor local.');
        }
    }

    /** @param resource $socket */
    private function respuesta($socket, ?float $limite = null): ?array
    {
        $cabecera = $this->leer($socket, 4, $limite);
        if ($cabecera === null) return null;
        $tamano = unpack('N', $cabecera)[1];
        if ($tamano < 1 || $tamano > 65536) return null;
        $bytes = $this->leer($socket, $tamano, $limite);
        $resultado = $bytes === null ? null : json_decode($bytes, true);
        return is_array($resultado) ? $resultado : null;
    }

    /** @param resource $socket */
    private function leer($socket, int $tamano, ?float $limite = null): ?string
    {
        $valor = '';
        while (strlen($valor) < $tamano) {
            if ($limite !== null && ! $this->plazo($socket, $limite)) return null;
            $parte = fread($socket, $tamano - strlen($valor));
            if ($parte === false || $parte === '') return null;
            $valor .= $parte;
        }

        return $valor;
    }

    /** Un único plazo para cabecera, cuerpo y autenticación, sin reiniciarlo por fragmento. */
    private function plazo($socket, float $limite): bool
    {
        $restante = $limite - microtime(true);
        if ($restante <= 0) return false;
        $segundos = (int) $restante;
        return stream_set_timeout($socket, $segundos, (int) (($restante - $segundos) * 1_000_000));
    }
}
