<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Adapters;

use Illuminate\Support\Facades\Log;
use Modules\GestionPrestamosRecepciones\Application\Ports\ValidacionFirmaElectronicaPort;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ResultadoValidacionFirma;
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
            $process = new Process(['pdfsig', $rutaAbsoluta]);
            $process->setEnv(['LANG' => 'C']);
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
