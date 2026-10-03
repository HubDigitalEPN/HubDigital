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
        // Escribir la hoja a disco evita retener objetos de cada celda en la VM.
        $hojaPath = tempnam(sys_get_temp_dir(), 'portal-hoja-');
        $xlsxPath = tempnam(sys_get_temp_dir(), 'portal-xlsx-');
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
            $escribir('<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><cols>');
            foreach ($encabezados as $i => $nombre) {
                $columna = $i + 1; $ancho = min(42, max(18, mb_strlen($nombre) + 2));
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
            ];
            $zonaFecha = new DateTimeZone('UTC');
            $baseFechaExcel = new DateTimeImmutable('1899-12-31', $zonaFecha);
            $escribirFila = static function (array $valores, bool $cabecera = false) use (&$numero, $escribir, $encabezados, $columnasNumericas, $zonaFecha, $baseFechaExcel): void {
                $escribir('<row r="'.$numero.'">');
                foreach (array_values($valores) as $i => $valor) {
                    $celda = Coordinate::stringFromColumnIndex($i + 1).$numero;
                    $campo = $encabezados[$i] ?? '';
                    if (! $cabecera && isset($columnasNumericas[$campo])) {
                        [$minimo, $maximo, $entero] = $columnasNumericas[$campo];
                        $valorNumerico = NumeroExportacion::decimal($valor, $minimo, $maximo, $entero);
                        if ($valorNumerico !== null) {
                            $escribir('<c r="'.$celda.'" t="n"><v>'.$valorNumerico.'</v></c>');
                            continue;
                        }
                    }
                    if (! $cabecera && $campo === 'eventDate' && is_string($valor)) {
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
                    $escribir('<c r="'.$celda.'" t="inlineStr"'.($cabecera ? ' s="1"' : '').'><is><t xml:space="preserve">'.$texto.'</t></is></c>');
                }
                $escribir('</row>'); $numero++;
            };
            $escribirFila($encabezados, true);
            foreach ($filas as $fila) $escribirFila($fila);
            $ultimaColumna = Coordinate::stringFromColumnIndex(count($encabezados));
            $escribir('</sheetData><autoFilter ref="A1:'.$ultimaColumna.'1"/></worksheet>');
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
