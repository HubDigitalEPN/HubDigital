<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Adapters;

use Illuminate\Support\Facades\Log;
use Modules\GestionPrestamosRecepciones\Application\Ports\ValidacionFirmaElectronicaPort;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ResultadoValidacionFirma;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

final class PdfsigValidacionFirmaElectronicaAdapter implements ValidacionFirmaElectronicaPort
{
    public function verificarFirma(string $rutaAbsoluta): ResultadoValidacionFirma
    {
        if (! file_exists($rutaAbsoluta)) {
            Log::warning('ValidacionFirma: archivo no encontrado', ['archivo' => $rutaAbsoluta]);

            return ResultadoValidacionFirma::NoVerificado;
        }

        try {
            // En Linux (producción) pdfsig vive en /usr/bin/pdfsig.
            // En macOS (desarrollo, Homebrew) puede estar en /opt/homebrew/bin o
            // /usr/local/bin. ExecutableFinder busca en PATH cuando está disponible
            // y cae al default de Linux cuando el worker de Supervisor no hereda PATH.
            $binary = (new ExecutableFinder)->find('pdfsig', '/usr/bin/pdfsig');

            $process = new Process([$binary, $rutaAbsoluta]);
            // HOME debe pasarse explícitamente: Supervisor no hereda variables de
            // entorno al worker. Sin HOME, pdfsig no localiza el NSS DB en
            // $HOME/.pki/nssdb y termina con SIGABRT.
            $process->setEnv(['LANG' => 'C', 'HOME' => posix_getpwuid(posix_getuid())['dir']]);
            $process->setTimeout(15);
            $process->run();

            // pdfsig usa exit code 2 para "sin firmas" y 0 para "con firmas".
            // Se analiza el output primero porque el exit code no distingue
            // entre "sin firmas" y errores reales.
            $output = $process->getOutput().$process->getErrorOutput();

            if (str_contains($output, 'does not contain any signatures')) {
                return ResultadoValidacionFirma::SinFirma;
            }

            if (str_contains($output, 'Signature #')) {
                return ResultadoValidacionFirma::Firmado;
            }

            // Si no hay patrones conocidos y el proceso falló, es un error real.
            if (! $process->isSuccessful()) {
                Log::warning('ValidacionFirma: pdfsig terminó con error', [
                    'archivo' => $rutaAbsoluta,
                    'exitCode' => $process->getExitCode(),
                    'output' => $output,
                ]);

                return ResultadoValidacionFirma::NoVerificado;
            }

            return ResultadoValidacionFirma::SinFirma;
        } catch (\Throwable $e) {
            Log::warning('ValidacionFirma: error al verificar firma', [
                'archivo' => $rutaAbsoluta,
                'error' => $e->getMessage(),
            ]);

            return ResultadoValidacionFirma::NoVerificado;
        }
    }
}
