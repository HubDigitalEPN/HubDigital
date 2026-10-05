<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Equivalencias documentadas; nunca crea entradas ni reconstruye una entidad desconocida. */
final class NormalizadorNombreCatalogo
{
    public static function desde(string $tipo, string $nombre): string
    {
        $nombre = trim(preg_replace('/\s+/u', ' ', $nombre) ?? '');
        $archivo = fopen(resource_path('data/catalogos-deposito-mepn.csv'), 'rb');
        if ($archivo === false) throw new \RuntimeException('No se pudo leer la referencia de nombres del catálogo.');
        try {
            $cabecera = fgetcsv($archivo, 0, ',', '"', '');
            while (($datos = fgetcsv($archivo, 0, ',', '"', '')) !== false) {
                if (count($datos) !== count($cabecera ?: [])) continue;
                $fila = array_combine($cabecera, $datos);
                if ($fila['tipo'] !== $tipo || self::clave($fila['valor_original']) !== self::clave($nombre)) continue;
                if ($fila['nombre_canonico'] === '') {
                    throw ValidationException::withMessages(['nombre' => 'Confirma el nombre completo de la entidad: '.$fila['evidencia']]);
                }
                return $fila['nombre_canonico'];
            }
        } finally {
            fclose($archivo);
        }
        return $nombre;
    }

    public static function clave(string $nombre): string
    {
        return Str::lower(Str::ascii(trim(preg_replace('/\s+/u', ' ', $nombre) ?? '')));
    }
}
