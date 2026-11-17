<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\CatalogoLocalidadesEcuador;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AceptarRecepcionConObservaciones\AceptarRecepcionConObservacionesHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AceptarRecepcionConObservaciones\AceptarRecepcionConObservacionesInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ActualizarOrigenSolicitudDeposito\ActualizarOrigenSolicitudDepositoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ActualizarOrigenSolicitudDeposito\ActualizarOrigenSolicitudDepositoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarDocumentalmenteSolicitud\AprobarDocumentalmenteSolicitudHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarDocumentalmenteSolicitud\AprobarDocumentalmenteSolicitudInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarRecepcionLote\AprobarRecepcionLoteHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarRecepcionLote\AprobarRecepcionLoteInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\GenerarActaRecepcion\GenerarActaRecepcionHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\GenerarActaRecepcion\GenerarActaRecepcionInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\IniciarRecepcionLote\IniciarRecepcionLoteHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\IniciarRecepcionLote\IniciarRecepcionLoteInput;
use Modules\GestionPrestamosRecepciones\Infrastructure\Jobs\ProcesarIngresoDeposito;
use Modules\GestionPrestamosRecepciones\Infrastructure\Notifications\CodigoQrDisponibleNotification;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\RecepcionLoteEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel as Solicitud;
use Modules\GestionPrestamosRecepciones\Infrastructure\Services\DocumentoIngresoDeposito;
use Modules\GestionPrestamosRecepciones\Infrastructure\Services\IngresoPendienteDeposito;
use Modules\GestionPrestamosRecepciones\Infrastructure\Services\RecepcionQrDeposito;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador\ValidarIngresoQr;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador\DetalleDeposito;
use Smalot\PdfParser\Parser;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\DatabaseFeatureTestCase;

uses(DatabaseFeatureTestCase::class);

function depositoArquitectura(array $datos = []): Solicitud
{
    return Solicitud::create([
        'id' => (string) Str::uuid(), 'numero' => 'QA-ARQ-DEP-'.random_int(10000, 99999),
        'investigador_id' => User::factory()->depositante()->create()->id, 'tipo_tramite' => 'Depósito',
        'estado' => 'Aprobada Documentalmente', 'codigo_qr' => 'LOTE-'.Str::upper(Str::random(6)),
        'aprobada_en' => now(), 'solicitud_proyecto' => 'Proyecto aprobado original', 'nro_individuos' => 8,
        'documentos_adjuntos' => [], 'datos_faltantes' => [], ...$datos,
    ]);
}

beforeEach(function (): void {
    Notification::fake();
    Storage::fake('local');
    config(['deposit-storage.driver' => 'local', 'deposit-storage.require_remote' => false]);
});

test('aprobación y pendiente son atómicos y registrar dos veces no duplica el aviso ni afirma correo enviado', function (): void {
    $s = depositoArquitectura(['estado' => 'Pendiente de Revisión por Curaduría', 'codigo_qr' => null]);
    $curador = User::factory()->curador()->create();
    $salida = app(AprobarDocumentalmenteSolicitudHandler::class)(new AprobarDocumentalmenteSolicitudInput($s->id, $curador->id));
    expect($salida->notificacionInvestigadorEnviada)->toBeFalse()->and($salida->notificacionInvestigadorProgramada)->toBeTrue();
    $pendientes = app(IngresoPendienteDeposito::class);
    expect($pendientes->registrar($s->id))->toBe($pendientes->registrar($s->id));
    expect(DB::table(IngresoPendienteDeposito::TABLA)->where('solicitud_id', $s->id)->count())->toBe(1);
    Notification::assertNotSentTo(User::find($s->investigador_id), CodigoQrDisponibleNotification::class);
    DB::beginTransaction();
    $otra = depositoArquitectura(['estado' => 'Pendiente de Revisión por Curaduría', 'codigo_qr' => null]);
    app(AprobarDocumentalmenteSolicitudHandler::class)(new AprobarDocumentalmenteSolicitudInput($otra->id, $curador->id));
    DB::rollBack();
    expect(DB::table(IngresoPendienteDeposito::TABLA)->where('solicitud_id', $otra->id)->exists())->toBeFalse();
});

