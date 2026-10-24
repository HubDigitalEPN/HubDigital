<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico;

use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\ConfiguracionPlantillaCatalogo;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\FilaCatalogoMapper;

final class ColumnasCatalogo
{
    public static function campo(string $clave): string
    {
        return ['codigoCatalogo' => 'occurrenceID', 'occurrenceId' => 'occurrenceID', 'taxonNombre' => 'scientificName',
            'taxonVerbatim' => 'scientificName', 'fechaColecta' => 'eventDate', 'fechaVerbatim' => 'eventDate',
            'fechaColectaFin' => 'datecollectedEnd', 'colector' => 'recordedBy', 'localidad' => 'locality', 'localityName' => 'locality', 'localidadVerbatim' => 'verbatimLocality',
            'elevationMinM' => 'minimumElevationInMeters', 'elevationMaxM' => 'maximumElevationInMeters',
            'coordVerbatim' => 'verbatimCoordinates', 'actaRecepcion' => '#actaRecepcion', 'priorOwner' => 'prior Owner',
            'exportImportAuthorization' => 'export/importAuthorization', 'cardexLiquidCollectionCode' => 'cardexLiquidColCode',
            'typeCaste' => 'typECaste', 'endemic' => 'Endemic', 'iptUpload' => 'IPT_upload',
            'recordCreatedBy' => 'recordCreatedBy,month-year', 'responsibleResearcherExport' => 'Responsible researcher or applicant for export',
            'loanNo' => 'loanNo.', 'loanToMuseum' => 'loanTo/Museum'] [$clave] ?? $clave;
    }

    public function etiqueta(string $clave, string $defecto): string
    {
        $campo = self::campo($clave);
        foreach (app(ConfiguracionPlantillaCatalogo::class)->leer()['columnas'] as $columna) {
            if ($columna['campo'] === $campo) return $columna['cabecera'];
        }
        return $defecto;
    }

    public function presentar(array $columnas): array
    {
        $def = app(ConfiguracionPlantillaCatalogo::class)->leer();
        $porCampo = array_column($def['columnas'], null, 'campo');
        $madre = array_column(ConfiguracionPlantillaCatalogo::predeterminada()['columnas'], null, 'campo');
        $salida = [];
        foreach ($columnas as $col) {
            $campo = self::campo($col['clave']);
            if (isset($madre[$campo]) && !isset($porCampo[$campo])) continue;
            if (isset($porCampo[$campo])) $col['etiqueta'] = $porCampo[$campo]['cabecera'];
            $salida[] = $col;
        }
        foreach ($def['columnas'] as $col) if ($col['personalizada'] ?? false) {
            $salida[] = ['clave' => $col['campo'], 'etiqueta' => $col['cabecera'], 'grupo' => 'registro',
                'prioridad' => 'opcional', 'visiblePorDefecto' => true, 'nombreDwC' => null];
        }
        // La ubicación encabeza la pantalla y la ficha, sin cambiar las claves del dato.
        usort($salida, static fn ($a, $b) => ($a['grupo'] !== 'localidad') <=> ($b['grupo'] !== 'localidad'));
        return $salida;
    }

    public function personalizadas(): array
    {
        return array_values(array_filter(app(ConfiguracionPlantillaCatalogo::class)->leer()['columnas'], static fn ($c) => $c['personalizada'] ?? false));
    }

    public function completar(array $filas): array
    {
        if (!$filas || !Schema::hasTable('taxonomia.valores_columnas_especimen')) return $filas;
        $valores = DB::table('taxonomia.valores_columnas_especimen')->whereIn('especimen_id', array_column($filas, 'id'))->get()->groupBy('especimen_id');
        foreach ($filas as &$fila) foreach ($valores[$fila['id']] ?? [] as $valor) $fila[$valor->campo] = $valor->valor;
        return $filas;
    }

