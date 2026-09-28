<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Localidades censales del INEC y altas administradas por provincia. */
final class CatalogoLocalidadesEcuador
{
    public const TABLA = 'recepciones.localidades_ecuador_catalogo';

    public static function codigoProvincia(string $nombre): ?string
    {
        foreach (CatalogoTerritorialEcuador::provincias() as $provincia) {
            if ($provincia['nombre'] === $nombre) {
                return $provincia['codigo'];
            }
        }

        return null;
    }

    public static function consulta(string $provincia, string $busqueda = ''): Builder
    {
        $consulta = DB::table(self::TABLA)->where('provincia_codigo', self::codigoProvincia($provincia) ?? '--');
        $texto = self::normalizar($busqueda);
        foreach (array_filter(explode(' ', $texto)) as $palabra) {
            $consulta->where('busqueda', 'like', '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $palabra).'%');
        }

        return $consulta;
    }

    /** @return list<object> */
    public static function opciones(string $provincia, string $busqueda, string $seleccionada = ''): array
    {
        $filas = self::consulta($provincia, $busqueda)->where('activo', true)->selectRaw('DISTINCT ON (nombre, canton, parroquia) *')
            ->orderBy('nombre')->orderBy('canton')->orderBy('parroquia')->orderBy('codigo')->limit(60)->get();
        if ($seleccionada !== '' && ! $filas->contains('codigo', $seleccionada)) {
            $actual = self::buscar($provincia, $seleccionada);
            if ($actual !== null) {
                $filas = $filas->reject(fn (object $fila): bool => $fila->nombre === $actual->nombre
                    && $fila->canton === $actual->canton && $fila->parroquia === $actual->parroquia);
                $filas->prepend($actual);
            }
        }

        return $filas->all();
    }

    public static function buscar(string $provincia, string $codigo, bool $soloActivas = true): ?object
    {
        return self::consulta($provincia)->where('codigo', $codigo)
            ->when($soloActivas, fn (Builder $q) => $q->where('activo', true))->first();
    }

    public static function etiqueta(object $fila): string
    {
        return implode(' · ', array_filter([$fila->nombre, $fila->parroquia, $fila->canton]));
    }

    public static function normalizar(string $texto): string
    {
        return trim(preg_replace('/\s+/u', ' ', Str::lower(Str::ascii($texto))) ?? '');
    }
}