test('el worker recupera un ingreso tras el commit y preserva el snapshot y los bytes al repetirse', function (): void {
    $s = depositoArquitectura();
    $pendientes = app(IngresoPendienteDeposito::class);
    $pendientes->registrar($s->id);
    $s->update(['solicitud_proyecto' => 'Cambio posterior no aprobado', 'nro_individuos' => 999]);
    $pendientes->procesar($s->id);
    $r = DB::table(IngresoPendienteDeposito::TABLA)->where('solicitud_id', $s->id)->first();
    // El fake permite comprobar el trabajo, pero no acredita una respuesta del transporte.
    expect($r->estado)->toBe('revision_necesaria')->and($r->correo_estado)->toBe('no_confirmado');
    $pdf = app(DocumentoIngresoDeposito::class)->contenido($s->id);
    $texto = (new Parser)->parseContent($pdf)->getText();
    expect($texto)->toContain('Proyecto aprobado original')->not->toContain('Cambio posterior no aprobado');
    $pendientes->procesar($s->id);
    expect(app(DocumentoIngresoDeposito::class)->contenido($s->id))->toBe($pdf);
    Notification::assertSentToTimes(User::find($s->investigador_id), CodigoQrDisponibleNotification::class, 2); // Campana y transporte separados.
    expect(DB::table(IngresoPendienteDeposito::TABLA)->where('id', $r->id)->value('intentos'))->toBe(1);
});

test('el despachador recupera trabajos perdidos y no llena la cola durante una espera', function (): void {
    Bus::fake();
    $s = depositoArquitectura();
    app(IngresoPendienteDeposito::class)->registrar($s->id);
    $this->artisan('depositos:ingresos-pendientes')->assertSuccessful();
    $this->artisan('depositos:ingresos-pendientes')->assertSuccessful();
    expect(Bus::dispatched(ProcesarIngresoDeposito::class)->where('solicitudId', $s->id))->toHaveCount(1);
    $this->travel(7)->minutes();
    $this->artisan('depositos:ingresos-pendientes')->assertSuccessful();
    expect(Bus::dispatched(ProcesarIngresoDeposito::class)->where('solicitudId', $s->id))->toHaveCount(2);
});

test('una escritura fallida conserva la aprobación y se detiene tras tres intentos', function (): void {
    $s = depositoArquitectura();
    $pendientes = app(IngresoPendienteDeposito::class);
    $pendientes->registrar($s->id);
    Storage::shouldReceive('disk')->andThrow(new RuntimeException('Almacenamiento no disponible'));
    for ($i = 0; $i < 3; $i++) {
        $pendientes->procesar($s->id);
        $this->travel(3)->minutes();
    }
    $pendientes->procesar($s->id);
    $r = DB::table(IngresoPendienteDeposito::TABLA)->where('solicitud_id', $s->id)->first();
    expect($r->estado)->toBe('fallido')->and($r->intentos)->toBe(3)->and($r->correo_estado)->toBe('pendiente')
        ->and($s->refresh()->estado)->toBe('Aprobada Documentalmente')->and($s->documento_ingreso_ruta)->toBeNull();
    Notification::assertNotSentTo(User::find($s->investigador_id), CodigoQrDisponibleNotification::class);
});

test('correo interrumpido requiere revisión explícita del Curador y nunca reenvía automáticamente', function (): void {
    $s = depositoArquitectura();
    $pendientes = app(IngresoPendienteDeposito::class);
    $pendientes->registrar($s->id);
    DB::table(IngresoPendienteDeposito::TABLA)->where('solicitud_id', $s->id)->update(['estado' => 'procesando', 'correo_estado' => 'enviando', 'ocupado_hasta' => now()->subMinute()->toIso8601String()]);
    $pendientes->procesar($s->id);
    expect(DB::table(IngresoPendienteDeposito::TABLA)->where('solicitud_id', $s->id)->value('estado'))->toBe('revision_necesaria');
    $tecnico = User::factory()->receptor()->create();
    Livewire::actingAs($tecnico)->test(ValidarIngresoQr::class, ['id' => $s->id, 'codigo' => $s->codigo_qr])
        ->set('motivoReintento', 'Revisado')->set('aceptarPosibleDuplicado', true)->call('reintentarAviso')->assertForbidden();
    $curador = User::factory()->curador()->create();
    expect(fn () => $pendientes->reintentar($s->id, $curador, 'Revisado'))->toThrow(HttpException::class);
    $pendientes->reintentar($s->id, $curador, 'Se revisó la bandeja de salida.', true);
    $r = DB::table(IngresoPendienteDeposito::TABLA)->where('solicitud_id', $s->id)->first();
    expect($r->estado)->toBe('pendiente')->and(json_decode($r->historial_reintentos, true)[0]['actor_id'])->toBe((string) $curador->id);
    Notification::assertNothingSent();
});

