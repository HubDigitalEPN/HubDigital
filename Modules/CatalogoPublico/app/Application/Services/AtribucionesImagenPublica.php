<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\Services;

use Modules\CatalogoPublico\Domain\ValueObjects\AtribucionImagen;

final class AtribucionesImagenPublica
{
    /** @return array<string, string> */
    public static function desdeFila(object $fila): array
    {
        return ['photographer' => $fila->photographer ?? $fila->autor_nombre_completo ?? '',
            'license' => $fila->license ?? '', 'license_holder' => $fila->license_holder ?? '',
            'license_institution' => $fila->license_institution ?? '', 'contact' => $fila->contact ?? '',
            'view' => $fila->view ?? AtribucionImagen::vistaDesdeNombre($fila->nombre_original ?? '')];
    }
}
