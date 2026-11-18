<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\LectorActualizacionCatalogo;

test('lee el mismo libro con namespace predeterminado o prefijo y conserva fechas y formulas', function (string $p): void {
    $ruta = tempnam(sys_get_temp_dir(), 'qa_xlsx_').'.xlsx';
    $ns = $p === '' ? 'xmlns' : 'xmlns:x';
    $decl = $ns.'="http://schemas.openxmlformats.org/spreadsheetml/2006/main"';
    $zip = new ZipArchive;
    $zip->open($ruta, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('xl/workbook.xml', '<'.$p.'workbook '.$decl.' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><'.$p.'workbookPr date1904="1"/><'.$p.'sheets><'.$p.'sheet name="Datos" sheetId="1" r:id="r1"/></'.$p.'sheets></'.$p.'workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="r1" Target="/xl/worksheets/sheet1.xml"/></Relationships>');
    $zip->addFromString('xl/styles.xml', '<'.$p.'styleSheet '.$decl.'><'.$p.'numFmts><'.$p.'numFmt numFmtId="164" formatCode="yyyy-mm-dd"/></'.$p.'numFmts><'.$p.'cellXfs><'.$p.'xf numFmtId="0"/><'.$p.'xf numFmtId="164"/></'.$p.'cellXfs></'.$p.'styleSheet>');
    $zip->addFromString('xl/sharedStrings.xml', '<'.$p.'sst '.$decl.'><'.$p.'si><'.$p.'t>occurrenceID</'.$p.'t></'.$p.'si><'.$p.'si><'.$p.'t>eventDate</'.$p.'t></'.$p.'si><'.$p.'si><'.$p.'t>individualCount</'.$p.'t></'.$p.'si></'.$p.'sst>');
    $zip->addFromString('xl/worksheets/sheet1.xml', '<'.$p.'worksheet '.$decl.'><'.$p.'sheetData><'.$p.'row r="1"><'.$p.'c r="A1" t="s"><'.$p.'v>0</'.$p.'v></'.$p.'c><'.$p.'c r="B1" t="s"><'.$p.'v>1</'.$p.'v></'.$p.'c><'.$p.'c r="C1" t="s"><'.$p.'v>2</'.$p.'v></'.$p.'c></'.$p.'row><'.$p.'row r="2"><'.$p.'c r="A2" t="inlineStr"><'.$p.'is><'.$p.'t>QA-XML-01</'.$p.'t></'.$p.'is></'.$p.'c><'.$p.'c r="B2" s="1"><'.$p.'v>1</'.$p.'v></'.$p.'c><'.$p.'c r="C2"><'.$p.'f>2+3</'.$p.'f><'.$p.'v>5</'.$p.'v></'.$p.'c></'.$p.'row></'.$p.'sheetData></'.$p.'worksheet>');
    $zip->close();
    try {
        $filas = iterator_to_array((new LectorActualizacionCatalogo)->filas($ruta, 'xlsx'));
        expect($filas)->toHaveCount(1)->and($filas[2]['datos'])->toMatchArray(['occurrence_id' => 'QA-XML-01', 'event_date' => '1904-01-02', 'individual_count' => '5']);
    } finally { unlink($ruta); }
})->with(['', 'x:']);

test('las fechas tipadas ISO conservan el dia de la celda y rechazan dias imposibles', function (string $valor, ?string $esperado): void {
    $ruta = tempnam(sys_get_temp_dir(), 'qa_fecha_').'.xlsx';
    $zip = new ZipArchive; $zip->open($ruta, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('xl/workbook.xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet r:id="r1"/></sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships><Relationship Id="r1" Target="worksheets/sheet1.xml"/></Relationships>');
    $zip->addFromString('xl/worksheets/sheet1.xml', '<x:worksheet xmlns:x="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><x:sheetData><x:row r="1"><x:c r="A1" t="inlineStr"><x:is><x:t>eventDate</x:t></x:is></x:c></x:row><x:row r="2"><x:c r="A2" t="d"><x:v>'.$valor.'</x:v></x:c></x:row></x:sheetData></x:worksheet>');
    $zip->close();
    try {
        $leer = fn () => iterator_to_array((new LectorActualizacionCatalogo)->filas($ruta, 'xlsx'));
        if ($esperado === null) expect($leer)->toThrow(DomainException::class, 'Fila 2, celda A2');
        else expect($leer()[2]['datos']['event_date'])->toBe($esperado);
    } finally { unlink($ruta); }
})->with([
    ['2026-11-10T12:00:00.000Z', '2026-11-10'], ['2026-11-10T23:30:00-05:00', '2026-11-10'],
    ['2026-02-30T12:00:00Z', null], ['fecha-no-valida', null],
]);
