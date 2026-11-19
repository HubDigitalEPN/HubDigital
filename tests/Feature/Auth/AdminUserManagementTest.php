<?php

use App\Enums\RolUsuario;
use App\Livewire\Administracion\GestionUsuarios;
use App\Models\User;
use App\Services\UserIdentityService;
use App\Support\Administracion\CreadorUsuario;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\GestionPrestamosRecepciones\Infrastructure\Adapters\NotificacionCuratoriaAdapter;
use Modules\GestionPrestamosRecepciones\Infrastructure\Notifications\NuevaSolicitudPorRevisarNotification;

beforeEach(function (): void {
    Notification::fake();
});

test('only an administrator can open user management', function () {
    $admin = User::factory()->administrador()->create();
    $depositante = User::factory()->depositante()->create();

    $this->actingAs($admin)->get(route('admin.usuarios'))
        ->assertOk()
        ->assertSee('data-testid="admin-users-page"', false)
        ->assertSee('aria-label="Ruta de navegación"', false)
        ->assertSee('Administración')->assertSee('Usuarios y perfiles')
        ->assertDontSee('Centro de administración');

    $this->actingAs($depositante)->get(route('admin.usuarios'))->assertForbidden();
});

test('administrator creates an internal user without email verification and requires an initial password change', function () {
    $admin = User::factory()->administrador()->create();

    Livewire::actingAs($admin)
        ->test(GestionUsuarios::class)
        ->set('mostrarFormulario', true)
        ->set('first_name', 'Receptora')
        ->set('last_name', 'EPN')
        ->set('email', ' recepcion.prueba@EPN.EDU.EC ')
        ->set('rol', RolUsuario::RECEPTOR->value)
        ->set('cargo', 'Técnica de recepción')
        ->set('institucion', 'Escuela Politécnica Nacional')
        ->call('crear', 'Inicial-Segura-2026!', 'Inicial-Segura-2026!')
        ->assertHasNoErrors()
        ->assertSet('mostrarFormulario', false)
        ->assertSee('Cuenta creada y habilitada. Al iniciar sesion debera cambiar su contrasena inicial.');

    $usuario = User::query()->where('email_normalizado', 'recepcion.prueba@epn.edu.ec')->sole();

    expect($usuario->rolesAsignados()->all())->toBe([RolUsuario::RECEPTOR])
        ->and($usuario->hasVerifiedEmail())->toBeTrue()
        ->and($usuario->must_change_password)->toBeTrue()
        ->and(Hash::check('Inicial-Segura-2026!', $usuario->password))->toBeTrue()
        ->and($usuario->password)->not->toBe('Inicial-Segura-2026!');

    Notification::assertNothingSent();
});

test('user management prevents duplicates and external emails for internal roles', function () {
    $admin = User::factory()->administrador()->create();
    User::factory()->depositante()->create(['email' => 'existente@example.org']);

    Livewire::actingAs($admin)
        ->test(GestionUsuarios::class)
        ->set('first_name', 'Cuenta')
        ->set('last_name', 'Duplicada')
        ->set('email', ' EXISTENTE@EXAMPLE.ORG ')
        ->set('rol', RolUsuario::DEPOSITANTE->value)
        ->call('crear', 'Inicial-Segura-2026!', 'Inicial-Segura-2026!')
        ->assertHasErrors('email');

    Livewire::actingAs($admin)
        ->test(GestionUsuarios::class)
        ->set('first_name', 'Cuenta')
        ->set('last_name', 'No institucional')
        ->set('email', 'curador@example.org')
        ->set('rol', RolUsuario::CURADOR->value)
        ->call('crear', 'Inicial-Segura-2026!', 'Inicial-Segura-2026!')
        ->assertHasErrors('email');

    expect(User::query()->where('email_normalizado', 'existente@example.org')->count())->toBe(1)
        ->and(User::query()->where('email_normalizado', 'curador@example.org')->exists())->toBeFalse();
});

