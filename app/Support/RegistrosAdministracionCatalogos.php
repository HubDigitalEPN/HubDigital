<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\GestionPrestamosRecepciones\Application\Ports\EsquemaMatrizPort;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\ConfiguracionPlantillaCatalogo;

final class RegistrosAdministracionCatalogos
{
    public const DEPOSITOS = 'matriz-depositos';
    public const IMPORTACION = 'plantilla-catalogo';
    public const IPT = 'formato-ipt';

    public function definicion(string $matriz): array
    {
        return match ($matriz) {
            self::DEPOSITOS => app(EsquemaMatrizPort::class)->leer(),
            self::IMPORTACION => app(ConfiguracionPlantillaCatalogo::class)->leer(),
            self::IPT => app(ConfiguracionIpt::class)->leer(),
        };
    }

    public function columnas(string $matriz): array
    {
        return ['orden' => 'Orden'] + match ($matriz) {
            self::DEPOSITOS => [
                'cabecera' => 'Cabecera Excel', 'campo' => 'Campo Darwin Core',
                'alias' => 'Otros nombres', 'requisito' => 'Requisito', 'formato' => 'Formato',
                'descripcion' => 'Descripción', 'excepcion_campo' => 'Campo de la excepción',
                'excepcion_valor' => 'Valor de la excepción', 'plantilla_depositante' => 'En plantilla del depositante',
                'requisito_guia' => 'Requisito en la guía',
            ],
            self::IMPORTACION => [
                'campo' => 'Campo', 'cabecera' => 'Cabecera Excel', 'descripcion' => 'Descripción y ejemplo',
                'alias' => 'Otros nombres', 'requisito' => 'Requisito', 'formato' => 'Formato',
                'requisito_guia' => 'Requisito en la guía', 'plantilla_catalogo' => 'En plantilla general',
                'personalizada' => 'Personalizada',
            ],
            self::IPT => ['campo' => 'Cabecera IPT', 'origen' => 'Campo de origen', 'formato' => 'Formato', 'ancho' => 'Ancho'],
        };
    }

    public function fuente(string $matriz): Builder
    {
        if ($matriz === self::DEPOSITOS) {
            return DB::table('recepciones.configuracion_matriz')->where('clave', 'depositos')->select('definicion');
        }

        $tabla = 'taxonomia.configuracion_plantilla_catalogo';
        $fuente = DB::table($tabla)->where('clave', $matriz === self::IPT ? 'ipt' : 'catalogo')->select('definicion');
        if (($matriz === self::IPT || Schema::hasTable($tabla)) && (clone $fuente)->exists()) {
            return $fuente;
        }
        $predeterminada = match ($matriz) {
            self::IMPORTACION => ConfiguracionPlantillaCatalogo::predeterminada(),
            self::IPT => ConfiguracionIpt::predeterminada(),
        };

        return DB::query()->selectRaw('?::jsonb AS definicion', [json_encode($predeterminada, JSON_THROW_ON_ERROR)]);
    }

