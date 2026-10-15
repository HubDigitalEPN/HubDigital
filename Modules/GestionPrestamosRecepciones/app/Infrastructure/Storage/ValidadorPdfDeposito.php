<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Storage;

use Symfony\Component\Process\Process;
use Modules\GestionPrestamosRecepciones\Infrastructure\Services\ClienteFirmadorPreparado;

/** Delega exclusivamente a Java la inspección de PDF antes de almacenarlos. */
final class ValidadorPdfDeposito
{
    /** Sólo dictámenes Java vinculados a los mismos bytes y a la misma release. */
    private static array $inspecciones = [];
    public function validar(string $ruta): void
    {
        $this->inspeccionar($ruta);
    }

    /** @param array<string, int> $limites @return array<string, mixed> */
    public function inspeccionar(string $ruta, array $limites = []): array
    {
        $jar = (string) config('firma-electronica.java_signature_jar');
        if (! is_file($jar)) {
            throw new \InvalidArgumentException('No está disponible el inspector Java de seguridad de PDF.');
        }
        $huella = is_file($ruta) ? hash_file('sha256', $ruta) : false;
        $motor = hash_file('sha256', $jar);
        $identidad = $huella === false ? null : $motor.':'.$huella;
        if ($identidad !== null && isset(self::$inspecciones[$identidad])) {
            return $this->exigirLimites(self::$inspecciones[$identidad], $limites);
        }
        try {
            $resultado = $limites === [] ? app(ClienteFirmadorPreparado::class)->inspeccionarExterno($ruta) : null;
            if ($resultado !== null) {
                if (($resultado['status'] ?? null) !== 'seguro') {
                    throw new \InvalidArgumentException((string) ($resultado['reason'] ?? 'Java rechazó la estructura del PDF.'));
                }
                return $this->conservar($identidad, $resultado, $limites);
            }
            $inspector = new Process([
                (string) config('firma-electronica.java_binary', 'java'),
                '-Djava.awt.headless=true', '-Xms24m', '-Xmx160m', '-XX:+UseSerialGC', '-XX:ActiveProcessorCount=2',
                '-Dhubdigital.pdf.max_pages='.($limites['max_pages'] ?? (int) config('firma-electronica.max_pages', 40)),
                '-Dhubdigital.pdf.max_page_points='.($limites['max_page_points'] ?? (int) config('firma-electronica.max_page_points', 1440)),
                '-Dhubdigital.pdf.max_render_pixels='.($limites['max_render_pixels'] ?? (int) config('firma-electronica.max_render_pixels', 100_000_000)),
                '-Dhubdigital.pdf.render_dpi='.($limites['render_dpi'] ?? (int) config('firma-electronica.render_dpi', 110)),
                '-jar', $jar, 'inspect', '--paths-stdin',
            ]);
            $inspector->setInput($ruta);
            $inspector->setEnv(DirectorioTemporalHubDigital::entornoProcesos());
            $inspector->setTimeout(20);
            $inspector->run();
            $resultado = json_decode(trim($inspector->getOutput()), true);
        } catch (\InvalidArgumentException $error) {
            throw $error;
        } catch (\Throwable $error) {
            throw new \InvalidArgumentException('No se pudo ejecutar el inspector Java de PDF.', previous: $error);
        }
        if (! $inspector->isSuccessful() || ! is_array($resultado) || ($resultado['status'] ?? null) !== 'seguro') {
            throw new \InvalidArgumentException((string) ($resultado['reason'] ?? 'Java no pudo inspeccionar la estructura del PDF.'));
        }

        return $this->conservar($identidad, $resultado, $limites);
    }

    private function conservar(?string $identidad, array $resultado, array $limites): array
    {
        $resultado = $this->exigirLimites($resultado, $limites);
        if ($identidad !== null) {
            if (count(self::$inspecciones) >= 4) array_shift(self::$inspecciones);
            self::$inspecciones[$identidad] = $resultado;
        }
        return $resultado;
    }

    private function exigirLimites(array $resultado, array $limites): array
    {
        $dpi = $limites['render_dpi'] ?? (int) config('firma-electronica.render_dpi', 110);
        if (($resultado['paginas'] ?? 0) < 1
            || $resultado['paginas'] > ($limites['max_pages'] ?? (int) config('firma-electronica.max_pages', 40))
            || ($resultado['ancho_maximo_points'] ?? INF) > ($limites['max_page_points'] ?? (int) config('firma-electronica.max_page_points', 1440))
            || ($resultado['alto_maximo_points'] ?? INF) > ($limites['max_page_points'] ?? (int) config('firma-electronica.max_page_points', 1440))
            || $dpi < 36 || $dpi > 300
            || ($resultado['area_paginas_points2'] ?? INF) * $dpi * $dpi / (72 * 72)
                > ($limites['max_render_pixels'] ?? (int) config('firma-electronica.max_render_pixels', 100_000_000))) {
            throw new \InvalidArgumentException('El PDF excede los límites de páginas, dimensiones o procesamiento gráfico.');
        }
        return $resultado;
    }
}