test('administrator searches citizens and internal profiles', function () {
    $admin = User::factory()->administrador()->create();
    User::factory()->depositante()->create([
        'first_name' => 'Consultora',
        'last_name' => 'Visible',
        'email' => 'consultora@example.org',
        'institucion' => 'Museo de prueba',
    ]);
    User::factory()->receptor()->create([
        'first_name' => 'Personal',
        'last_name' => 'Interno',
        'email' => 'personal@epn.edu.ec',
    ]);

    Livewire::actingAs($admin)
        ->test(GestionUsuarios::class)
        ->set('busqueda', 'museo de prueba')
        ->assertSee('consultora@example.org')
        ->assertDontSee('personal@epn.edu.ec')
        ->set('busqueda', '')
        ->set('filtroRol', RolUsuario::RECEPTOR->value)
        ->assertSee('personal@epn.edu.ec')
        ->assertDontSee('consultora@example.org');
});

test('direct Livewire access requires a verified persisted administrator membership', function () {
    $depositante = User::factory()->depositante()->create();
    $sinVerificar = User::factory()->administrador()->unverified()->create();

    Livewire::test(GestionUsuarios::class)->assertForbidden();
    Livewire::actingAs($depositante)->test(GestionUsuarios::class)->assertForbidden();
    Livewire::actingAs($sinVerificar)->test(GestionUsuarios::class)->assertForbidden();
});

test('revoking administrator membership blocks an already open Livewire action', function () {
    $admin = User::factory()->administrador()->create();
    $componente = Livewire::actingAs($admin)->test(GestionUsuarios::class)
        ->set('first_name', 'Cuenta')
        ->set('last_name', 'Bloqueada')
        ->set('email', 'bloqueada@example.org');

    $admin->roles()->delete();

    $componente->call('crear', 'Inicial-Segura-2026!', 'Inicial-Segura-2026!')->assertForbidden();

    expect(User::query()->where('email_normalizado', 'bloqueada@example.org')->exists())->toBeFalse();
});

test('validation errors never serialize passwords into Livewire snapshots or effects', function () {
    $admin = User::factory()->administrador()->create();
    $secreto = 'Privada-No-Serializar-2026!';
    $confirmacion = 'Confirmacion-Distinta-2026!';

    $componente = Livewire::actingAs($admin)->test(GestionUsuarios::class)
        ->set('mostrarFormulario', true)
        ->set('first_name', 'Cuenta')
        ->set('last_name', 'Pendiente')
        ->set('email', 'pendiente@example.org')
        ->call('crear', $secreto, $confirmacion)
        ->assertHasErrors('password');

    $respuesta = json_encode([$componente->snapshot, $componente->effects, $componente->html()]);

    expect($componente->snapshot['data'])->not->toHaveKey('password')
        ->and($componente->snapshot['data'])->not->toHaveKey('password_confirmation')
        ->and($respuesta)->not->toContain($secreto)
        ->and($respuesta)->not->toContain($confirmacion);

    Notification::assertNothingSent();
});

test('a database duplicate is converted into validation without creating partial membership', function () {
    $cantidadInicial = User::query()->count();
    User::factory()->depositante()->create(['email' => 'duplicado@example.org']);
    $creador = app(CreadorUsuario::class);

    expect(fn () => $creador->crear([
        'first_name' => 'Cuenta',
        'last_name' => 'Duplicada',
        'email' => ' DUPLICADO@EXAMPLE.ORG ',
        'password' => 'Inicial-Segura-2026!',
    ], RolUsuario::DEPOSITANTE))->toThrow(ValidationException::class);

    expect(User::query()->count())->toBe($cantidadInicial + 1);
    Notification::assertNothingSent();
});

test('identity service recognizes administrator curatorial authority without fabricating membership', function () {
    $admin = User::factory()->administrador()->create();
    $identidad = app(UserIdentityService::class);

    expect($identidad->esCurador($admin->id))->toBeTrue()
        ->and($admin->esCurador())->toBeTrue()
        ->and($identidad->obtenerRoles($admin->id)->all())->toBe([RolUsuario::ADMIN]);
});

test('curatorial notifications include administrators and exclude citizens', function () {
    $admin = User::factory()->administrador()->create();
    $curador = User::factory()->curador()->create();
    $ciudadano = User::factory()->depositante()->create();

    app(NotificacionCuratoriaAdapter::class)->notificarNuevaSolicitudPorRevisar((string) Str::uuid());

    Notification::assertSentTo([$admin, $curador], NuevaSolicitudPorRevisarNotification::class);
    Notification::assertNotSentTo($ciudadano, NuevaSolicitudPorRevisarNotification::class);
});

