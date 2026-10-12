<?php

declare(strict_types=1);

// La activación exige una JVM preparada que corresponda exactamente al JAR publicado.
$jar = dirname(__DIR__, 3).'/resources/bin/hubdigital-pdf-signature.jar';
$socketPath = '/run/hubdigital-signature/firmador.sock';
for ($attempt = 0; $attempt < 30; $attempt++) {
    $socket = @stream_socket_client('unix://'.$socketPath, $code, $error, 0.2);
    if ($socket !== false) {
        try {
            stream_set_timeout($socket, 2);
            fwrite($socket, pack('N', 6).'health');
            $read = static function (int $length) use ($socket): string {
                $value = '';
                while (strlen($value) < $length) {
                    $part = fread($socket, $length - strlen($value));
                    if ($part === false || $part === '') throw new RuntimeException('Firmador sin respuesta.');
                    $value .= $part;
                }

                return $value;
            };
            $length = unpack('N', $read(4))[1];
            if ($length < 1 || $length > 65536) throw new RuntimeException('Dictamen de salud inválido.');
            $result = json_decode($read($length), true, flags: JSON_THROW_ON_ERROR);
            if (($result['status'] ?? '') !== 'ready' || ! hash_equals(hash_file('sha256', $jar), $result['jar_sha256'] ?? '')
                || (fileperms($socketPath) & 0777) !== 0600) {
                throw new RuntimeException('El firmador preparado no corresponde al artefacto publicado o su socket no es privado.');
            }
            echo "OK firma PDF: JVM preparada, socket privado y JAR de esta release.\n";
            exit(0);
        } catch (Throwable $error) {
            // Un arranque en curso admite espera; ningún dato sensible forma parte del diagnóstico.
        } finally {
            fclose($socket);
        }
    }
    sleep(1);
}
fwrite(STDERR, "NO OK firma PDF: no se dispone del servicio preparado de esta release.\n");
exit(1);
