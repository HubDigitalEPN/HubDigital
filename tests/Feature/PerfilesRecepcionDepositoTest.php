<?php

declare(strict_types=1);

use App\Enums\RolUsuario;
use App\Livewire\CapturaDarwinCore;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\GestionPrestamosRecepciones\Infrastructure\Adapters\NotificacionCuratoriaAdapter;
use Modules\GestionPrestamosRecepciones\Infrastructure\Notifications\CodigoQrDisponibleNotification;
use Modules\GestionPrestamosRecepciones\Infrastructure\Notifications\LoteRecibidoParaActaNotification;
use Modules\GestionPrestamosRecepciones\Infrastructure\Notifications\NuevaSolicitudPorRevisarNotification;
use Modules\GestionPrestamosRecepciones\Infrastructure\Notifications\SolicitudEnviadaNotification;
use Modules\GestionPrestamosRecepciones\Infrastructure\Notifications\SolicitudRechazadaNotification;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel as Solicitud;
use Modules\GestionPrestamosRecepciones\Infrastructure\Services\DocumentoIngresoDeposito;
use Modules\GestionPrestamosRecepciones\Infrastructure\Services\RecepcionQrDeposito;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador\ContenidoRecepcion;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador\ValidarIngresoQr;

uses(Tests\DatabaseFeatureTestCase::class);

function solicitudIngresoQa(User $dueno, array $datos = []): Solicitud
{
    return Solicitud::create([
        'id' => (string) Str::uuid(), 'numero' => 'MEPN-DEP-'.random_int(10000, 99999),
        'investigador_id' => $dueno->id, 'tipo_tramite' => 'Depósito',
        'estado' => 'Aprobada Documentalmente', 'codigo_qr' => 'LOTE-'.strtoupper(Str::random(6)),
        'aprobada_en' => now(), 'solicitud_nombre_permiso' => 'Titular de prueba',
        'solicitud_proyecto' => 'Proyecto de prueba', 'solicitud_institucion' => 'Institución sintética',
        'grupo_animal' => 'Insectos', 'nro_lotes' => 1, 'nro_individuos' => 2,
        'documentos_adjuntos' => [], 'datos_faltantes' => [], ...$datos,
    ]);
}

beforeEach(function (): void {
    Notification::fake();
    Storage::fake('local');
    config(['deposit-storage.driver' => 'local', 'deposit-storage.require_remote' => false]);
});

test('hay cinco perfiles operativos y los alias conservan préstamos y recepción', function (): void {
    expect(array_map(fn ($rol) => $rol->etiqueta(), RolUsuario::cases()))->toBe([
        'Depositante', 'Administrador (Curador)', 'Técnico', 'Pasante', 'Administrador-Creador',
    ]);
    expect(RolUsuario::desdeHistorico('PRESTAMISTA'))->toBe(RolUsuario::DEPOSITANTE)
        ->and(RolUsuario::desdeHistorico('RECEPTOR'))->toBe(RolUsuario::AYUDANTE_CURADOR);
    $dueno = User::factory()->depositante()->create();
    expect($dueno->esPrestamista())->toBeTrue()->and($dueno->esCurador())->toBeFalse();
    expect(User::factory()->receptor()->create()->esReceptor())->toBeTrue();
    $this->actingAs($dueno)->get(route('prestamos.investigador.mis-solicitudes'))->assertOk();
    $this->actingAs($dueno)->get(route('prestamos.investigador.mis-depositos'))->assertOk();
});

test('el público puede descargar selección y análisis y no el catálogo completo', function (): void {
    expect(\App\Support\DescargasCatalogo::permitidas(boton: 'catalogo.resultados'))->toBeTrue()
        ->and(\App\Support\DescargasCatalogo::permitidas(boton: 'catalogo.analisis'))->toBeTrue()
        ->and(\App\Support\DescargasCatalogo::permitidas(boton: 'catalogo.completo'))->toBeFalse()
        ->and(\App\Support\DescargasCatalogo::permitidas(boton: 'catalogo.ipt'))->toBeFalse();
    $this->get(route('portal.descarga', ['exportacion' => 'datos']))->assertForbidden();
    $this->get(route('portal.descarga', ['exportacion' => 'riqueza']))->assertOk();
    $externo = User::factory()->depositante()->create();
    DB::table('usuarios.configuracion_catalogo')->where('clave', 'descargas_autoservicio')->update(['habilitado' => true]);
    DB::table('usuarios.permisos_botones')->updateOrInsert(['boton' => 'catalogo.ipt', 'rol' => 'DEPOSITANTE'], ['ver' => true, 'usar' => true]);
    expect(\App\Support\DescargasCatalogo::permitidas($externo, 'catalogo.ipt'))->toBeFalse();

});

