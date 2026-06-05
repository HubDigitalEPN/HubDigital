<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ExportarDarwinCoreArchive;

/**
 * Resultado del exportador DwC-A. Los archivos vienen como strings en memoria
 * para que el caller decida si escribir a disco, comprimir en ZIP, o subir a
 * un endpoint. Esto mantiene el handler libre de side-effects de filesystem.
 */
final readonly class ExportarDarwinCoreArchiveOutput
{
    /**
     * @param  array<string, int>  $motivosExclusion  Diccionario motivo → conteo.
     */
    public function __construct(
        public string $metaXml,
        public string $emlXml,
        public string $occurrenceTxt,
        public string $taxonTxt,
        public int $totalEspecimenes,
        public int $publicados,
        public int $excluidos,
        public array $motivosExclusion,
    ) {}

    public function resumenLinea(): string
    {
        return "DwC-A: {$this->publicados} publicables / {$this->totalEspecimenes} totales ".
            "({$this->excluidos} excluidos)";
    }
}