    public function consultar(string $matriz, Builder $fuente, string $busqueda): Builder
    {
        $campos = array_keys($this->columnas($matriz));
        $documento = DB::query()->fromSub($fuente, 'matriz')->selectRaw("definicion->'columnas' AS columnas");
        $registros = $this->filasProyectadas($matriz, $documento);

        if (trim($busqueda) !== '') {
            $patron = '%'.strtr(trim($busqueda), ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']).'%';
            $registros->where(function (Builder $query) use ($patron, $campos): void {
                foreach ($campos as $campo) {
                    $columna = $query->getGrammar()->wrap($campo);
                    $query->orWhereRaw("cast($columna as text) ilike ?", [$patron]);
                }
            });
        }

        return $registros;
    }

    public function cantidad(Builder $fuente): int
    {
        return (int) DB::query()->fromSub($fuente, 'matriz')
            ->selectRaw("jsonb_array_length(definicion->'columnas') AS cantidad")->value('cantidad');
    }

    public function lote(string $matriz, Builder $fuente, int $desde, int $cantidad, string $direccion): Builder
    {
        $documento = DB::query()->fromSub($fuente, 'matriz')->selectRaw(<<<'SQL'
            jsonb_path_query_array(definicion, '$.columnas[$desde to $hasta]',
                jsonb_build_object('desde', ?::integer, 'hasta', ?::integer)) AS columnas
            SQL, [$desde, $desde + $cantidad - 1]);

        return $this->filasProyectadas($matriz, $documento, $desde)->orderBy('orden', $direccion);
    }

    /** @param list<string> $campos */
    public function contieneCampos(Builder $fuente, array $campos): bool
    {
        $requeridos = array_map(static fn (string $campo): array => ['campo' => $campo], $campos);

        return DB::query()->fromSub($fuente, 'matriz')
            ->whereRaw("(definicion->'columnas') @> ?::jsonb", [json_encode($requeridos, JSON_THROW_ON_ERROR)])
            ->exists();
    }

    public function proteccionDepositos(Builder $fuente): array
    {
        $datos = DB::query()->fromSub($fuente, 'matriz')->selectRaw(<<<'SQL'
            jsonb_build_object(
                'identificacion', definicion->'identificacion',
                'obligatorias', jsonb_path_query_array(definicion, '$.columnas[*] ? (@.requisito == "obligatorio").campo'),
                'excepciones', jsonb_path_query_array(definicion, '$.columnas[*].excepcion_campo ? (@ != "" && @ != null)')
            ) AS proteccion
            SQL)->value('proteccion');
        if ($datos === null) {
            throw new \RuntimeException('La configuración de la matriz de depósito todavía no está disponible.');
        }

        $proteccion = json_decode($datos, true, 512, JSON_THROW_ON_ERROR);

        return app(EditorMatrizDepositos::class)->camposProtegidos(
            $proteccion['identificacion'] ?? [], $proteccion['obligatorias'], $proteccion['excepciones'],
        );
    }

    private function filasProyectadas(string $matriz, Builder $documento, int $offset = 0): Builder
    {
        $campos = array_diff(array_keys($this->columnas($matriz)), ['orden']);
        $etiquetas = $this->etiquetas($matriz);
        $tipos = array_map(static fn (string $campo): string => $campo.' '.match ($campo) {
            'ancho' => 'integer',
            'alias' => 'jsonb',
            'plantilla_depositante', 'plantilla_catalogo', 'personalizada' => 'boolean',
            default => 'text',
        }, $campos);
        $filas = DB::query()->fromSub($documento, 'matriz')
            ->crossJoin(DB::raw('LATERAL ROWS FROM (jsonb_to_recordset(matriz.columnas) AS ('.implode(', ', $tipos).')) WITH ORDINALITY AS columna'))
            ->selectRaw('columna.ordinality + ?::integer AS orden', [$offset]);
        foreach ($campos as $campo) {
            $this->proyectarCampo($filas, $campo, $etiquetas[$campo] ?? []);
        }

        return DB::query()->fromSub($filas, 'columnas_matriz');
    }

    private function proyectarCampo(Builder $filas, string $campo, array $etiquetas): void
    {
        $columna = 'columna.'.$campo;
        if ($campo === 'alias') {
            $filas->selectRaw("CASE WHEN $columna IS NOT NULL THEN array_to_string(ARRAY(SELECT jsonb_array_elements_text($columna)), ', ') END AS alias");
        } elseif (in_array($campo, ['plantilla_depositante', 'plantilla_catalogo', 'personalizada'], true)) {
            $valorPorDefecto = $campo === 'personalizada' ? 'false' : 'true';
            $filas->selectRaw("CASE WHEN COALESCE($columna, $valorPorDefecto) THEN ? ELSE ? END AS $campo", [$etiquetas[1], $etiquetas[0]]);
        } elseif ($etiquetas !== []) {
            $casos = str_repeat(' WHEN ? THEN ?', count($etiquetas));
            $valores = [];
            foreach ($etiquetas as $valor => $texto) {
                array_push($valores, $valor, $texto);
            }
            $filas->selectRaw("CASE $columna$casos ELSE $columna END AS $campo", $valores);
        } else {
            $filas->addSelect($columna);
        }
    }

    public function etiquetas(string $matriz): array
    {
        $requisitos = ['obligatorio' => 'Obligatorio', 'recomendado' => 'Recomendado', 'opcional' => 'Opcional'];
        $guia = $requisitos + ['interno' => 'Interno', 'predeterminado' => 'Predeterminado'];
        $formatos = ['texto' => 'Texto', 'fecha' => 'Fecha'];
        $binario = [1 => 'Sí', 0 => 'No'];

        return match ($matriz) {
            self::DEPOSITOS => ['requisito' => $guia, 'requisito_guia' => $requisitos, 'formato' => $formatos, 'plantilla_depositante' => $binario],
            self::IMPORTACION => ['requisito' => $requisitos, 'requisito_guia' => $guia, 'formato' => $formatos,
                'plantilla_catalogo' => $binario, 'personalizada' => $binario],
            self::IPT => ['formato' => ConfiguracionIpt::FORMATOS],
        };
    }
}
