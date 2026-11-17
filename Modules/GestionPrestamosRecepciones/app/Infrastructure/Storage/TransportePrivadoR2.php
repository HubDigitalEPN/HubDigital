<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Storage;

/** El socket es una optimización privada, nunca un requisito para alcanzar R2. */
class TransportePrivadoR2
{
    public function socket(string $host, string $configurado): ?string
    {
        if (PHP_OS_FAMILY !== 'Linux' || $configurado !== '/run/hubdigital/r2-transport/r2.sock') return null;
        $metadatos = dirname($configurado).'/origin-host';
        return ! is_link($configurado) && @filetype($configurado) === 'socket' && (@fileperms($configurado) & 0777) === 0600
            && ! is_link($metadatos) && @fileowner($metadatos) === 0
            && trim((string) @file_get_contents($metadatos)) === $host ? $configurado : null;
    }
}
