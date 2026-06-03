<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects;

enum TipoPermiso: string
{
    case Research = 'research';
    case Transport = 'transport';
    case ExportImport = 'export_import';

    public static function desdeValorFlexible(string $valor): ?self
    {
        $normalizado = mb_strtolower(trim($valor));

        return match ($normalizado) {
            'research', 'investigacion', 'investigación' => self::Research,
            'transport', 'transporte' => self::Transport,
            'export_import', 'export/import', 'exportacion_importacion', 'exportación_importación' => self::ExportImport,
            default => self::tryFrom($normalizado),
        };
    }

    /** @return string[] */
    public static function valoresAceptados(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function equals(self $other): bool
    {
        return $this === $other;
    }
}