test('el Pasante no se autoasigna ni accede a administración recepción o importaciones', function (): void {
    $pasante = User::factory()->create(['rol' => RolUsuario::PASANTE]);
    foreach (['admin.usuarios', 'prestamos.receptor.depositos', 'inventario.taxonomia.importar'] as $ruta) {
        $this->actingAs($pasante)->get(route($ruta))->assertForbidden();
    }
    $this->actingAs($pasante)->get(route('dashboard'))->assertOk()->assertSee('Captura Darwin Core');
    $this->get(route('inventario.captura-dwc'))->assertOk();
    $this->actingAs(User::factory()->depositante()->create())->get(route('roles.activar', 'PASANTE'))->assertNotFound();
    auth()->logout();
    $this->post('/register', ['first_name' => 'Prueba', 'last_name' => 'Pública', 'email' => 'pasante-publico@example.test',
        'password' => 'UnaClaveSegura1!', 'password_confirmation' => 'UnaClaveSegura1!', 'rol' => 'PASANTE'])->assertSessionHasErrors('rol');
});

test('activar el alias histórico conserva Depositante y los perfiles internos no se autoasignan ese rol', function (): void {
    $dueno = User::factory()->depositante()->create();
    $this->actingAs($dueno)->get(route('roles.activar', 'PRESTAMISTA'))->assertRedirect(route('dashboard'));
    expect($dueno->refresh()->rol)->toBe(RolUsuario::DEPOSITANTE);
    $pasante = User::factory()->create(['rol' => RolUsuario::PASANTE]);
    $this->actingAs($pasante)->get(route('roles.activar', 'DEPOSITANTE'))->assertForbidden();
    expect($pasante->refresh()->rol)->toBe(RolUsuario::PASANTE);
});

test('la captura guarda datos DwC y bloquea occurrenceID y campos internos aunque se manipule Livewire', function (): void {
    $pasante = User::factory()->create(['rol' => RolUsuario::PASANTE]);
    $editor = Livewire::actingAs($pasante)->test(CapturaDarwinCore::class)->call('nuevo')
        ->set('datos.taxonNombre', 'Neoponera apicalis')->set('datos.colector', 'Colector de prueba')
        ->set('datos.country', 'Ecuador')->set('datos.fechaColecta', '2026-10-30')
        ->set('datos.decimalLatitude', '-0.2')->set('datos.decimalLongitude', '-78.5')
        ->call('guardar')->assertHasNoErrors();
    $id = $editor->get('id');
    $fila = DB::table('taxonomia.especimenes')->where('id', $id)->first();
    expect($fila->country)->toBe('Ecuador')->and($fila->occurrence_id)->toBe('urn:uuid:'.$id);
    expect(DB::table('taxonomia.historial_columnas_especimen')->where('especimen_id', $id)->count())->toBeGreaterThan(0);
    Livewire::actingAs($pasante)->test(CapturaDarwinCore::class)->call('seleccionar', $id)
        ->set('datos.country', 'Perú')->call('guardar')->assertHasNoErrors();
    expect(DB::table('taxonomia.especimenes')->where('id', $id)->value('country'))->toBe('Perú');
    foreach (['occurrenceId', 'recordCreatedBy', 'iptUpload', 'estadoRevision', 'extra_interno'] as $clave) {
        Livewire::actingAs($pasante)->test(CapturaDarwinCore::class)->call('seleccionar', $id)
            ->set('datos.'.$clave, 'MANIPULADO')->call('guardar')->assertForbidden();
    }
    expect(DB::table('taxonomia.especimenes')->where('id', $id)->value('occurrence_id'))->toBe($fila->occurrence_id);
});

test('envío avisa al dueño curador Técnico y creador sin incluir Pasantes', function (): void {
    $dueno = User::factory()->depositante()->create();
    $curador = User::factory()->curador()->create();
    $tecnico = User::factory()->receptor()->create();
    $creador = User::factory()->administrador()->create();
    $pasante = User::factory()->create(['rol' => RolUsuario::PASANTE]);
    $s = solicitudIngresoQa($dueno, ['estado' => 'Pendiente de Revisión por Curaduría']);
    app(NotificacionCuratoriaAdapter::class)->notificarNuevaSolicitudPorRevisar($s->id);
    Notification::assertSentTo($dueno, SolicitudEnviadaNotification::class);
    foreach ([$curador, $tecnico, $creador] as $usuario) Notification::assertSentTo($usuario, NuevaSolicitudPorRevisarNotification::class);
    Notification::assertNotSentTo($pasante, NuevaSolicitudPorRevisarNotification::class);
});

