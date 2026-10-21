<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers;

use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ZipArchive;

/** La plantilla curatorial es independiente de las obligaciones del depósito. */
final class ConfiguracionPlantillaCatalogo
{
    public static function predeterminada(): array
    {
        return json_decode(file_get_contents(__DIR__.'/../../../../database/data/plantilla-catalogo-v3.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    public function leer(): array
    {
        if (!Schema::hasTable('taxonomia.configuracion_plantilla_catalogo')) return self::predeterminada();
        $valor = DB::table('taxonomia.configuracion_plantilla_catalogo')->where('clave', 'catalogo')->value('definicion');
        return $valor ? json_decode($valor, true, 512, JSON_THROW_ON_ERROR) : self::predeterminada();
    }

    public static function huella(array $definicion): string
    {
        // jsonb puede reordenar las claves de cada objeto. El orden de columnas
        // sí tiene significado; el orden interno de las propiedades no.
        $ordenar = static function (array $valor) use (&$ordenar): array {
            foreach ($valor as $clave => $elemento) if (is_array($elemento)) $valor[$clave] = $ordenar($elemento);
            if (!array_is_list($valor)) ksort($valor);
            return $valor;
        };
        return hash('sha256', json_encode($ordenar($definicion), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    public static function alias(array $definicion): array
    {
        $mapa = [];
        foreach ($definicion['columnas'] as $c) foreach ([$c['cabecera'], $c['campo'], ...($c['alias'] ?? [])] as $nombre) {
            $clave = mb_strtolower(trim($nombre));
            if (isset($mapa[$clave]) && $mapa[$clave] !== $c['campo']) throw new DomainException("La columna «{$nombre}» identifica más de un campo.");
            $mapa[$clave] = $c['campo'];
        }
        return $mapa;
    }

    public static function validarFila(array $datos, int $fila, array $definicion): void
    {
        $faltantes = [];
        $mapper = new FilaCatalogoMapper;
        foreach ($definicion['columnas'] as $c) {
            if ($c['requisito'] !== 'obligatorio') continue;
            $clave = array_key_first($mapper->normalizarClaves([$c['campo'] => 'valor']));
            $valor = $datos[$clave] ?? '';
            // Las matrices históricas pueden identificar la misma ficha con catalogNumber.
            if ($c['campo'] === 'occurrenceID' && trim((string)$valor) === '') $valor = $datos['catalog_number'] ?? '';
            if (trim((string)$valor) === '' || trim((string)$valor) === '_') $faltantes[] = $c['cabecera'];
        }
        if ($faltantes !== []) throw new DomainException('Fila '.$fila.': campos obligatorios sin completar: '.implode(', ', $faltantes).'.');
    }

    public static function validar(array $definicion): void
    {
        $columnas = $definicion['columnas'] ?? [];
        if (count($columnas) < 1 || count($columnas) > 200) throw new DomainException('La plantilla debe contener entre 1 y 200 columnas.');
        $campos = []; $cabeceras = [];
        foreach ($columnas as $c) {
            foreach (['campo', 'cabecera', 'descripcion'] as $k) if (!is_string($c[$k] ?? null) || trim($c[$k]) === '' || mb_strlen($c[$k]) > ($k === 'descripcion' ? 5000 : 160) || str_starts_with(ltrim($c[$k]), '=')) throw new DomainException('Cada columna necesita un campo, encabezado y descripción válidos; no se admiten fórmulas.');
            $campo = mb_strtolower(trim($c['campo'])); $cabecera = mb_strtolower(trim($c['cabecera']));
            if (isset($campos[$campo]) || isset($cabeceras[$cabecera])) throw new DomainException('La configuración contiene campos o encabezados repetidos.');
            $campos[$campo] = true; $cabeceras[$cabecera] = true;
            if (!in_array($c['requisito'] ?? '', ['obligatorio', 'recomendado', 'opcional'], true) || !in_array($c['formato'] ?? '', ['texto', 'fecha'], true)) throw new DomainException('Requisito o formato no válido en «'.$c['cabecera'].'».');
        }
        $id = array_values(array_filter($columnas, fn ($c) => $c['campo'] === 'occurrenceID'));
        if (count($id) !== 1 || $id[0]['requisito'] !== 'obligatorio') throw new DomainException('Conserva occurrenceID como obligatorio para identificar y actualizar las fichas.');
        self::alias($definicion);
    }

    /** Sólo lee metadatos explícitos; nunca evalúa fórmulas del libro. */
    public function desdeExcel(string $ruta): array
    {
        $zip = new ZipArchive;
        if ($zip->open($ruta) !== true) throw new DomainException('Selecciona una plantilla .xlsx válida.');
        try {
            $bytes = 0;
            if ($zip->numFiles > 500) throw new DomainException('La plantilla contiene demasiados componentes.');
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i); $bytes += $stat['size'];
                if ($bytes > 16 * 1024 * 1024 || preg_match('~(^/|(^|/)\.\.(/|$))~', $stat['name'])) throw new DomainException('La plantilla excede el tamaño permitido.');
            }
        } finally { $zip->close(); }
        $lector = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx;
        $lector->setReadFilter(new class implements \PhpOffice\PhpSpreadsheet\Reader\IReadFilter {
            public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
            {
                return $worksheetName === 'Guía' ? $row <= 211 : $row === 1;
            }
        });
        $libro = $lector->load($ruta);
        try {
            $guia = $libro->getSheetByName('Guía'); $hoja = $libro->getSheet(0);
            if (!$guia || $guia->getHighestDataRow() > 210 || \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($hoja->getHighestDataColumn()) > 200) throw new DomainException('Conserva las hojas Especímenes y Guía de la plantilla descargada, con un máximo de 200 columnas.');
            $leer = static function ($celda): string {
                if ($celda->getDataType() === 'f') throw new DomainException('La configuración y la guía no admiten fórmulas.');
                return trim((string)$celda->getValue());
            };
            $anteriores = $this->leer(); $porCampo = array_column($anteriores['columnas'], null, 'campo'); $columnas = [];
            for ($i = 2; $i <= $guia->getHighestDataRow(); $i++) {
                $campo = $leer($guia->getCell('A'.$i)); if ($campo === '') continue;
                $c = ['campo' => $campo, 'cabecera' => $leer($guia->getCell('B'.$i)), 'descripcion' => $leer($guia->getCell('C'.$i)), 'requisito' => mb_strtolower($leer($guia->getCell('D'.$i))), 'formato' => mb_strtolower($leer($guia->getCell('E'.$i)))];
                $previo = $porCampo[$campo] ?? null;
                $c['alias'] = $previo['alias'] ?? [];
                if ($previo && $previo['cabecera'] !== $c['cabecera']) $c['alias'] = array_values(array_unique([...$c['alias'], $previo['cabecera']]));
                $columnas[] = $c;
            }
            $definicion = ['version' => 'catalogo-v3', 'columnas' => $columnas]; self::validar($definicion);
            // Leer la fila por coordenada evita perder encabezados vacíos intermedios.
            $cabeceras = [];
            $cantidad = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($hoja->getHighestDataColumn());
            for ($i = 1; $i <= $cantidad; $i++) $cabeceras[] = $leer($hoja->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i).'1'));
            if ($cabeceras !== array_column($columnas, 'cabecera')) throw new DomainException('Los encabezados y su orden deben coincidir con la columna «Columna» de la hoja Guía.');
            return $definicion;
        } finally { $libro->disconnectWorksheets(); }
    }

    public function guardar(array $definicion, string $esperada, string $usuario): void
    {
        self::validar($definicion);
        DB::transaction(function () use ($definicion, $esperada, $usuario): void {
            $fila = DB::table('taxonomia.configuracion_plantilla_catalogo')->where('clave', 'catalogo')->lockForUpdate()->first();
            if (!$fila || !hash_equals($esperada, self::huella(json_decode($fila->definicion, true, 512, JSON_THROW_ON_ERROR)))) throw new DomainException('La plantilla cambió mientras la editabas. Descarga la versión actual y vuelve a aplicar tus ajustes.');
            DB::table('taxonomia.configuracion_plantilla_catalogo')->where('clave', 'catalogo')->update(['definicion' => json_encode($definicion, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), 'actualizado_por' => $usuario, 'updated_at' => now()]);
        });
    }
}
