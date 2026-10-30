<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class DisenoLaboratorio
{
    public static function elementos(array $pagina): array
    {
        $elementos = [];
        foreach ($pagina['textos'] as $clave => $texto) {
            if (in_array($clave, ['personal_destacado', 'personal_titulo', 'contacto_email', 'adrian_especialidad'], true)) continue;
            $tag = $clave === 'titulo' ? 'h1' : (str_starts_with($clave, 'rama_') ? 'h2' : (str_ends_with($clave, '_nombre') || str_ends_with($clave, '_titulo') ? 'h3' : 'p'));
            $elementos['texto-'.$clave] = ['tipo' => $clave === 'contacto_accion' ? 'enlace' : 'texto', 'texto' => $texto, 'tag' => $tag, 'etiqueta' => ucfirst(str_replace('_', ' ', $clave))];
        }
        $elementos['texto-personal'] = ['tipo' => 'texto', 'texto' => $pagina['textos']['personal_destacado'].' '.$pagina['textos']['personal_titulo'], 'tag' => 'h2', 'etiqueta' => 'Título del equipo'];
        foreach ($pagina['fotos'] as $clave => $foto) $elementos['foto-'.$clave] = ['tipo' => 'foto', 'clave' => $clave, 'foto' => $foto, 'etiqueta' => $foto['etiqueta'] ?? $foto['alt'] ?? $clave];
        foreach ($pagina['bloques'] as $bloque) {
            $id = 'bloque-'.$bloque['id'];
            if (filled($bloque['titulo'])) $elementos['texto-'.$id.'-titulo'] = ['tipo' => 'texto', 'texto' => $bloque['titulo'], 'tag' => 'h3', 'etiqueta' => $bloque['titulo']];
            if (filled($bloque['texto'])) $elementos['texto-'.$id.'-texto'] = ['tipo' => 'texto', 'texto' => $bloque['texto'], 'tag' => 'p', 'etiqueta' => 'Texto: '.($bloque['titulo'] ?: 'bloque adicional')];
            if ($bloque['foto']['ruta']) $elementos['foto-'.$id] = ['tipo' => 'foto', 'clave' => $id, 'foto' => $bloque['foto'], 'etiqueta' => $bloque['alt']];
        }
        // Las ramas nuevas conservan juntos título, fotografía y perfil al
        // incorporarse al editor libre de una página con posiciones guardadas.
        foreach (config('pagina-laboratorio.ramas', []) as $rama => $datos) {
            $ids = ['texto-rama_'.$rama.'_titulo'];
            foreach ($datos['personas'] as $persona) array_push($ids, 'foto-'.$persona, 'texto-'.$persona.'_nombre', 'texto-'.$persona.'_bio');
            foreach ($ids as $id) { $elemento = $elementos[$id]; unset($elementos[$id]); $elementos[$id] = $elemento; }
        }
        return $elementos;
    }

    public static function validar(array $posiciones, array $pagina): array
    {
        Validator::make(['posiciones' => $posiciones], [
            'posiciones' => ['array', 'max:150'], 'posiciones.*' => ['array:x,y,ancho,alto,fuente,capa'],
            'posiciones.*.x' => ['required', 'numeric', 'between:0,95'], 'posiciones.*.y' => ['required', 'numeric', 'between:0,5000'],
            'posiciones.*.ancho' => ['required', 'numeric', 'between:5,100'], 'posiciones.*.alto' => ['required', 'numeric', 'between:1,500'],
            'posiciones.*.fuente' => ['required', 'numeric', 'between:0.5,8'], 'posiciones.*.capa' => ['required', 'integer', 'between:0,20'],
        ])->validate();
        $elementos = self::elementos($pagina);
        $validas = [];
        foreach ($posiciones as $id => $posicion) {
            if (!isset($elementos[$id])) continue; // Un bloque eliminado no conserva geometría huérfana.
            if ((float) $posicion['x'] + (float) $posicion['ancho'] > 100.01) {
                throw ValidationException::withMessages(['posiciones' => 'Un elemento sale del ancho de la página. Reduce su ancho o muévelo hacia la izquierda.']);
            }
            $validas[$id] = array_map(fn ($valor) => round((float) $valor, 3), $posicion);
            $validas[$id]['capa'] = (int) $posicion['capa'];
        }
        return $validas;
    }

    public static function disponer(array $pagina): array
    {
        $elementos = self::elementos($pagina);
        $posiciones = $pagina['posiciones'];
        $siguiente = 0;
        foreach ($posiciones as $p) $siguiente = max($siguiente, $p['y'] + $p['alto'] + 3);
        foreach ($elementos as $id => &$elemento) {
            $p = $posiciones[$id] ?? ['x' => 4, 'y' => $siguiente, 'ancho' => 92, 'alto' => $elemento['tipo'] === 'foto' ? 25 : 8, 'fuente' => ($elemento['tag'] ?? 'p') === 'p' ? 1.25 : 2, 'capa' => 0];
            if (!isset($posiciones[$id])) $siguiente += $p['alto'] + 3;
            $elemento['posicion'] = $p;
        }
        unset($elemento);
        uasort($elementos, fn ($a, $b) => [$a['posicion']['y'], $a['posicion']['x']] <=> [$b['posicion']['y'], $b['posicion']['x']]);
        return $elementos;
    }
}
