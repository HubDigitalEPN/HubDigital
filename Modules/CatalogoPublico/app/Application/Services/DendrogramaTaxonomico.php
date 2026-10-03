<?php

namespace Modules\CatalogoPublico\Application\Services;

/** Geometría de la jerarquía pública recibida; no infiere filogenia ni consulta la colección. */
final class DendrogramaTaxonomico
{
    private const RANGOS = [
        'kingdom' => 'Reino', 'reino' => 'Reino', 'subkingdom' => 'Subreino', 'subreino' => 'Subreino',
        'phylum' => 'Filo', 'filo' => 'Filo', 'subphylum' => 'Subfilo', 'subfilo' => 'Subfilo',
        'class' => 'Clase', 'clase' => 'Clase', 'subclass' => 'Subclase', 'subclase' => 'Subclase',
        'superclass' => 'Superclase', 'superclase' => 'Superclase', 'infraclass' => 'Infraclase', 'infraclase' => 'Infraclase',
        'order' => 'Orden', 'orden' => 'Orden', 'suborder' => 'Suborden', 'suborden' => 'Suborden',
        'superorder' => 'Superorden', 'superorden' => 'Superorden',
        'infraorder' => 'Infraorden', 'infraorden' => 'Infraorden',
        'superfamily' => 'Superfamilia', 'superfamilia' => 'Superfamilia',
        'family' => 'Familia', 'familia' => 'Familia', 'subfamily' => 'Subfamilia', 'subfamilia' => 'Subfamilia',
        'epifamily' => 'Epifamilia', 'epifamilia' => 'Epifamilia', 'supertribe' => 'Supertribu', 'supertribu' => 'Supertribu',
        'tribe' => 'Tribu', 'tribu' => 'Tribu', 'subtribe' => 'Subtribu', 'subtribu' => 'Subtribu',
        'genus' => 'Género', 'genero' => 'Género', 'género' => 'Género',
        'subgenus' => 'Subgénero', 'subgenero' => 'Subgénero', 'subgénero' => 'Subgénero',
        'species' => 'Especie', 'especie' => 'Especie', 'subspecies' => 'Subespecie', 'subespecie' => 'Subespecie',
        'variety' => 'Variedad', 'variedad' => 'Variedad', 'form' => 'Forma', 'forma' => 'Forma',
        'group' => 'Grupo', 'grupo' => 'Grupo',
    ];

    public static function etiquetaRango(string $rango): string
    {
        $clave = mb_strtolower(trim($rango));

        return self::RANGOS[$clave] ?? ($clave === '' ? 'Taxón' : mb_convert_case($rango, MB_CASE_TITLE));
    }

    /** Solo nombres de rangos públicos; los metadatos de un registro no forman parte del linaje. */
    public static function linajePublico(array $jerarquia, string $especie): array
    {
        $fuente = is_array($jerarquia['ancestros'] ?? null) ? $jerarquia['ancestros'] : $jerarquia;
        $linaje = $vistos = [];
        $incluyeEspecie = false;
        foreach ($fuente as $clave => $dato) {
            $rango = is_array($dato) ? ($dato['rango'] ?? $dato['nivel'] ?? null) : $clave;
            $nombre = is_array($dato) ? ($dato['nombre'] ?? $dato['taxon'] ?? null) : $dato;
            if (! is_string($rango) || ! is_string($nombre) || trim($nombre) === '') continue;
            $etiqueta = self::RANGOS[mb_strtolower(trim($rango))] ?? null;
            if ($etiqueta === null || ($etiqueta === 'Especie' && trim($nombre) !== trim($especie))) continue;
            $nombre = trim($nombre);
            $identidad = $etiqueta."\0".$nombre;
            if (isset($vistos[$identidad])) continue;
            $vistos[$identidad] = true;
            $linaje[] = ['rango' => $etiqueta, 'nombre' => $nombre];
            $incluyeEspecie = $incluyeEspecie || $etiqueta === 'Especie';
        }
        if (! $incluyeEspecie) $linaje[] = ['rango' => 'Especie', 'nombre' => $especie];

        return $linaje;
    }