test('un expediente con acta firmada conserva descargas y bloquea acciones de ingreso incluso por llamada directa', function (): void {
    $s = depositoArquitectura(['recepcion_qr_en' => now(), 'contenido_validado_en' => now()]);
    $pdf = app(DocumentoIngresoDeposito::class)->contenido($s->id);
    RecepcionLoteEloquentModel::create(['id' => (string) Str::uuid(), 'solicitud_deposito_id' => $s->id, 'codigo_qr' => $s->codigo_qr,
        'tipo_tramite' => 'Depósito', 'estado' => 'Verificado Físicamente', 'acta_firmada_ruta' => 'historica/acta.pdf', 'firmada_en' => now()]);
    $dueno = User::findOrFail($s->investigador_id);
    expect($s->refresh()->flujoIngreso()->puedeRecibir())->toBeFalse()->and($s->estadoPresentacion())->toBe('Finalizada · acta firmada');
    $this->actingAs($dueno)->get(route('prestamos.investigador.deposito.detalle', $s->id))->assertOk()
        ->assertDontSee('Anunciar entrega')->assertDontSee('¡Solicitud aprobada! Tu Código QR está listo')
        ->assertSee('Consultar documento de ingreso del expediente');
    Livewire::actingAs($dueno)->test(DetalleDeposito::class, ['id' => $s->id])->call('anunciarEntrega')->assertStatus(409);
    $this->get(route('depositos.ingreso.documento', $s->id))->assertOk()->assertContent($pdf);
});

