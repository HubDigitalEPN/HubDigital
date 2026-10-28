<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Infrastructure\Adapters;

use Modules\CatalogoPublico\Application\Ports\GeneradorXlsxPort;
use Modules\CatalogoPublico\Domain\ValueObjects\NumeroExportacion;
use DateTimeImmutable;
use DateTimeZone;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use RuntimeException;
use ZipArchive;

final class PhpSpreadsheetGeneradorXlsxAdapter implements GeneradorXlsxPort
{
    public function generar(array $encabezados, iterable $filas): string
    {
        return $this->escribir($encabezados, $filas, false);
    }

    public function generarIpt(array $encabezados, iterable $filas, array $columnas = [], array &$revision = []): string
    {
        return $this->escribir($encabezados, $filas, true, $columnas, $revision);
    }

    public function generarCatalogo(array $encabezados, iterable $filas, array $campos): string
    {
        return $this->escribir($encabezados, $filas, false, array_map(static fn (string $campo): array => ['campo' => $campo], $campos));
    }

    private function escribir(array $encabezados, iterable $filas, bool $ipt, array $columnas = [], array &$revision = []): string
    {
        // Escribir la hoja a disco evita retener objetos de cada celda en la VM.
        // PHP usa /run/hubdigital/php-tmp en OCI (tmpfs pequeño). Una hoja
        // completa puede superar ese límite aunque el disco tenga espacio.
        $directorio = storage_path('app/private/exportaciones');
        if (! is_dir($directorio) && ! mkdir($directorio, 0700, true) && ! is_dir($directorio)) {
            throw new RuntimeException('No se pudo preparar el directorio privado de exportación.');
        }
        $hojaPath = tempnam($directorio, 'portal-hoja-');
        $xlsxPath = tempnam($directorio, 'portal-xlsx-');
        if ($hojaPath === false || $xlsxPath === false) {
            if ($hojaPath !== false) unlink($hojaPath);
            if ($xlsxPath !== false) unlink($xlsxPath);
            throw new RuntimeException('No se pudo preparar el archivo de exportación.');
        }
        $hoja = null; $zip = new ZipArchive(); $zipAbierto = false;
        try {
            $hoja = fopen($hojaPath, 'wb');
            if ($hoja === false) throw new RuntimeException('No se pudo escribir la hoja de exportación.');
            $escribir = static function (string $texto) use ($hoja): void {
                if (fwrite($hoja, $texto) !== strlen($texto)) throw new RuntimeException('Escritura de exportación incompleta.');
            };
            $escribir('<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'.($ipt ? '<sheetFormatPr baseColWidth="10" defaultRowHeight="16"/>' : '').'<cols>');
            foreach ($encabezados as $i => $nombre) {
                $columna = $i + 1; $ancho = $ipt ? (int)($columnas[$i]['ancho'] ?? 10) : min(42, max(18, mb_strlen($nombre) + 2));
                $escribir('<col min="'.$columna.'" max="'.$columna.'" width="'.$ancho.'" customWidth="1"/>');
            }
            $escribir('</cols><sheetData>');
            $numero = 1;
            $columnasNumericas = [
                'individualCount' => [0, null, true],
                'decimalLatitude' => [-90, 90, false],
                'decimalLongitude' => [-180, 180, false],
                'minimumElevationInMeters' => [null, null, false],
                'maximumElevationInMeters' => [null, null, false],
                'coordinateUncertaintyInMeters' => [0, null, false],
                'year' => [1, 9999, true], 'month' => [1, 12, true], 'day' => [1, 31, true],
            ];
            $zonaFecha = new DateTimeZone('UTC');
            $baseFechaExcel = new DateTimeImmutable('1899-12-31', $zonaFecha);
            $escribirFila = static function (array $valores, bool $cabecera = false) use (&$numero, $escribir, $encabezados, $columnasNumericas, $zonaFecha, $baseFechaExcel, $ipt, $columnas, &$revision): void {
                $escribir('<row r="'.$numero.'">');
                foreach (array_values($valores) as $i => $valor) {
                    $celda = Coordinate::stringFromColumnIndex($i + 1).$numero;
                    $campo = $columnas[$i]['campo'] ?? $encabezados[$i] ?? '';
                    $formato = $ipt ? ($columnas[$i]['formato'] ?? null) : null;
                    if (! $cabecera && ($formato === 'entero' || $formato === 'decimal' || ($formato === null && isset($columnasNumericas[$campo])))) {
                        [$minimo, $maximo, $entero] = $columnasNumericas[$campo] ?? [null, null, $formato === 'entero'];
                        if ($formato !== null) $entero = $formato === 'entero';
                        $valorNumerico = NumeroExportacion::decimal($valor, $minimo, $maximo, $entero && !$ipt);
                        if ($ipt && $entero && !preg_match('/^[+-]?\d+$/D', trim((string)($valor ?? '')))) $valorNumerico = null;
                        if ($ipt && $campo === 'coordinateUncertaintyInMeters' && $valorNumerico !== null && (float)$valorNumerico <= 0) $valorNumerico = null;
                        if ($valorNumerico !== null) {
                            $escribir('<c r="'.$celda.'" t="n"><v>'.$valorNumerico.'</v></c>');
                            continue;
                        }
                        if ($ipt && $formato !== null && trim((string)($valor ?? '')) !== '') { $revision['formato:'.$campo] = ($revision['formato:'.$campo] ?? 0) + 1; $valor = ''; }
                    }
                    if (!$cabecera && $formato === 'fecha' && trim((string)($valor ?? '')) !== '' && !\App\Support\PerfilIptGbif::fechaIsoValida((string)$valor)) { $revision['formato:'.$campo] = ($revision['formato:'.$campo] ?? 0) + 1; $valor = ''; }
                    if (! $cabecera && ($formato === 'fecha' || ($formato === null && $campo === 'eventDate')) && is_string($valor)) {
                        $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $valor, $zonaFecha);
                        if ($fecha !== false && $fecha->format('Y-m-d') === $valor && $valor >= '1900-01-01') {
                            // Este libro usa siempre el calendario Excel 1900, incluida
                            // su fecha ficticia 29/02/1900; no depende de otros libros leídos.
                            $serial = $baseFechaExcel->diff($fecha)->days + ($valor >= '1900-03-01' ? 1 : 0);
                            $escribir('<c r="'.$celda.'" t="n" s="2"><v>'.$serial.'</v></c>');
                            continue;
                        }
                    }
                    $texto = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string) ($valor ?? ''));
                    $texto = htmlspecialchars($texto, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                    // Códigos, textos no confiables y fechas/números no normalizados
                    // permanecen texto literal: nunca se escriben elementos <f>.
                    $escribir('<c r="'.$celda.'" t="inlineStr"'.($cabecera && !$ipt ? ' s="1"' : '').'><is><t xml:space="preserve">'.$texto.'</t></is></c>');
                }
                $escribir('</row>'); $numero++;
            };
            $escribirFila($encabezados, true);
            foreach ($filas as $fila) $escribirFila($fila);
            $ultimaColumna = Coordinate::stringFromColumnIndex(count($encabezados));
            $escribir('</sheetData>'.($ipt ? '<pageMargins left="0.75" right="0.75" top="1" bottom="1" header="0.5" footer="0.5"/>' : '<autoFilter ref="A1:'.$ultimaColumna.'1"/>').'</worksheet>');
            fclose($hoja); $hoja = null;
            if ($zip->open($xlsxPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('No se pudo crear el archivo XLSX.');
            $zipAbierto = true;
            $xmls = [
                '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
                '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
                'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Especímenes" sheetId="1" r:id="rId1"/></sheets></workbook>',
                'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
                'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><numFmts count="1"><numFmt numFmtId="164" formatCode="yyyy-mm-dd"/></numFmts><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF1B365D"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="center"/></xf><xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>',
            ];
            if ($ipt) {
                $xmls['xl/workbook.xml'] = str_replace('name="Especímenes"', 'name="occurrence"', $xmls['xl/workbook.xml']);
                $xmls['xl/styles.xml'] = str_replace(['<sz val="11"/>', 'Calibri'], ['<sz val="12"/>', 'Aptos Narrow'], $xmls['xl/styles.xml']);
                if ($revision !== []) {
                    $xmls['[Content_Types].xml'] = str_replace('</Types>', '<Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>', $xmls['[Content_Types].xml']);
                    $xmls['xl/workbook.xml'] = str_replace('</sheets>', '<sheet name="Revisión" sheetId="2" r:id="rId3"/></sheets>', $xmls['xl/workbook.xml']);
                    $xmls['xl/_rels/workbook.xml.rels'] = str_replace('</Relationships>', '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/></Relationships>', $xmls['xl/_rels/workbook.xml.rels']);
                    $aviso = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><cols><col min="1" max="1" width="100" customWidth="1"/></cols><sheetData>';
                    $lineas = ['Requiere revisión: se conservaron todos los registros y se dejaron vacíos los datos obligatorios no disponibles.', 'Completa los datos en el sistema antes de publicar el recurso en IPT.'];
                    foreach ($revision as $campo => $cantidad) $lineas[] = str_starts_with($campo, 'formato:')
                        ? substr($campo, 8).': '.$cantidad.' valores incompatibles con el formato configurado; se dejaron vacíos. El original se conserva en el catálogo completo.'
                        : $campo.': '.$cantidad.' registros sin dato obligatorio.';
                    foreach ($lineas as $i => $linea) $aviso .= '<row r="'.($i+1).'"><c r="A'.($i+1).'" t="inlineStr"><is><t>'.htmlspecialchars($linea, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</t></is></c></row>';
                    $xmls['xl/worksheets/sheet2.xml'] = $aviso.'</sheetData></worksheet>';
                }
            }
            foreach ($xmls as $ruta => $xml) if (! $zip->addFromString($ruta, $xml)) throw new RuntimeException('No se pudo completar el archivo XLSX.');
            if (! $zip->addFile($hojaPath, 'xl/worksheets/sheet1.xml') || ! $zip->close()) throw new RuntimeException('No se pudo cerrar el archivo XLSX.');
            $zipAbierto = false;
            $contenido = file_get_contents($xlsxPath);
            if ($contenido === false) throw new RuntimeException('No se pudo leer el archivo XLSX terminado.');
            return $contenido;
        } finally {
            if (is_resource($hoja)) fclose($hoja);
            if ($zipAbierto) $zip->close();
            unlink($hojaPath); unlink($xlsxPath);
        }
    }
}