test('el ingreso es un único PDF íntegro adjunto al correo y privado por solicitud', function (): void {
    $dueno = User::factory()->depositante()->create();
    $s = solicitudIngresoQa($dueno);
    $documentos = app(DocumentoIngresoDeposito::class);
    $pdf = $documentos->contenido($s->id);
    expect(str_starts_with($pdf, '%PDF-'))->toBeTrue()->and($documentos->contenido($s->id))->toBe($pdf);
    $s->refresh();
    expect($s->documento_ingreso_sha256)->toBe(hash('sha256', $pdf));
    $texto = (new \Smalot\PdfParser\Parser)->parseContent($pdf)->getText();
    expect($texto)->toContain($s->numero, $s->codigo_qr, 'Documento de ingreso', \Modules\GestionPrestamosRecepciones\Presentation\Support\FechaEcuador::formatear($s->aprobada_en));
    $correo = (new CodigoQrDisponibleNotification($s->id, $s->numero, $s->codigo_qr, $s->tipo_tramite))->toMail($dueno);
    expect($correo->rawAttachments)->toHaveCount(1)->and($correo->rawAttachments[0]['data'])->toBe($pdf);
    $this->actingAs($dueno)->get(route('depositos.ingreso.documento', $s->id))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    // El documento nuevo conserva también la impresión individual histórica.
    $this->get(route('prestamos.deposito.qr', $s->id))->assertOk()->assertSee($s->codigo_qr)->assertSee('window.print()', false);
    $this->get(route('depositos.ingreso.descargar', $s->id))->assertOk()->assertSee('data-documento-ingreso-listo', false)->assertSee('descargar.click()', false);
    expect((new CodigoQrDisponibleNotification($s->id, $s->numero, $s->codigo_qr, $s->tipo_tramite))->toArray($dueno)['url'])->toBe(route('depositos.ingreso.descargar', $s->id));
    $this->actingAs(User::factory()->depositante()->create())->get(route('depositos.ingreso.documento', $s->id))->assertForbidden();
    $this->get(route('prestamos.deposito.qr', $s->id))->assertForbidden();
    Storage::disk('local')->put($s->documento_ingreso_ruta, 'alterado');
    expect(fn () => $documentos->contenido($s->id))->toThrow(\RuntimeException::class);
});

test('el QR exige firma y Técnico autenticado y abrirlo no recibe el material', function (): void {
    $s = solicitudIngresoQa(User::factory()->depositante()->create());
    $tecnico = User::factory()->receptor()->create();
    $url = url(app(DocumentoIngresoDeposito::class)->urlQr($s));
    $this->actingAs($tecnico)->get($url)->assertOk()->assertSee('Aprobar recepción')->assertSee($s->codigo_qr);
    expect($s->refresh()->recepcion_qr_en)->toBeNull();
    $this->get(str_replace('signature=', 'signature=x', $url))->assertForbidden();
    $this->actingAs(User::factory()->depositante()->create())->get($url)->assertForbidden();
    $this->actingAs(User::factory()->create(['rol' => RolUsuario::PASANTE]))->get($url)->assertForbidden();
});

test('el Técnico recibe por QR una sola vez y solo cierra tras tabular y verificar todo el contenido', function (): void {
    $dueno = User::factory()->depositante()->create();
    $curador = User::factory()->curador()->create();
    $tecnico = User::factory()->receptor()->create();
    $creador = User::factory()->administrador()->create();
    $s = solicitudIngresoQa($dueno, ['curador_responsable' => $tecnico->id]);
    $recepcion = app(RecepcionQrDeposito::class);
    $recepcion->decidir($s->id, $s->codigo_qr, $tecnico, true);
    $recepcion->decidir($s->id, $s->codigo_qr, $tecnico, true);
    expect(DB::table('recepciones.recepcion_lotes')->where('solicitud_deposito_id', $s->id)->count())->toBe(1);
    expect($s->refresh()->recepcion_qr_por)->toBe($tecnico->id)->and($s->contenido_validado_en)->toBeNull();
    Notification::assertNotSentTo($curador, LoteRecibidoParaActaNotification::class);
    $pantalla = Livewire::actingAs($tecnico)->test(ContenidoRecepcion::class, ['id' => $s->id]);
    $pantalla->set('contenidoCompleto', true)->set('criterios', [true, true, true, true])->call('finalizar')->assertHasErrors('contenidoCompleto');
    $pantalla->set('identificador', 'Recipiente 1')->set('cantidad', 2)->set('scientificName', 'Neoponera apicalis')
        ->call('guardarRegistro')->assertHasNoErrors();
    $pantalla->set('contenidoCompleto', true)->set('criterios', [true, true, true, true])->call('finalizar')->assertHasNoErrors();
    expect($s->refresh()->contenido_validado_por)->toBe($tecnico->id)->and($s->contenido_validado_en)->not->toBeNull();
    Notification::assertSentTo($curador, LoteRecibidoParaActaNotification::class);
    Notification::assertSentTo($creador, LoteRecibidoParaActaNotification::class);
    expect($s->estadoPresentacion())->toBe('Recibida y validada');
    $this->actingAs($dueno)->get(route('prestamos.investigador.mis-depositos'))->assertOk()->assertSee('Recibida y validada');
    $this->get(route('dashboard'))->assertOk()->assertSee('Recibida y validada');
    $this->actingAs($tecnico)->get(route('prestamos.receptor.depositos', ['vista' => 'historial']))->assertOk()->assertSee('Recibida y validada');
    $pantalla->set('identificador', 'No permitido')->set('scientificName', 'Otra especie')->call('guardarRegistro')->assertStatus(409);
    expect(DB::table('recepciones.contenido_recepcion')->where('solicitud_id', $s->id)->count())->toBe(1);
});