test('origen exige localidad INEC provincial activa y guarda una finalidad separada sin aceptar consultoría sin permisos', function (): void {
    $s = depositoArquitectura(['estado' => 'En Borrador', 'codigo_qr' => null]);
    $codigo = 'QA-ARQ-'.Str::uuid();
    DB::table(CatalogoLocalidadesEcuador::TABLA)->insert([
        'codigo' => $codigo, 'provincia_codigo' => '17', 'nombre' => 'Sitio QA INEC', 'busqueda' => 'sitio qa inec',
        'activo' => true, 'fuente' => 'QA', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $handler = app(ActualizarOrigenSolicitudDepositoHandler::class);
    $input = fn (?string $localidad, string $provincia = 'Pichincha', string $finalidad = 'Investigación', string $situacion = 'Sin permisos del MAE') => new ActualizarOrigenSolicitudDepositoInput(
        $s->id, 'Nacional (Ecuador)', $situacion, $provincia, $localidad, null, $finalidad,
    );
    expect(fn () => $handler($input(null)))->toThrow(DomainException::class);
    expect(fn () => $handler($input($codigo, 'Pastaza')))->toThrow(DomainException::class);
    expect(fn () => $handler($input($codigo, finalidad: 'Consultoría')))->toThrow(DomainException::class);
    $handler($input($codigo));
    expect($s->refresh()->localidad)->toBe('Sitio QA INEC')->and($s->localidad_origen_codigo)->toBe($codigo)
        ->and($s->finalidad_actividad)->toBe('Investigación')->and($s->tipo_tramite)->toBe('Depósito');
    DB::table(CatalogoLocalidadesEcuador::TABLA)->where('codigo', $codigo)->update(['activo' => false]);
    expect(fn () => $handler($input($codigo)))->toThrow(DomainException::class);
});

test('el envío del correo real usa solo el transporte local de prueba y el PDF aprobado como adjunto', function (): void {
    Notification::swap(new ChannelManager(app()));
    config(['mail.default' => 'array', 'webpush.vapid.subject' => null, 'queue.default' => 'sync', 'hubdigital.validation_mode' => false]);
    $s = depositoArquitectura();
    $pendientes = app(IngresoPendienteDeposito::class);
    $pendientes->registrar($s->id);
    $pendientes->procesar($s->id);
    $r = DB::table(IngresoPendienteDeposito::TABLA)->where('solicitud_id', $s->id)->first();
    expect($r->estado)->toBe('completado')->and($r->correo_estado)->toBe('aceptado_transporte');
    $mensajes = app('mailer')->getSymfonyTransport()->messages();
    expect($mensajes)->toHaveCount(1);
    $mensaje = $mensajes->first()->getOriginalMessage();
    expect($mensaje->getAttachments())->toHaveCount(1)->and($mensaje->getAttachments()[0]->getBody())->toBe(app(DocumentoIngresoDeposito::class)->contenido($s->id));
    expect(DB::table('notifications')->where('notifiable_id', $s->investigador_id)->where('type', CodigoQrDisponibleNotification::class)->count())->toBe(1);
});

test('el flujo nuevo no permite saltar la recepción por QR ni validar contenido mediante el endpoint histórico', function (): void {
    $s = depositoArquitectura(['finalidad_actividad' => 'Investigación']);
    $tecnico = User::factory()->receptor()->create();
    expect(fn () => app(IniciarRecepcionLoteHandler::class)(
        new IniciarRecepcionLoteInput($s->id, $tecnico->id),
    ))->toThrow(DomainException::class);
    app(RecepcionQrDeposito::class)->decidir($s->id, $s->codigo_qr, $tecnico, true);
    expect(fn () => app(AprobarRecepcionLoteHandler::class)(
        new AprobarRecepcionLoteInput($s->id, $tecnico->id, []),
    ))->toThrow(DomainException::class, 'registra y valida su contenido');
    expect(fn () => app(AceptarRecepcionConObservacionesHandler::class)(
        new AceptarRecepcionConObservacionesInput($s->id, $tecnico->id, [], 'Observación de prueba'),
    ))->toThrow(DomainException::class, 'registra y valida su contenido');
    expect(DB::table('recepciones.recepcion_lotes')->where('solicitud_deposito_id', $s->id)->value('estado'))->toBe('En Verificación');
    expect(fn () => app(GenerarActaRecepcionHandler::class)(
        new GenerarActaRecepcionInput($s->id, User::factory()->curador()->create()->id),
    ))->toThrow(DomainException::class);
});

test('el dashboard presenta la entrega no recibida y el cierre sin consultar una recepción por cada fila', function (): void {
    $dueno = User::factory()->depositante()->create();
    $cerrado = depositoArquitectura(['investigador_id' => $dueno->id, 'recepcion_qr_en' => now(), 'contenido_validado_en' => now()]);
    RecepcionLoteEloquentModel::create(['id' => (string) Str::uuid(), 'solicitud_deposito_id' => $cerrado->id, 'codigo_qr' => $cerrado->codigo_qr,
        'tipo_tramite' => 'Depósito', 'estado' => 'Verificado Físicamente', 'acta_firmada_ruta' => 'historica/acta.pdf', 'firmada_en' => now()]);
    depositoArquitectura(['investigador_id' => $dueno->id, 'recepcion_qr_rechazada_en' => now()]);
    depositoArquitectura(['investigador_id' => $dueno->id, 'recepcion_qr_en' => now()]);
    depositoArquitectura(['investigador_id' => $dueno->id]);
    DB::enableQueryLog();
    DB::flushQueryLog();
    Livewire::actingAs($dueno)->test(\App\Livewire\Dashboard::class)
        ->assertSee('Entrega no recibida · pendiente de Curaduría')->assertSee('Finalizada · acta firmada')
        ->assertSee('Recibida · pendiente de validar contenido');
    $consultas = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'from "recepciones"."recepcion_lotes"'));
    DB::disableQueryLog();
    expect($consultas)->toHaveCount(1);
});