    public static function calcular(array $arbol, ?string $seleccionado = null): array
    {
        $nodos = [];
        foreach ($arbol as $nodo) {
            if (! is_array($nodo) || (! is_string($nodo['id'] ?? null) && ! is_int($nodo['id'] ?? null))) {
                continue;
            }
            $id = (string) $nodo['id'];
            if ($id === '' || isset($nodos[$id])) {
                continue;
            }
            $padre = is_string($nodo['padre_id'] ?? null) || is_int($nodo['padre_id'] ?? null) ? (string) $nodo['padre_id'] : null;
            $nodos[$id] = array_merge($nodo, [
                'id' => $id, 'padre_id' => $padre,
                'nombre' => (string) ($nodo['nombre'] ?? ''), 'rango' => (string) ($nodo['rango'] ?? ''),
                'total' => max(0, (int) ($nodo['total'] ?? 0)),
            ]);
        }
        // Un padre no publicado nunca se reconstruye a partir del nombre o de un UUID.
        foreach ($nodos as &$nodo) {
            if (! isset($nodos[$nodo['padre_id'] ?? ''])) {
                $nodo['padre_id'] = null;
            }
        }
        unset($nodo);
        // Evitar ciclos en un payload inválido sin fabricar una relación entre sus miembros.
        foreach (array_keys($nodos) as $id) {
            $ruta = $indices = [];
            $cursor = $id;
            while ($cursor !== null) {
                if (isset($indices[$cursor])) {
                    foreach (array_slice($ruta, $indices[$cursor]) as $idCiclico) {
                        $nodos[$idCiclico]['padre_id'] = null;
                    }
                    break;
                }
                $indices[$cursor] = count($ruta);
                $ruta[] = $cursor;
                $cursor = $nodos[$cursor]['padre_id'];
            }
        }
        $hijos = $raices = [];
        foreach ($nodos as $id => $nodo) {
            if ($nodo['padre_id'] === null) {
                $raices[] = (string) $id;
            } else {
                $hijos[$nodo['padre_id']][] = (string) $id;
            }
        }
        $profundidadMaxima = 0;
        foreach ($nodos as $nodo) {
            $profundidad = 0;
            $cursor = $nodo['padre_id'];
            while ($cursor !== null) {
                $profundidad++;
                $cursor = $nodos[$cursor]['padre_id'];
            }
            $profundidadMaxima = max($profundidadMaxima, $profundidad);
        }
        // Cada rango tiene su propia columna y todas las hojas terminan a la derecha.
        $pasoHorizontal = 148;
        $ancho = max(960, 24 + $profundidadMaxima * $pasoHorizontal + 236 + 24);
        $xHojas = $ancho - 260;
        $linajeSeleccionado = [];
        $cursor = $seleccionado;
        while ($cursor !== null && isset($nodos[$cursor])) {
            $linajeSeleccionado[$cursor] = true;
            $cursor = $nodos[$cursor]['padre_id'];
        }
        $posiciones = [];
        $y = 12;
        $visitar = function (string $id, int $profundidad) use (&$visitar, &$posiciones, &$y, $nodos, $hijos, $linajeSeleccionado, $seleccionado, $pasoHorizontal, $xHojas): float {
            $nodo = $nodos[$id];
            $hoja = ($hijos[$id] ?? []) === [];
            $alto = 64;
            // Reservar el lugar del padre conserva el orden de tabulación de la jerarquía.
            $posiciones[$id] = [];
            $centros = [];
            foreach ($hijos[$id] ?? [] as $hijo) {
                $centros[] = $visitar($hijo, $profundidad + 1);
            }
            $centro = (float) ($hoja ? $y + $alto / 2 : (min($centros) + max($centros)) / 2);
            if ($hoja) $y += 76;
            $posiciones[$id] = array_merge($nodo, [
                'x' => $hoja ? $xHojas : 24 + $profundidad * $pasoHorizontal, 'y' => $centro,
                'superior' => $centro - $alto / 2, 'alto' => $alto, 'ancho_nodo' => $hoja ? 236 : 132, 'profundidad' => $profundidad,
                'etiqueta' => self::etiquetaRango($nodo['rango']), 'hoja' => $hoja,
                'mostrar_total' => in_array(mb_strtolower(trim($nodo['rango'])), ['species', 'especie'], true),
                'padre_nombre' => $nodos[$nodo['padre_id'] ?? '']['nombre'] ?? null,
                'seleccionado' => $id === $seleccionado, 'en_linaje' => isset($linajeSeleccionado[$id]),
                'miniatura' => null,
            ]);

            return $centro;
        };
        foreach ($raices as $id) {
            $visitar($id, 0);
        }
        $ramas = [];
        foreach ($posiciones as $id => $nodo) {
            $padre = $posiciones[$nodo['padre_id'] ?? ''] ?? null;
            if ($padre === null) {
                continue;
            }
            $salida = $padre['x'] + $padre['ancho_nodo'];
            $codo = round(($salida + $nodo['x']) / 2, 2);
            $ramas[] = [
                'padre_id' => $padre['id'], 'hijo_id' => (string) $id,
                'trazo' => 'M'.$salida.' '.$padre['y'].' H'.$codo.' V'.$nodo['y'].' H'.$nodo['x'],
                'activa' => $nodo['en_linaje'], 'hoja' => $nodo['hoja'],
            ];
        }

        return ['ancho' => $ancho, 'alto' => max(100, $y + 12), 'nodos' => array_values($posiciones), 'ramas' => $ramas];
    }
}
