<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\GestionPrestamosRecepciones\Application\Ports\GeneradorActaRecepcionPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\IngresoColeccionPort;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDetalleRecepcion\ConsultarDetalleRecepcionHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDetalleRecepcion\ConsultarDetalleRecepcionInput;
use Modules\GestionPrestamosRecepciones\Domain\Entities\MatrizEspecies;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\MatrizEspeciesRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel as Solicitud;
use Modules\GestionPrestamosRecepciones\Infrastructure\Services\ContenidoRecibidoDeposito;
use Smalot\PdfParser\Parser;

uses(Tests\DatabaseFeatureTestCase::class);

function crearContenidoParaColeccion(bool $validado = true): Solicitud
{
    $usuario = User::factory()->depositante()->create();
    $s = Solicitud::create(['id' => (string)Str::uuid(), 'numero' => 'MEPN-INV-DEP-'.random_int(10000,99999),
        'investigador_id' => $usuario->id, 'tipo_tramite' => 'Depósito', 'estado' => 'Aprobada Documentalmente',
        'codigo_qr' => 'LOTE-'.strtoupper(Str::random(6)), 'solicitud_proyecto' => 'PRUEBA QA - SIMULACION SIN ENTREGA REAL',
        'grupo_animal' => 'Insectos', 'nro_lotes' => 1, 'nro_individuos' => 12, 'nro_morfoespecies' => 1,
        'documentos_adjuntos' => [], 'datos_faltantes' => [], 'contenido_validado_en' => $validado ? now() : null]);
    $repo = app(MatrizEspeciesRepositoryInterface::class);
    $matriz = MatrizEspecies::crear($repo->nextIdentity(), $s->id, ['scientificName' => true, 'basisOfRecord' => true], 'Depósito');
    $matriz->agregarRegistroEspecimen('Dolichoderus sp. QA', datosDwC: [
        'scientificName' => 'Dolichoderus sp. QA', 'basisOfRecord' => 'PreservedSpecimen', 'individualCount' => 12,
        'recordNumber' => 'Frasco QA01', 'country' => 'Ecuador', 'decimalLatitude' => '-0.09', 'decimalLongitude' => '-78.47',
        'eventDate' => '2026-11-10', 'recordedBy' => 'Colector QA', 'specimenNotes' => 'Nota original conservada.',
    ]);
    $repo->guardar($matriz);
    foreach ([['Frasco QA01',6,'Pheidole sp. QA'],['Frasco QA02',4,'Camponotus sp. QA']] as [$identificador,$cantidad,$nombre]) {
        DB::table('recepciones.contenido_recepcion')->insert(['id' => (string)Str::uuid(), 'solicitud_id' => $s->id,
            'identificador' => $identificador, 'cantidad' => $cantidad,
            'datos_dwc' => json_encode(['scientificName' => $nombre, 'recordNumber' => $identificador, 'preparations' => 'ethanol 80%']),
            'observaciones' => 'PRUEBA QA - SIN MATERIAL REAL - SIN EFECTOS JURIDICOS.',
            'validado_por' => $usuario->id, 'validado_en' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }
    return $s;
}

test('el ingreso usa las cantidades e identificaciones constatadas conserva metadatos e idempotencia', function (): void {
    $s = crearContenidoParaColeccion();
    $s->forceFill(['nro_permiso_recoleccion' => 'PERMISO-QA-REC-01', 'nro_permiso_movilizacion' => 'PERMISO-QA-MOV-02'])->save();
    $puerto = app(IngresoColeccionPort::class);
    expect($puerto->ingresarLote($s->id, 'Temporal')->especimenesCreados)->toBe(2);
    $filas = DB::table('taxonomia.especimenes')->where('acta_recepcion', $s->numero)->get();
    expect($filas)->toHaveCount(2)->and($filas->sum('individual_count'))->toBe(10);
    $asociada = $filas->firstWhere('taxon_verbatim', 'Pheidole sp. QA');
    expect($asociada)->not->toBeNull()->and((float)$asociada->decimal_latitude)->toBe(-0.09)
        ->and($asociada->specimen_notes)->toContain('Nota original conservada.', 'SIN MATERIAL REAL');
    $adicional = $filas->firstWhere('taxon_verbatim', 'Camponotus sp. QA');
    expect($adicional->decimal_latitude)->toBeNull()->and($adicional->estado_revision)->toBe('pendiente');
    foreach ([$asociada, $adicional] as $especimen) {
        expect($especimen->research_permit)->toBe('PERMISO-QA-REC-01')
            ->and($especimen->transport_permit)->toBe('PERMISO-QA-MOV-02')
            ->and($especimen->cardex_liquid_collection_code)->toBe($s->numero);
    }
    $repetido = $puerto->ingresarLote($s->id, 'Temporal');
    expect($repetido->especimenesCreados)->toBe(0)->and($repetido->omitidosPorDuplicado)->toBe(2)
        ->and($puerto->resumenDeLote($s->id)->especimenesEnColeccion)->toBe(2);
    expect(DB::table('divulgacion.especimenes_divulgables')->whereIn('especimen_id', $filas->pluck('id'))->exists())->toBeFalse();
    expect($puerto->devolverLote($s->id, new DateTimeImmutable))->toBe(2);
});

test('los lotes historicos ya ingresados conservan sus codigos sin duplicarse al leer el contenido nuevo', function (): void {
    $s = crearContenidoParaColeccion(false); $puerto = app(IngresoColeccionPort::class);
    expect($puerto->ingresarLote($s->id, 'Temporal')->especimenesCreados)->toBe(1);
    $original = DB::table('taxonomia.especimenes')->where('acta_recepcion', $s->numero)->sole();
    $s->forceFill(['contenido_validado_en' => now()])->save();
    expect($puerto->ingresarLote($s->id, 'Temporal')->omitidosPorDuplicado)->toBe(1)
        ->and($puerto->resumenDeLote($s->id)->especimenesEnColeccion)->toBe(1);
    expect(DB::table('taxonomia.especimenes')->where('acta_recepcion', $s->numero)->sole()->id)->toBe($original->id)
        ->and(DB::table('taxonomia.especimenes')->where('id',$original->id)->value('individual_count'))->toBe(12);
});

test('el acta incluye proyecto contenido constatado y observaciones sin alterar la declaracion', function (): void {
    $s = crearContenidoParaColeccion();
    $detalle = app(ConsultarDetalleRecepcionHandler::class)->handle(new ConsultarDetalleRecepcionInput($s->id));
    $pdf = app(GeneradorActaRecepcionPort::class)->generar($detalle);
    $texto = (new Parser)->parseContent($pdf)->getText();
    expect($texto)->toContain('Contenido constatado:', 'Frasco QA01', 'Frasco QA02', 'Pheidole sp. QA',
        'SIN EFECTOS JURIDICOS', 'SIMULACION SIN ENTREGA REAL');
    expect($s->refresh()->nro_individuos)->toBe(12);
    file_put_contents(storage_path('app/private/qa-acta-contenido.pdf'), $pdf);
});

test('el contenido cerrado sin filas se rechaza y la declaracion no se usa como sustituto', function (): void {
    $s = crearContenidoParaColeccion();
    DB::table('recepciones.contenido_recepcion')->where('solicitud_id',$s->id)->delete();
    expect(fn () => app(ContenidoRecibidoDeposito::class)->detalle($s->id))->toThrow(DomainException::class, 'no conserva');
});
