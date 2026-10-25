<?php

declare(strict_types=1);

namespace App\Support;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\ConfiguracionPlantillaCatalogo;

final class ConfiguracionIpt
{
    public const FORMATOS = ['texto' => 'Texto literal', 'entero' => 'Número entero', 'decimal' => 'Número decimal', 'fecha' => 'Fecha ISO 8601'];

    public static function predeterminada(): array
    {
        return ['columnas' => array_map(static fn (string $campo): array => [
            'campo' => $campo, 'origen' => $campo,
            'formato' => in_array($campo, ['individualCount', 'year', 'month', 'day'], true) ? 'entero'
                : (in_array($campo, ['decimalLatitude', 'decimalLongitude', 'minimumElevationInMeters', 'maximumElevationInMeters'], true) ? 'decimal' : ($campo === 'eventDate' ? 'fecha' : 'texto')),
            'ancho' => 10,
        ], PerfilIptGbif::COLUMNAS), 'fuente' => 'epn-ipt-69-v1'];
    }

    public function leer(): array
    {
        $valor = DB::table('taxonomia.configuracion_plantilla_catalogo')->where('clave', 'ipt')->value('definicion');
        return $valor ? json_decode($valor, true, 512, JSON_THROW_ON_ERROR) : self::predeterminada();
    }

    public static function terminos(): array
    {
        // Registro oficial de Occurrence Core 2025-07-10 y términos del ejemplo
        // EPN conservados para compatibilidad con instalaciones IPT anteriores.
        return array_values(array_unique([...PerfilIptGbif::COLUMNAS, ...json_decode(file_get_contents(resource_path('data/ipt-occurrence-terms.json')), true, 512, JSON_THROW_ON_ERROR)]));
    }

    public function origenes(): array
    {
        return array_values(array_unique(['', ...PerfilIptGbif::COLUMNAS, ...self::terminos(),
            ...array_column(app(ConfiguracionPlantillaCatalogo::class)->leer()['columnas'], 'campo'),
            'catalogNumber', 'coordinateUncertaintyInMeters', 'georeferenceRemarks', 'verbatimEventDate',
        ]));
    }

    public function validar(array $def): void
    {
        $columnas = $def['columnas'] ?? [];
        if (!array_is_list($columnas) || count($columnas) < 1 || count($columnas) > 300) throw new DomainException('Configura entre 1 y 300 columnas.');
        $vistos = []; $terminos = self::terminos(); $origenes = $this->origenes();
        foreach ($columnas as $c) {
            if (!is_array($c) || !in_array($c['campo'] ?? null, $terminos, true)) throw new DomainException('Selecciona un término Darwin Core admitido para cada cabecera.');
            if (isset($vistos[$c['campo']])) throw new DomainException('Las cabeceras no pueden repetirse: '.$c['campo']);
            $vistos[$c['campo']] = true;
            if (!in_array($c['origen'] ?? null, $origenes, true) || !isset(self::FORMATOS[$c['formato'] ?? ''])) throw new DomainException('El campo de origen o formato no es válido.');
            if (filter_var($c['ancho'] ?? null, FILTER_VALIDATE_INT) === false || $c['ancho'] < 5 || $c['ancho'] > 80) throw new DomainException('El ancho debe estar entre 5 y 80.');
            if ((preg_match('/(?:ID|Code)$/D', $c['campo']) || in_array($c['campo'], ['id', 'catalogNumber', 'recordNumber', 'otherCatalogNumbers'], true)) && $c['formato'] !== 'texto') throw new DomainException('Los identificadores y códigos deben conservar el formato Texto literal.');
            if ($c['campo'] === 'eventDate' && $c['formato'] !== 'fecha') throw new DomainException('eventDate debe usar Fecha ISO 8601.');
            if (in_array($c['campo'], ['id', 'occurrenceID'], true) && $c['origen'] !== $c['campo']) throw new DomainException('Conserva el origen estable de id y occurrenceID para identificar el mismo registro en cada descarga.');
        }
        foreach (['occurrenceID', 'basisOfRecord', 'scientificName', 'eventDate'] as $obligatorio) if (!isset($vistos[$obligatorio])) throw new DomainException('Conserva la columna obligatoria '.$obligatorio.'.');
    }

    public function guardar(array $def, string $huella): void
    {
        abort_unless(auth()->user()?->esAdministrador(), 403);
        $this->validar($def);
        DB::transaction(function () use ($def, $huella): void {
            // La fila catálogo existe también antes del primer guardado de IPT.
            DB::table('taxonomia.configuracion_plantilla_catalogo')->where('clave', 'catalogo')->lockForUpdate()->first();
            if (!hash_equals(ConfiguracionPlantillaCatalogo::huella($this->leer()), $huella)) throw new DomainException('Otro administrador cambió el formato. Recarga la pantalla antes de guardar.');
            DB::table('taxonomia.configuracion_plantilla_catalogo')->updateOrInsert(['clave' => 'ipt'], [
                'definicion' => json_encode($def, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'actualizado_por' => auth()->id(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }
}
