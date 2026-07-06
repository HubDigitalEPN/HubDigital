<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Support;

use Symfony\Component\Process\Process;

/**
 * Lee el certificado de un PKCS#12 tolerando el cifrado "legacy" (RC2-40, etc.) que
 * usan muchos certificados ecuatorianos y que OpenSSL 3 desactiva por defecto.
 *
 * Primero intenta la función nativa {@see openssl_pkcs12_read()} (rápida, cubre los
 * .p12 modernos y 3DES). Si falla —típico con RC2— cae a un script Python que usa la
 * librería `cryptography` (la misma que pyHanko), que lee PKCS#12 legacy en cualquier
 * plataforma sin depender del proveedor legacy de OpenSSL. La contraseña se pasa por
 * stdin (nunca por argumentos ni logs).
 */
final class Pkcs12Reader
{
    /**
     * Devuelve el certificado del PKCS#12 en formato PEM, o null si el archivo o la
     * contraseña no son válidos.
     */
    public static function certificadoPem(string $contenidoP12, string $contrasenia): ?string
    {
        $out = [];
        if (@openssl_pkcs12_read($contenidoP12, $out, $contrasenia)) {
            return $out['cert'] ?? null;
        }

        return self::viaPython($contenidoP12, $contrasenia);
    }

    private static function viaPython(string $contenidoP12, string $contrasenia): ?string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'p12r_');

        try {
            @chmod($tmp, 0600);
            file_put_contents($tmp, $contenidoP12);

            $python = config('gestionprestamosrecepciones.firma.python_bin', 'python');
            $script = __DIR__.'/leer_p12.py';

            $process = new Process([$python, $script, $tmp]);
            $process->setInput($contrasenia);
            $process->setTimeout(20);
            $process->run();

            if ($process->isSuccessful()
                && preg_match('/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s', $process->getOutput(), $m)) {
                return $m[0];
            }

            return null;
        } finally {
            @unlink($tmp);
        }
    }
}