    public function guardarValor(string $id, string $campo, ?string $valor): void
    {
        abort_unless(auth()->user()?->esCurador(), 403);
        abort_unless(Str::isUuid($id) && DB::table('taxonomia.especimenes')->where('id', $id)->exists(), 404);
        abort_unless(in_array($campo, array_column($this->personalizadas(), 'campo'), true), 422);
        if (mb_strlen($valor ?? '') > 5000) throw new DomainException('El campo admite hasta 5000 caracteres.');
        DB::transaction(function () use ($id, $campo, $valor): void {
            // La misma fila de configuración serializa escritura y eliminación de columnas.
            $config = DB::table('taxonomia.configuracion_plantilla_catalogo')->where('clave', 'catalogo')->lockForUpdate()->first();
            $def = json_decode($config->definicion, true, 512, JSON_THROW_ON_ERROR);
            abort_unless(in_array($campo, array_column($def['columnas'], 'campo'), true), 422);
            $anterior = DB::table('taxonomia.valores_columnas_especimen')->where(['especimen_id' => $id, 'campo' => $campo])->value('valor');
            DB::table('taxonomia.valores_columnas_especimen')->updateOrInsert(['especimen_id' => $id, 'campo' => $campo], ['valor' => $valor, 'actualizado_por' => auth()->id(), 'updated_at' => now()]);
            if ($anterior !== $valor) DB::table('taxonomia.historial_columnas_especimen')->insert(['especimen_id' => $id, 'campo' => $campo, 'anterior' => $anterior, 'nuevo' => $valor, 'actualizado_por' => auth()->id(), 'created_at' => now()]);
        });
    }

    public function usada(array $columna): bool
    {
        $campo = $columna['campo'];
        if ($campo === 'occurrenceID') return true;
        if ($columna['personalizada'] ?? false) return DB::table('taxonomia.valores_columnas_especimen')->where('campo', $campo)->exists()
            || DB::table('taxonomia.historial_columnas_especimen')->where('campo', $campo)->exists();
        $normalizada = (new FilaCatalogoMapper)->normalizarClaves([$campo => 'x']);
        $nombre = array_key_first($normalizada);
        $nombre = ['scientific_name' => 'taxon_verbatim', 'event_date' => 'fecha_verbatim', 'recorded_by' => 'colector', 'acta_recepcion' => 'acta_recepcion'] [$nombre] ?? $nombre;
        $columnas = Schema::getColumnListing('taxonomia.especimenes');
        if (in_array($nombre, $columnas, true) && DB::table('taxonomia.especimenes')->whereNotNull($nombre)->whereRaw('btrim(CAST("'.str_replace('"', '""', $nombre).'" AS text)) NOT IN (\'\', \'_\')')->exists()) return true;
        $rangos = ['kingdom' => 'reino', 'phylum' => 'phylum', 'class' => 'clase', 'order' => 'orden', 'suborder' => 'suborden', 'family' => 'familia', 'subfamily' => 'subfamilia', 'tribe' => 'tribu', 'genus' => 'genero', 'specificEpithet' => 'especie'];
        if (isset($rangos[$campo]) && DB::selectOne(<<<'SQL'
            WITH RECURSIVE usados AS (
                SELECT t.id, t.padre_id, t.rango FROM taxonomia.taxones t WHERE t.id IN (SELECT taxon_id FROM taxonomia.especimenes)
                UNION SELECT t.id, t.padre_id, t.rango FROM taxonomia.taxones t JOIN usados u ON u.padre_id = t.id
            ) SELECT id FROM usados WHERE rango = ? LIMIT 1
            SQL, [$rangos[$campo]]) !== null) return true;
        $nombres = array_map(mb_strtolower(...), [$campo, $columna['cabecera'], ...($columna['alias'] ?? [])]);
        $parametros = implode(',', array_fill(0, count($nombres), '?'));
        return DB::table('taxonomia.importacion_filas_catalogo')->whereRaw("EXISTS (SELECT 1 FROM jsonb_array_elements(original) AS c WHERE lower(c->>'cabecera') IN ({$parametros}) AND btrim(COALESCE(c->>'valor', '')) NOT IN ('', '_'))", $nombres)->exists();
    }
}
