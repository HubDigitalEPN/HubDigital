<?php

declare(strict_types=1);

namespace App\Support;

final class PlantillasLaboratorio
{
    private static ?array $referencia = null;

    public static function referencia(): array
    {
        return self::$referencia ??= json_decode(file_get_contents(
            dirname(__DIR__, 2).'/Modules/InventarioGestionColeccion/database/data/plantillas-laboratorio.json'
        ), true, 512, JSON_THROW_ON_ERROR);
    }

    public static function catalogo(array $actual, array $base): array
    {
        $referencia = array_column(self::referencia()['catalogo'], null, 'campo');
        $originales = array_column($base['columnas'], null, 'campo');

        foreach ($actual['columnas'] as &$columna) {
            $campo = $columna['campo'];
            $original = $originales[$campo] ?? null;
            if ($original === null) {
                continue;
            }
            if (! isset($referencia[$campo])) {
                // PostgreSQL puede devolver las propiedades del JSON en otro orden.
                if ($columna == $original) {
                    $columna['plantilla_catalogo'] = false;
                }
                continue;
            }
            $nueva = $referencia[$campo];
            self::actualizarCabecera($columna, $original, $nueva['cabecera']);
            $columna['requisito_guia'] ??= $nueva['requisito_guia'];
            if ($columna['descripcion'] === $original['descripcion'] && isset($nueva['descripcion'])) {
                $columna['descripcion'] = $nueva['descripcion'];
            }
        }

        return $actual;
    }

    public static function depositantes(?array $actual, array $base, array $cortaAnterior): array
    {
        $sinConfiguracion = $actual === null;
        $actual ??= $base;
        $referencia = self::referencia();
        $general = array_column($referencia['catalogo'], null, 'campo');
        $corta = array_column($referencia['depositantes'], null, 'campo');
        $originales = array_column($base['columnas'], null, 'campo');
        $cortaAnterior = array_column($cortaAnterior['columnas'], null, 'campo');
        $ordenOriginal = array_keys($originales) === array_column($actual['columnas'], 'campo');

        foreach ($actual['columnas'] as &$columna) {
            $campo = $columna['campo'];
            if (! isset($originales[$campo], $general[$campo])) {
                continue;
            }
            $original = $originales[$campo];
            $incluidaAntes = isset($cortaAnterior[$campo]);
            $requisitoAnterior = $cortaAnterior[$campo]['requisito'] ?? $original['requisito'];
            if ($sinConfiguracion) {
                $columna['requisito'] = $requisitoAnterior;
            }
            if (($columna['plantilla_depositante'] ?? $incluidaAntes) === $incluidaAntes) {
                $columna['plantilla_depositante'] = isset($corta[$campo]) || $columna['requisito'] === 'obligatorio';
            }
            self::actualizarCabecera($columna, $original, $general[$campo]['cabecera']);
            $nueva = $corta[$campo] ?? [];
            $guiaAnterior = $cortaAnterior[$campo]['descripcion'] ?? $original['descripcion'];
            $actual['descripciones_guia'][$campo] = array_values(array_unique(array_filter([
                ...($actual['descripciones_guia'][$campo] ?? []),
                $columna['descripcion'], $original['descripcion'], $guiaAnterior,
                $general[$campo]['descripcion'] ?? '', $nueva['descripcion'] ?? '',
            ])));
            if (isset($nueva['descripcion']) && in_array($columna['descripcion'], [$original['descripcion'], $guiaAnterior], true)) {
                $columna['descripcion'] = $nueva['descripcion'];
            }

            // La guía de los Excel nuevos puede pedir datos adicionales sin invalidar archivos históricos.
            $guia = $nueva['requisito_guia'] ?? $columna['requisito'];
            if ($guia !== $columna['requisito'] && in_array($columna['requisito'], [$requisitoAnterior, $original['requisito']], true)) {
                $columna['requisito_guia'] ??= $guia;
            }
        }
        unset($columna);

        if ($ordenOriginal) {
            $columnas = array_column($actual['columnas'], null, 'campo');
            $actual['columnas'] = array_values(array_replace(array_intersect_key($corta, $columnas), $columnas));
        }
        $actual['etiquetas_guia'] = array_values(array_unique([
            ...$actual['etiquetas_guia'], ...$referencia['etiquetas_guia_depositantes'],
        ]));
        $actual['version_plantilla_depositante'] = 'depositantes-v4';

        return $actual;
    }

    private static function actualizarCabecera(array &$columna, array $original, string $cabecera): void
    {
        if ($columna['cabecera'] === $cabecera) {
            return;
        }
        if ($columna['cabecera'] === $original['cabecera']) {
            $alias = $columna['cabecera'];
            $columna['cabecera'] = $cabecera;
        } else {
            $alias = $cabecera;
        }
        $columna['alias'] = array_values(array_unique([...($columna['alias'] ?? []), $alias]));
    }
}
