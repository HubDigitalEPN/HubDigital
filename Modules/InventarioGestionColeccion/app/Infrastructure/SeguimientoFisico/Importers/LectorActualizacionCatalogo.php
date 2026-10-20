<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers;

use DomainException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use XMLReader;
use ZipArchive;

/** Lectura por filas: conserva columnas repetidas, formatos de fecha y cachés de fórmulas. */
final class LectorActualizacionCatalogo
{
    public const MAX_FILAS = 50000;
    public const MAX_COLUMNAS = 200;
    public const MAX_BYTES = 25 * 1024 * 1024;

    public function filas(string $ruta, string $extension, string $delimitador = ','): \Generator
    {
        if (!is_file($ruta) || filesize($ruta) > self::MAX_BYTES) throw new DomainException('El archivo no existe o supera los 25 MB permitidos.');
        $fuente = match (strtolower($extension)) {
            'xlsx' => $this->xlsx($ruta),
            'csv', 'txt' => $this->csv($ruta, $delimitador),
            'xls' => $this->xls($ruta),
            default => throw new DomainException('Selecciona un archivo .xlsx, .xls o .csv.'),
        };
        $cabeceras = null;
        $contador = 0;
        $mapper = new FilaCatalogoMapper;
        foreach ($fuente as $numero => $celdas) {
            if (count($celdas) > self::MAX_COLUMNAS || ($celdas !== [] && max(array_keys($celdas)) >= self::MAX_COLUMNAS)) throw new DomainException('La matriz supera las 200 columnas permitidas.');
            if ($cabeceras === null) {
                $cabeceras = array_map(static fn ($v) => trim(preg_replace('/^\xEF\xBB\xBF/', '', (string)$v)), $celdas);
                if (!array_filter($cabeceras)) throw new DomainException('La primera fila debe contener las columnas de la matriz.');
                continue;
            }
            if (!array_filter($celdas, static fn ($v) => trim((string)$v) !== '' && trim((string)$v) !== '_')) continue;
            if (++$contador > self::MAX_FILAS) throw new DomainException('El archivo supera los 50.000 registros. Divide la matriz para continuar.');
            $datos = [];
            $original = [];
            foreach ($celdas as $columna => $valor) {
                $cabecera = $cabeceras[$columna] ?? '';
                if (trim((string)$valor) !== '' && $cabecera === '') throw new DomainException("Fila {$numero}: hay datos en una columna sin encabezado.");
                if ($cabecera === '') continue;
                $original[] = ['columna' => $columna + 1, 'cabecera' => $cabecera, 'valor' => (string)$valor];
                if (trim((string)$valor) === '') continue;
                $normalizada = $mapper->normalizarClaves([$cabecera => trim((string)$valor)]);
                $clave = array_key_first($normalizada);
                if (isset($datos[$clave]) && $datos[$clave] !== $normalizada[$clave]) throw new DomainException("Fila {$numero}: las columnas equivalentes «{$cabecera}» tienen valores distintos. Corrige la fila antes de importar.");
                $datos[$clave] = $normalizada[$clave];
            }
            yield $numero => ['datos' => $datos, 'original' => $original];
        }
        if ($cabeceras === null) throw new DomainException('El archivo está vacío.');
    }

    private function csv(string $ruta, string $delimitador): \Generator
    {
        if (!in_array($delimitador, [',', ';', "\t"], true)) throw new DomainException('Selecciona un separador CSV válido.');
        $archivo = fopen($ruta, 'rb');
        try {
            $fila = 0;
            while (($valores = fgetcsv($archivo, null, $delimitador, '"', '')) !== false) yield ++$fila => array_map(static fn ($v) => (string)$v, $valores);
        } finally { fclose($archivo); }
    }

    private function xml(string $ruta, string $entrada): XMLReader
    {
        $reader = new XMLReader;
        if (!$reader->open('zip://'.str_replace('\\', '/', $ruta).'#'.$entrada, null, LIBXML_NONET | LIBXML_COMPACT)) throw new DomainException('No se pudo leer la estructura del Excel.');
        $reader->setParserProperty(XMLReader::SUBST_ENTITIES, false);
        return $reader;
    }