test('rechazar por QR exige motivo y bloquea recepción posterior conservando el historial', function (): void {
    $dueno = User::factory()->depositante()->create();
    $s = solicitudIngresoQa($dueno);
    $tecnico = User::factory()->receptor()->create();
    $pantalla = Livewire::actingAs($tecnico)->test(ValidarIngresoQr::class, ['id' => $s->id, 'codigo' => $s->codigo_qr]);
    $pantalla->call('rechazar')->assertHasErrors('motivo');
    $pantalla->set('motivo', 'El material no corresponde al documento presentado.')->call('rechazar')->assertHasNoErrors();
    expect($s->refresh()->estado)->toBe('Rechazo Permanente')->and($s->recepcion_qr_motivo)->toContain('no corresponde');
    Notification::assertSentTo($dueno, SolicitudRechazadaNotification::class);
    $pantalla->call('aprobar')->assertStatus(409);
    expect(DB::table('recepciones.recepcion_lotes')->where('solicitud_deposito_id', $s->id)->exists())->toBeFalse();
});


test('el paso uno abre en pantalla el PDF firmado íntegro sin alterar el documento', function (): void {
    $s = solicitudColaR2ParaPruebas();
    $dueno = User::findOrFail($s->investigador_id);
    $pdf = app(\Modules\GestionPrestamosRecepciones\Presentation\Support\GeneradorPdfSolicitudDeposito::class)->generar($s);
    $ruta = 'prueba-preview/'.$s->id.'.pdf';
    Storage::disk('local')->put($ruta, $pdf);
    $s->forceFill(['solicitud_firmada_ruta' => $ruta, 'solicitud_firmada_sha256' => hash('sha256', $pdf), 'solicitud_firmada_en' => now()])->save();
    Livewire::actingAs($dueno)->test(\Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador\RegistroSolicitudDeposito::class)
        ->assertSee('Ver documento firmado')->call('visualizarPdfSolicitud')->assertSet('mostrarVistaPdfSolicitud', true)
        ->assertSee('PDF completo de la solicitud de depósito');
    $this->get(route('depositos.solicitud.documento', $s->id))->assertOk()->assertContent($pdf);
    expect(hash('sha256', Storage::disk('local')->get($ruta)))->toBe($s->solicitud_firmada_sha256);
});

test('la notificación y el QR conservan su destino al iniciar sesión sin permitir redirecciones externas', function (): void {
    $tecnico = User::factory()->receptor()->create();
    $s = solicitudIngresoQa(User::factory()->depositante()->create());
    $url = url(app(DocumentoIngresoDeposito::class)->urlQr($s));
    $this->withSession(['url.intended' => $url])->post(route('login.store'), ['email' => $tecnico->email, 'password' => 'password'])->assertRedirect($url);
    auth()->logout();
    $this->withSession(['url.intended' => 'https://example.org/depositos/ingreso/ajeno'])->post(route('login.store'), ['email' => $tecnico->email, 'password' => 'password'])
        ->assertRedirect(route('portal.catalogo', ['vista' => 'mapa']));
});

test('el Curador asigna un Pasante de confianza y no modifica ni crea cuentas Creador', function (): void {
    $curador = User::factory()->curador()->create();
    $dueno = User::factory()->depositante()->create();
    $creador = User::factory()->administrador()->create();
    Livewire::actingAs($curador)->test(\App\Livewire\Administracion\GestionUsuarios::class)->call('editar', $dueno->id)
        ->set('edicionRoles', [RolUsuario::PASANTE->value])->set('edicionRol', RolUsuario::PASANTE->value)->call('actualizar')->assertHasNoErrors();
    expect($dueno->fresh()->esPasante())->toBeTrue();
    Livewire::actingAs($curador)->test(\App\Livewire\Administracion\GestionUsuarios::class)->call('editar', $creador->id)->assertForbidden();
    Livewire::actingAs($curador)->test(\App\Livewire\Administracion\GestionUsuarios::class)->call('editar', $dueno->id)
        ->set('edicionRoles', ['ADMIN'])->set('edicionRol', 'ADMIN')->call('actualizar')->assertForbidden();
});