test('passwords that resemble hashes are still treated as the entered password', function () {
    $entrada = Hash::make('Inicial-Segura-2026!');
    $usuario = app(CreadorUsuario::class)->crear([
        'first_name' => 'Cuenta',
        'last_name' => 'Hash',
        'email' => 'hash@example.org',
        'password' => $entrada,
        'cargo' => 'Investigadora',
        'institucion' => 'Institución de prueba',
    ], RolUsuario::DEPOSITANTE);

    expect(Hash::check($entrada, $usuario->password))->toBeTrue()
        ->and($usuario->password)->not->toBe($entrada);
});

test('administrator conserva el autoservicio consolidado sin duplicar la membresía', function () {
    $admin = User::factory()->administrador()->create();
    $usuario = User::factory()->prestamista()->create();

    Livewire::actingAs($admin)
        ->test(GestionUsuarios::class)
        ->call('editar', $usuario->id)
        ->set('edicionRoles', [RolUsuario::PRESTAMISTA->value, RolUsuario::DEPOSITANTE->value])
        ->set('edicionRol', RolUsuario::DEPOSITANTE->value)
        ->set('edicionCargo', 'Investigadora asociada')
        ->set('edicionInstitucion', 'Universidad de prueba')
        ->call('actualizar')
        ->assertHasNoErrors();

    $usuario->refresh()->load('roles');

    expect($usuario->rolesAsignados()->all())
        ->toBe([RolUsuario::DEPOSITANTE])
        ->and($usuario->rol)->toBe(RolUsuario::DEPOSITANTE)
        ->and($usuario->cargo)->toBe('Investigadora asociada');
});

test('administrator cannot assign investigator deposit membership without its requirements', function () {
    $admin = User::factory()->administrador()->create();
    $usuario = User::factory()->prestamista()->create();

    Livewire::actingAs($admin)
        ->test(GestionUsuarios::class)
        ->call('editar', $usuario->id)
        ->set('edicionRoles', [RolUsuario::PRESTAMISTA->value, RolUsuario::DEPOSITANTE->value])
        ->set('edicionRol', RolUsuario::DEPOSITANTE->value)
        ->set('edicionCargo', '')
        ->set('edicionInstitucion', '')
        ->call('actualizar')
        ->assertHasErrors(['cargo', 'institucion']);
});

test('long multibyte passwords produce validation instead of a hashing failure', function () {
    $admin = User::factory()->administrador()->create();
    $clave = str_repeat('ñ', 35).'Mayuscula1!';

    Livewire::actingAs($admin)->test(GestionUsuarios::class)
        ->set('first_name', 'Cuenta')
        ->set('last_name', 'Larga')
        ->set('email', 'larga@example.org')
        ->set('rol', RolUsuario::DEPOSITANTE->value)
        ->set('cargo', 'Investigadora')->set('institucion', 'Institución de prueba')
        ->call('crear', $clave, $clave)
        ->assertHasErrors('password');

    expect(User::query()->where('email_normalizado', 'larga@example.org')->exists())->toBeFalse();
});

test('administration requests use a generic error response and a log without sensitive details', function () {
    $admin = User::factory()->administrador()->create();
    config()->set('app.debug', true);
    Livewire::actingAs($admin)->test(GestionUsuarios::class);

    // El arnés de Livewire desactiva las excepciones inesperadas. Probamos el
    // manejador real con la petición que el componente acaba de marcar.
    expect(request()->attributes->get('administracion_sensible'))->toBeTrue();
    request()->headers->set('Accept', 'application/json');
    $error = new RuntimeException('SQL password=$2y$04$secreto-bootstrap-no-publicar');
    $handler = app(ExceptionHandler::class);
    Log::spy();
    $handler->report($error);
    $respuesta = $handler->render(request(), $error);

    expect($respuesta->getStatusCode())->toBe(500)
        ->and($respuesta->getContent())->not->toContain('secreto-bootstrap-no-publicar')
        ->and($respuesta->getContent())->not->toContain('SQL password');
    Log::shouldHaveReceived('error')->once()->with('Fallo en administración de usuarios.', [
        'exception_type' => RuntimeException::class,
        'exception_code' => 0,
    ]);
});