    private function texto(string $xml): \SimpleXMLElement
    {
        if (stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) throw new DomainException('El Excel contiene una estructura XML no permitida.');
        $elemento = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);
        if ($elemento === false) throw new DomainException('El Excel contiene una estructura ilegible.');
        return $elemento;
    }

    private function xlsx(string $ruta): \Generator
    {
        $zip = new ZipArchive;
        if ($zip->open($ruta) !== true) throw new DomainException('El archivo Excel está dañado.');
        try {
            if ($zip->numFiles > 2000) throw new DomainException('El Excel contiene demasiados componentes. Guárdalo como una matriz nueva y vuelve a cargarlo.');
            $bytes = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $bytes += $stat['size'];
                if ($bytes > 256 * 1024 * 1024 || preg_match('~(^/|(^|/)\.\.(/|$))~', $stat['name'])) throw new DomainException('El Excel excede los límites de descompresión segura.');
                if (in_array($stat['name'], ['xl/workbook.xml', 'xl/_rels/workbook.xml.rels', 'xl/styles.xml'], true) && $stat['size'] > 2 * 1024 * 1024) throw new DomainException('Los estilos o metadatos del Excel son demasiado grandes. Guarda una copia simplificada.');
            }
            $libro = $this->texto((string)$zip->getFromName('xl/workbook.xml'));
            $relaciones = $this->texto((string)$zip->getFromName('xl/_rels/workbook.xml.rels'));
            $hoja = $libro->sheets->sheet[0] ?? null;
            if ($hoja === null) throw new DomainException('El Excel no contiene una hoja de datos.');
            $id = (string)$hoja->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            $entrada = null;
            foreach ($relaciones->Relationship as $relacion) {
                if ((string)$relacion['Id'] !== $id) continue;
                if ((string)$relacion['TargetMode'] === 'External') throw new DomainException('La hoja de datos no puede ser un enlace externo.');
                $target = (string)$relacion['Target'];
                if (str_contains($target, '..') || str_contains($target, ':')) throw new DomainException('La ruta de la hoja no es válida.');
                $entrada = str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/'.$target;
            }
            if ($entrada === null || $zip->locateName($entrada) === false) throw new DomainException('No se encontró la primera hoja del Excel.');
            $strings = [];
            if ($zip->locateName('xl/sharedStrings.xml') !== false) {
                $lector = $this->xml($ruta, 'xl/sharedStrings.xml');
                $tamano = 0;
                try {
                    while ($lector->read()) {
                        if ($lector->nodeType === XMLReader::DOC_TYPE) throw new DomainException('El Excel contiene una declaración XML no permitida.');
                        if ($lector->nodeType !== XMLReader::ELEMENT || $lector->localName !== 'si') continue;
                        $xml = $lector->readOuterXml();
                        if (strlen($xml) > 256 * 1024) throw new DomainException('Una celda de texto del Excel es demasiado grande.');
                        $nodo = $this->texto($xml);
                        $valor = implode('', array_map(static fn ($v) => (string)$v, $nodo->xpath('.//*[local-name()="t"]')));
                        $tamano += strlen($valor);
                        if (count($strings) >= 250000 || $tamano > 16 * 1024 * 1024) throw new DomainException('El diccionario de texto del Excel es demasiado grande. Divide la matriz.');
                        $strings[] = $valor;
                    }
                } finally { $lector->close(); }
            }
            $fechas = [];
            if ($zip->locateName('xl/styles.xml') !== false) {
                $estilos = $this->texto((string)$zip->getFromName('xl/styles.xml'));
                $formatos = [];
                foreach ($estilos->numFmts->numFmt ?? [] as $formato) $formatos[(int)$formato['numFmtId']] = (string)$formato['formatCode'];
                $indice = 0;
                foreach ($estilos->cellXfs->xf ?? [] as $formato) {
                    $num = (int)$formato['numFmtId'];
                    $fechas[$indice++] = Date::isDateTimeFormatCode($formatos[$num] ?? NumberFormat::builtInFormatCode($num) ?: 'General');
                }
            }
            $calendario = in_array((string)($libro->workbookPr['date1904'] ?? ''), ['1', 'true'], true) ? Date::CALENDAR_MAC_1904 : Date::CALENDAR_WINDOWS_1900;
            $lector = $this->xml($ruta, $entrada);
            $leidas = 0;
            $numero = 0;
            $valores = [];
            $textoFila = 0;
            try {
                while ($lector->read()) {
                    if ($lector->nodeType === XMLReader::DOC_TYPE) throw new DomainException('El Excel contiene una declaración XML no permitida.');
                    if ($lector->localName === 'row' && $lector->nodeType === XMLReader::ELEMENT) {
                        if (++$leidas > self::MAX_FILAS + 1000) throw new DomainException('La hoja contiene demasiadas filas. Elimina las filas de plantilla vacías y vuelve a cargarla.');
                        $numero = (int)($lector->getAttribute('r') ?? $leidas);
                        if ($numero < 1 || $numero > 1048576) throw new DomainException('El número de fila no es válido para un archivo Excel.');
                        $valores = [];
                        $textoFila = 0;
                        if ($lector->isEmptyElement) yield $numero => $valores;
                    } elseif ($lector->localName === 'row' && $lector->nodeType === XMLReader::END_ELEMENT) {
                        yield $numero => $valores;
                    } elseif ($lector->nodeType === XMLReader::ELEMENT && $lector->localName === 'c') {
                        $xml = $lector->readOuterXml();
                        if (strlen($xml) > 256 * 1024) throw new DomainException("Fila {$numero}: una celda supera el tamaño permitido.");
                        $celda = $this->texto($xml);
                        $ref = (string)$celda['r'];
                        preg_match('/^[A-Z]+/', $ref, $letras);
                        $columna = 0;
                        foreach (str_split($letras[0] ?? '') as $letra) $columna = $columna * 26 + ord($letra) - 64;
                        if ($columna < 1 || $columna > self::MAX_COLUMNAS) throw new DomainException("Fila {$numero}: la matriz supera las 200 columnas permitidas.");
                        if (isset($celda->f) && !isset($celda->v)) throw new DomainException("Fila {$numero}, celda {$ref}: la fórmula no tiene un resultado guardado. Abre y guarda el Excel antes de subirlo.");
                        $tipo = (string)$celda['t'];
                        $valor = match ($tipo) {
                            's' => isset($celda->v) ? ($strings[(int)$celda->v] ?? throw new DomainException("Texto inválido en {$ref}.")) : '',
                            'inlineStr' => implode('', array_map(static fn ($v) => (string)$v, $celda->xpath('.//*[local-name()="t"]'))),
                            'e' => throw new DomainException("Fila {$numero}, celda {$ref}: Excel contiene un error. Corrige la celda."),
                            default => (string)$celda->v,
                        };
                        if ($valor !== '' && is_numeric($valor) && ($fechas[(int)$celda['s']] ?? false)) {
                            $anterior = Date::getExcelCalendar();
                            try { Date::setExcelCalendar($calendario); $valor = Date::excelToDateTimeObject((float)$valor)->format('Y-m-d'); }
                            finally { Date::setExcelCalendar($anterior); }
                        }
                        $textoFila += strlen($valor);
                        if (strlen($valor) > 65535 || $textoFila > 4 * 1024 * 1024) throw new DomainException("Fila {$numero}: el texto de las celdas supera el tamaño permitido.");
                        $valores[$columna - 1] = $valor;
                    }
                }
            } finally { $lector->close(); }
        } finally { $zip->close(); }
    }

    private function xls(string $ruta): \Generator
    {
        // El formato binario antiguo carece del recorrido XML por filas.
        if (filesize($ruta) > 2 * 1024 * 1024) throw new DomainException('Para un archivo .xls mayor de 2 MB, guárdalo como .xlsx y vuelve a cargarlo.');
        $reader = IOFactory::createReaderForFile($ruta);
        $reader->setReadDataOnly(false);
        $info = $reader->listWorksheetInfo($ruta)[0] ?? [];
        if (($info['totalRows'] ?? 0) > self::MAX_FILAS + 1 || ($info['totalColumns'] ?? 0) > self::MAX_COLUMNAS) throw new DomainException('La hoja supera los límites de filas o columnas.');
        $reader->setLoadSheetsOnly($info['worksheetName']);
        $book = $reader->load($ruta);
        try {
            foreach ($book->getSheet(0)->getRowIterator() as $fila) {
                $values = [];
                foreach ($fila->getCellIterator('A', $info['lastColumnLetter']) as $cell) {
                    $value = $cell->isFormula() ? $cell->getOldCalculatedValue() : $cell->getValue();
                    if ($cell->isFormula() && $value === null) throw new DomainException('Hay fórmulas sin resultado guardado. Abre y guarda el Excel antes de subirlo.');
                    if ($value !== null && is_numeric($value) && Date::isDateTime($cell)) $value = Date::excelToDateTimeObject((float)$value)->format('Y-m-d');
                    $values[] = (string)$value;
                }
                yield $fila->getRowIndex() => $values;
            }
        } finally { $book->disconnectWorksheets(); }
    }
}
