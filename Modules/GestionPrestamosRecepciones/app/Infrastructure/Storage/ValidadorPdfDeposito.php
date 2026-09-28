<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Storage;

use Symfony\Component\Process\Process;

/** Delega exclusivamente a Java la inspección de PDF antes de almacenarlos. */
final class ValidadorPdfDeposito
{
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
        try {
            $inspector = new Process([
                (string) config('firma-electronica.java_binary', 'java'),
                '-Djava.awt.headless=true', '-Xmx384m',
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
        } catch (\Throwable $error) {
            throw new \InvalidArgumentException('No se pudo ejecutar el inspector Java de PDF.', previous: $error);
        }
        if (! $inspector->isSuccessful() || ! is_array($resultado) || ($resultado['status'] ?? null) !== 'seguro') {
            throw new \InvalidArgumentException((string) ($resultado['reason'] ?? 'Java no pudo inspeccionar la estructura del PDF.'));
        }

        return $resultado;
    }
}
