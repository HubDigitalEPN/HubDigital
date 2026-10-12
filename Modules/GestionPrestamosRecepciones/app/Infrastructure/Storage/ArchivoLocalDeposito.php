<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Storage;

/** Ruta local de trabajo; elimina automaticamente las copias temporales remotas. */
final class ArchivoLocalDeposito
{
    public function __construct(
        private readonly string $ruta,
        private readonly bool $temporal,
        private readonly ?string $directorioTemporal = null,
        public readonly ?array $dictamenFirma = null,
    ) {}

    public function ruta(): string
    {
        return $this->ruta;
    }

    public function limpiar(): void
    {
        if ($this->temporal && is_file($this->ruta)) {
            @unlink($this->ruta);
        }
        if ($this->temporal && is_string($this->directorioTemporal)) {
            DirectorioTemporalHubDigital::eliminar($this->directorioTemporal);
        }
    }

    public function __destruct()
    {
        $this->limpiar();
    }
}
