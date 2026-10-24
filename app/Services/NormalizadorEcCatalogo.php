<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class NormalizadorEcCatalogo
{
    public function conflictos(): array
    {
        $expresion = static fn (string $alias): string => "CASE WHEN left({$alias}.nombre_cientifico,2)='EC' THEN 'E'||substr(replace({$alias}.nombre_cientifico,'EC','ec'),2) ELSE replace({$alias}.nombre_cientifico,'EC','ec') END";
        return DB::table('taxonomia.taxones as a')->join('taxonomia.taxones as b', function ($join) use ($expresion): void {
            $join->on('a.rango', '=', 'b.rango')->whereRaw('a.id <> b.id')->whereRaw('('.$expresion('a').') = ('.$expresion('b').')');
        })->where('a.nombre_cientifico', 'like', '%EC%')->get(['a.id', 'a.nombre_cientifico', 'a.rango', 'a.padre_id', 'b.id as coincidencia_id', 'b.nombre_cientifico as coincidencia_nombre', 'b.padre_id as coincidencia_padre'])->map(static fn ($f) => (array) $f)->all();
    }
    public static function texto(string $valor): string
    {
        $nuevo = str_replace('EC', 'ec', $valor);
        // Inicio del contenido, no de cada palabra: es la excepción solicitada.
        return str_starts_with($valor, 'EC') ? 'E'.substr($nuevo, 1) : $nuevo;
    }

    /** Corrige datos del catálogo; conserva el original en un respaldo JSONL privado. */
    public function ejecutar(string $respaldo, bool $omitirConflictos = false): array
    {
        $conexion = config('database.connections.'.config('database.default'));
        if (!app()->environment('local') || ($conexion['driver'] ?? '') !== 'pgsql'
            || !in_array($conexion['host'] ?? '', ['localhost', '127.0.0.1', '::1'], true)) {
            throw new RuntimeException('La corrección de este encargo sólo está autorizada en la base local.');
        }
        $conflictos = $this->conflictos();
        if ($conflictos !== [] && !$omitirConflictos) throw new RuntimeException('Hay taxones que quedarían duplicados. Revisa el informe antes de corregirlos.');
        $idsOmitidos = array_column($conflictos, 'id');
        $archivo = fopen($respaldo, 'xb');
        if (!$archivo) throw new RuntimeException('No se pudo crear el respaldo exclusivo.');
        $escribir = static function (array $fila) use ($archivo): void {
            $linea = json_encode($fila, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)."\n";
            if (fwrite($archivo, $linea) !== strlen($linea)) throw new RuntimeException('No se pudo completar el respaldo. Se cancela la corrección.');
        };
        try {
            $resultado = DB::transaction(function () use ($escribir, $archivo, $idsOmitidos): array {
                DB::statement("SET LOCAL lock_timeout = '10s'");
                DB::statement("SET LOCAL statement_timeout = '30s'");
                $escribir(['operacion' => 'normalizar-EC', 'base' => config('database.connections.pgsql.database'), 'fecha' => now()->toIso8601String()]);
                fflush($archivo);
                $columnas = DB::select(<<<'SQL'
                    SELECT c.table_schema, c.table_name, c.column_name
                    FROM information_schema.columns c
                    JOIN information_schema.tables t USING (table_schema, table_name)
                    WHERE c.table_schema = 'taxonomia' AND t.table_type = 'BASE TABLE'
                      AND c.data_type IN ('text','character varying','character') AND c.is_generated = 'NEVER'
                      AND c.table_name NOT LIKE '%configuracion%' AND c.table_name NOT LIKE '%importacion%'
                      AND c.table_name NOT LIKE '%historial%' AND c.table_name NOT LIKE '%bitacora%'
                      AND c.table_name NOT LIKE '%edicion%' AND c.table_name NOT LIKE '%valores_columnas%'
                      AND EXISTS (SELECT 1 FROM information_schema.columns i WHERE i.table_schema = c.table_schema AND i.table_name = c.table_name AND i.column_name = 'id')
                    ORDER BY c.table_name, c.column_name
                    SQL);
                $celdas = 0; $tablas = []; $filas = [];
                foreach ($columnas as $columna) {
                    $tabla = $columna->table_schema.'.'.$columna->table_name;
                    $campo = $columna->column_name;
                    $registros = DB::table($tabla)->where($campo, 'like', '%EC%')->orderBy('id')->get(['id', $campo]);
                    foreach ($registros as $registro) {
                        if ($tabla === 'taxonomia.taxones' && $campo === 'nombre_cientifico' && in_array($registro->id, $idsOmitidos, true)) continue;
                        $anterior = (string) $registro->{$campo}; $nuevo = self::texto($anterior);
                        if ($nuevo === $anterior) continue;
                        $escribir(['tabla' => $tabla, 'id' => $registro->id, 'campo' => $campo, 'anterior' => $anterior, 'nuevo' => $nuevo]);
                        // Vaciar el buffer antes de modificar cada celda mantiene recuperable el original.
                        if (!fflush($archivo)) throw new RuntimeException('No se pudo guardar el respaldo en disco.');
                        $cambio = DB::table($tabla)->where('id', $registro->id)->where($campo, $anterior)->update([$campo => $nuevo]);
                        if ($cambio !== 1) throw new RuntimeException('Un registro cambió durante la corrección. No se aplicó el lote.');
                        $celdas++; $tablas[$tabla] = ($tablas[$tabla] ?? 0)+1; $filas[$tabla.':'.$registro->id] = true;
                    }
                }
                return ['celdas' => $celdas, 'registros' => count($filas), 'tablas' => $tablas, 'conflictos_omitidos' => count($idsOmitidos)];
            });
            $escribir(['estado' => 'confirmado', 'resumen' => $resultado]); fflush($archivo);
            return $resultado;
        } catch (\Throwable $error) {
            $escribir(['estado' => 'revertido', 'tipo' => $error::class]); fflush($archivo);
            throw $error;
        } finally { fclose($archivo); }
    }
}
