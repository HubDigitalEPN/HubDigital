<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services;

/** Clasifica únicamente nombres explícitos de la fuente y referencias territoriales conocidas. */
final class DesgloseLocalidad
{
    public static function unir(?string $area, ?string $territorio, ?string $sector): ?string
    {
        $partes = [];
        foreach ([$area, $territorio, $sector] as $parte) {
            $parte = trim($parte ?? '');
            if ($parte !== '') $partes[self::clave($parte)] ??= $parte;
        }
        return $partes === [] ? null : implode(', ', array_values($partes));
    }

    /**
     * @param array<string, string> $territorios Clave normalizada → nombre de cantón/parroquia en la provincia.
     * @return array{localidad:?string, localidad2:?string, localidad3:?string, requiere_revision:bool}
     */
    public static function desde(string $texto, ?string $municipio = null, ?string $parroquia = null, array $territorios = [], array $contexto = []): array
    {
        $areas = $sectores = [];
        $territorio = null;
        $contexto = array_map(self::clave(...), array_filter($contexto, is_string(...)));
        foreach (preg_split('/\s*,\s*/u', trim($texto)) ?: [] as $parte) {
            $parte = trim($parte);
            if ($parte === '') continue;
            $clave = self::clave($parte);
            if (preg_match('/^(parque nacional|reserva\b|bosque protector\b|refugio de vida silvestre\b|area (?:protegida|nacional de recreacion)\b)/u', $clave)) {
                $areas[$clave] ??= $parte;
            } elseif (isset($territorios[$clave])) {
                // La última referencia explícita suele ser la parroquia más específica.
                $territorio = $territorios[$clave];
            } elseif (in_array($clave, $contexto, true)) {
                continue;
            } else {
                $sectores[$clave] ??= $parte;
            }
        }
        $territorio ??= $parroquia ?: $municipio;
        // Varios nombres territoriales son información que debe conservarse.
        foreach (preg_split('/\s*,\s*/u', trim($texto)) ?: [] as $parte) {
            $clave = self::clave(trim($parte));
            if (isset($territorios[$clave]) && $clave !== self::clave($territorio ?? '')) $sectores[$clave] ??= trim($parte);
        }
        // La localidad y el cantón son opcionales: su ausencia no vuelve ambiguo
        // un área o sector explícito. Revisar cuando compiten varias áreas o no
        // hay referencia territorial para resolver varios nombres de lugar.
        $resultado = [
            'localidad' => $areas === [] ? null : implode(', ', array_values($areas)),
            'localidad2' => trim($territorio ?? '') ?: null,
            'localidad3' => $sectores === [] ? null : implode(', ', array_values($sectores)),
            'requiere_revision' => count($areas) > 1 || ($territorio === null && count($sectores) > 1),
        ];
        // Una fuente larga debe conservarse en verbatim, sin truncar ni perder la fila al importar.
        if (mb_strlen($resultado['localidad'] ?? '') > 255 || mb_strlen($resultado['localidad2'] ?? '') > 255
            || mb_strlen($resultado['localidad3'] ?? '') > 500) {
            return ['localidad' => null, 'localidad2' => null, 'localidad3' => null, 'requiere_revision' => true];
        }
        return $resultado;
    }

    public static function clave(string $texto): string
    {
        return trim(preg_replace('/\s+/u', ' ', strtr(mb_strtolower($texto), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ])) ?? '');
    }
}
