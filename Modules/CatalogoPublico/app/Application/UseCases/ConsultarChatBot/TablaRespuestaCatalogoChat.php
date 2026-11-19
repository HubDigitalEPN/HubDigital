<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot;

/** Presenta únicamente valores públicos ya calculados; no interpreta SQL ni inventa filas. */
final class TablaRespuestaCatalogoChat
{
    public function aplicar(array $respuesta): array
    {
        if (isset($respuesta['datos']['total']) && is_numeric($respuesta['datos']['total']) && (int) $respuesta['datos']['total'] === 0) {
            unset($respuesta['tabla']);
            return $respuesta;
        }
        if (isset($respuesta['tabla'])) {
            if (empty($respuesta['tabla']['filas'])) unset($respuesta['tabla']);
            return $respuesta;
        }
        if (($respuesta['intent'] ?? null) === 'portal.mapa_ayuda' && isset($respuesta['datos']['con_coordenadas']) && $respuesta['datos']['total'] > 1) {
            $respuesta['tabla'] = ['titulo' => 'Registros de la selección', 'columnas' => ['nombre' => 'Dato', 'cantidad' => 'Registros'],
                'filas' => [['nombre' => 'Publicados', 'cantidad' => $respuesta['datos']['total']], ['nombre' => 'Con coordenadas públicas válidas', 'cantidad' => $respuesta['datos']['con_coordenadas']]]];
            return $respuesta;
        }
        if (($respuesta['fuente'] ?? null) !== 'catalogo') return $respuesta;
        $datos = $respuesta['datos'] ?? [];
        $filas = $datos['filas'] ?? $datos['rows'] ?? [];
        if (isset($datos['conteos']) && is_array($datos['conteos'])) {
            $filas = [];
            foreach (['registros' => 'Registros', 'especies' => 'Especies', 'generos' => 'Géneros', 'familias' => 'Familias'] as $clave => $nombre) {
                if (isset($datos['conteos'][$clave]) && is_numeric($datos['conteos'][$clave])) $filas[] = ['unidad' => $nombre, 'cantidad' => $datos['conteos'][$clave]];
            }
        }
        if (! is_array($filas)) return $respuesta;
        $total = $datos['total'] ?? count($filas);
        if (count($filas) <= 1 && (! is_numeric($total) || $total <= 1)) return $respuesta;
        $etiquetas = ['nombre' => 'Nombre', 'rango' => 'Rango', 'cantidad' => 'Cantidad', 'registros' => 'Registros', 'unidad' => 'Unidad', 'codigo' => 'Código', 'nombre_cientifico' => 'Identificación'];
        $columnas = [];
        $publicas = [];
        foreach (array_slice($filas, 0, 10) as $fila) {
            if (is_string($fila) || is_int($fila) || is_float($fila)) {
                $columnas['resultado'] = 'Resultado';
                $publicas[] = ['resultado' => $fila];
            } elseif (is_array($fila)) {
                $valores = array_filter(array_intersect_key($fila, $etiquetas), static fn ($v) => is_scalar($v) || $v === null);
                if ($valores === []) continue;
                $columnas += array_intersect_key($etiquetas, $valores);
                $publicas[] = $valores;
            }
        }
        if ($publicas === [] && $filas === [] && is_numeric($total) && $total > 1) {
            $columnas = ['cantidad' => 'Cantidad'];
            $publicas = [['cantidad' => $total]];
        }
        if ($publicas !== []) $respuesta['tabla'] = ['titulo' => 'Resultados de la consulta', 'columnas' => $columnas, 'filas' => $publicas];
        return $respuesta;
    }
}
