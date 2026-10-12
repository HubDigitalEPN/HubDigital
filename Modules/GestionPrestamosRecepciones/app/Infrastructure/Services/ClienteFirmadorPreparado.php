<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Services;

/** Transporte privado al JAR de esta release. null permite el respaldo CLI. */
final class ClienteFirmadorPreparado
{
    public function firmar(string $original, string $salida, string $p12, string $clave, string $perfil): ?array
    {
        if (! config('firma-electronica.worker_enabled', false) || PHP_OS_FAMILY !== 'Linux') {
            return null;
        }
        $jar = (string) config('firma-electronica.java_signature_jar');
        $trust = realpath((string) config('firma-electronica.java_signature_trust_dir'));
        $hash = is_file($jar) ? hash_file('sha256', $jar) : false;
        if ($hash === false || $trust === false) return null;
        $limites = implode(':', [config('firma-electronica.max_pages', 40),
            config('firma-electronica.max_page_points', 1440), config('firma-electronica.render_dpi', 110),
            config('firma-electronica.max_render_pixels', 100000000)]);
        $entrada = implode("\0", [$hash, $trust, $limites, $original, $salida, $p12, $clave, $perfil]);
        $socket = null;
        try {
            if (strlen($entrada) > 65536) return null;
            $socket = @stream_socket_client('unix://'.config('firma-electronica.worker_socket'), $codigo, $error, 0.2);
            if ($socket === false) return null;
            stream_set_timeout($socket, (int) config('firma-electronica.java_timeout', 60));
            $paquete = pack('N', strlen($entrada)).$entrada;
            for ($offset = 0; $offset < strlen($paquete); $offset += $n) {
                $n = fwrite($socket, substr($paquete, $offset));
                if ($n === false || $n === 0) return null;
            }
            $cabecera = $this->leer($socket, 4);
            if ($cabecera === null) return null;
            $tamano = unpack('N', $cabecera)[1];
            if ($tamano < 1 || $tamano > 65536) return null;
            $respuesta = $this->leer($socket, $tamano);
            $resultado = $respuesta === null ? null : json_decode($respuesta, true);
            if (! is_array($resultado) || ($resultado['status'] ?? null) === 'worker_incompatible') return null;

            return $resultado;
        } finally {
            $entrada = $paquete = $clave = '';
            if (is_resource($socket)) fclose($socket);
        }
    }

    /** @param resource $socket */
    private function leer($socket, int $tamano): ?string
    {
        $valor = '';
        while (strlen($valor) < $tamano) {
            $parte = fread($socket, $tamano - strlen($valor));
            if ($parte === false || $parte === '') return null;
            $valor .= $parte;
        }

        return $valor;
    }
}
