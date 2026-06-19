<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Domain\ValueObjects;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class NombreArchivoExportacion
{
    private function __construct(
        private string $valor,
    ) {}

    public static function generar(string $especieNombre, DateTimeImmutable $fecha): self
    {
        $nombre = trim($especieNombre);

        if ($nombre === '') {
            throw new InvalidArgumentException('El nombre de la especie no puede estar vacío para generar el nombre del archivo de exportación.');
        }

        $segmento = preg_replace('/[^a-zA-Z0-9]+/', '_', $nombre);
        $segmento = trim((string) $segmento, '_');

        return new self($segmento.'_'.$fecha->format('Y-m-d').'.xlsx');
    }

    public function valor(): string
    {
        return $this->valor;
    }
}
