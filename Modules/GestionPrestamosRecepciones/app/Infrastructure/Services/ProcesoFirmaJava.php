<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Services;

use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

/** Transporte persistente al motor Java, sin interpretar ni validar firmas en PHP. */
final class ProcesoFirmaJava
{
    private static ?Process $proceso = null;

    private static ?InputStream $entrada = null;

    private static string $identidad = '';

    private static int $peticiones = 0;

    private static bool $ocupado = false;

    /** @param list<string> $comando @param array<string, string> $entorno @return array<string, mixed> */
    public static function ejecutar(array $comando, array $entorno, string $rutas, int $timeout): array
    {
        if (self::$ocupado) {
            throw new \RuntimeException('El transporte Java ya está atendiendo otra petición.');
        }
        self::$ocupado = true;
        try {
            $jar = $comando[count($comando) - 3];
            clearstatcache(true, $jar);
            $confianza = [];
            foreach (glob(($entorno['HUBDIGITAL_SIGNATURE_TRUST_DIR'] ?? '').'/*.{crt,cer,pem}', GLOB_BRACE) ?: [] as $certificado) {
                clearstatcache(true, $certificado);
                $confianza[$certificado] = [filemtime($certificado), filesize($certificado)];
            }
            $identidad = hash('sha256', serialize([$comando, $entorno, filemtime($jar), filesize($jar), $confianza]));
            if (self::$proceso === null || self::$identidad !== $identidad
                || self::$peticiones >= 128 || ! self::$proceso->isRunning()) {
                self::cerrar();
                $continuo = array_slice($comando, 0, -2);
                $continuo[] = 'verify-stream';
                self::$entrada = new InputStream;
                self::$proceso = new Process($continuo);
                self::$proceso->setInput(self::$entrada);
                self::$proceso->setEnv($entorno);
                self::$proceso->setTimeout(null);
                self::$proceso->start();
                self::$identidad = $identidad;
            }
            self::$entrada->write(base64_encode($rutas)."\n");
            self::$peticiones++;
            $salida = '';
            $limite = microtime(true) + max(1, $timeout);
            do {
                $salida .= self::$proceso->getIncrementalOutput();
                if (strlen($salida) > 2_000_000) {
                    throw new \RuntimeException('La respuesta del motor Java excede el límite.');
                }
                if (($fin = strpos($salida, "\n")) !== false) {
                    if (trim(substr($salida, $fin + 1)) !== '') {
                        throw new \RuntimeException('Java devolvió más de un dictamen para la petición.');
                    }
                    $resultado = json_decode(substr($salida, 0, $fin), true, 512, JSON_THROW_ON_ERROR);
                    if (! is_array($resultado) || ! is_string($resultado['status'] ?? null)) {
                        throw new \RuntimeException('Java no devolvió un dictamen de firma.');
                    }
                    self::$proceso->clearOutput();
                    self::$proceso->clearErrorOutput();

                    return $resultado;
                }
                if (! self::$proceso->isRunning()) {
                    throw new \RuntimeException('El motor Java terminó antes de devolver su dictamen.');
                }
                usleep(10_000);
            } while (microtime(true) < $limite);

            throw new \RuntimeException('El motor Java excedió el tiempo de validación.');
        } catch (\Throwable $error) {
            self::cerrar();
            throw $error;
        } finally {
            self::$ocupado = false;
        }
    }

    private static function cerrar(): void
    {
        self::$entrada?->close();
        self::$proceso?->stop(0);
        self::$proceso = null;
        self::$entrada = null;
        self::$identidad = '';
        self::$peticiones = 0;
    }
}
