<?php

use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
    // Solo la copia efímera creada por el paquete puede preparar usuarios vacíos.
    // La transacción revierte TRUNCATE/CASCADE y la reserva al finalizar cada caso.
    $prefijo = (string) getenv('HUBDIGITAL_TEST_DATABASE_PREFIX');
    $conexion = DB::connection();
    if (preg_match('/^hd_pkg_[a-f0-9]{12}$/D', $prefijo)
        && preg_match('/^'.preg_quote($prefijo, '/').'_[1-4](?:_test_[1-4])?$/D', $conexion->getDatabaseName())
        && $conexion->transactionLevel() > 0) {
        DB::statement('TRUNCATE TABLE usuarios.users CASCADE');
        DB::table('usuarios.admin_bootstrap')->delete();
    }
    // El alta inicial sólo existe antes de crear la primera cuenta. La base
    // local de desarrollo conserva usuarios, así que este flujo requiere una
    // instalación vacía (por ejemplo, la validación de instalación en OCI).
    if (User::query()->exists()) {
        $this->markTestSkipped('El bootstrap inicial requiere una base sin usuarios.');
    }
    config()->set('administracion.bootstrap', [
        'enabled' => true,
        'token' => 'token-efimero-de-prueba-no-real',
        'expires_at' => now()->addMinutes(10)->toIso8601String(),
        'email' => 'adrian.troya@epn.edu.ec',
        'allowed_hosts' => ['localhost', 'dev.labinvepn.org'],
        'allowed_environments' => ['testing'],
    ]);
});

test('the development bootstrap creates the only initial admin from the web form', function () {
    $this->get(route('admin.bootstrap.create'))
        ->assertOk()
        ->assertSee('data-testid="admin-bootstrap-form"', false)
        ->assertSee('adrian.troya@epn.edu.ec');

    $this->post(route('admin.bootstrap.store'), [
        'bootstrap_token' => 'token-efimero-de-prueba-no-real',
        'first_name' => 'Adrián',
        'last_name' => 'Troya',
        'email' => 'adrian.troya@epn.edu.ec',
        'password' => 'Temporal-Segura-2026!',
        'password_confirmation' => 'Temporal-Segura-2026!',
    ])->assertSessionHasNoErrors()
        ->assertRedirect(route('login'));

    $admin = User::query()->where('email_normalizado', 'adrian.troya@epn.edu.ec')->sole();

    expect($admin->rolesAsignados()->all())->toBe([RolUsuario::ADMIN])
        ->and($admin->rol)->toBe(RolUsuario::ADMIN)
        ->and($admin->hasVerifiedEmail())->toBeFalse()
        ->and(Hash::check('Temporal-Segura-2026!', $admin->password))->toBeTrue()
        ->and($admin->password)->not->toBe('Temporal-Segura-2026!');

    Notification::assertSentTo($admin, VerifyEmail::class);
    $this->get(route('admin.bootstrap.create'))->assertNotFound();
    $this->post(route('admin.bootstrap.store'), [])->assertNotFound();

    // La reserva no depende de conservar la cuenta ni de una caché temporal.
    $admin->delete();
    $this->get(route('admin.bootstrap.create'))->assertNotFound();
});

test('bootstrap rejects an invalid token without revealing its expected value', function () {
    $this->post(route('admin.bootstrap.store'), [
        'bootstrap_token' => 'incorrecto',
        'first_name' => 'Adrián',
        'last_name' => 'Troya',
        'email' => 'adrian.troya@epn.edu.ec',
        'password' => 'Temporal-Segura-2026!',
        'password_confirmation' => 'Temporal-Segura-2026!',
    ])->assertSessionHasErrors('bootstrap_token');

    expect(User::query()->exists())->toBeFalse();
});

test('bootstrap cannot create a different email or run after expiration', function () {
    $this->post(route('admin.bootstrap.store'), [
        'bootstrap_token' => 'token-efimero-de-prueba-no-real',
        'first_name' => 'Otra',
        'last_name' => 'Persona',
        'email' => 'otra.persona@epn.edu.ec',
        'password' => 'Temporal-Segura-2026!',
        'password_confirmation' => 'Temporal-Segura-2026!',
    ])->assertSessionHasErrors('bootstrap_token');

    config()->set('administracion.bootstrap.expires_at', now()->subMinute()->toIso8601String());

    $this->get(route('admin.bootstrap.create'))->assertNotFound();
    expect(User::query()->exists())->toBeFalse();
});

test('bootstrap is unreachable from an unlisted host', function () {
    // Una URL absoluta evita que el helper de Laravel vuelva a usar el host
    // de APP_URL al construir la petición y sobrescriba HTTP_HOST.
    $this->get('http://labinvepn.org/instalacion/administrador')->assertNotFound();
    $this->post('http://labinvepn.org/instalacion/administrador', [])->assertNotFound();
});

test('bootstrap never flashes its token or passwords after validation fails', function () {
    $datos = [
        'bootstrap_token' => 'token-efimero-de-prueba-no-real',
        'first_name' => '',
        'last_name' => 'Troya',
        'email' => 'adrian.troya@epn.edu.ec',
        'password' => 'Secreto-No-Repetir-2026!',
        'password_confirmation' => 'Secreto-No-Repetir-2026!',
    ];

    $this->from(route('admin.bootstrap.create'))->post(route('admin.bootstrap.store'), $datos)
        ->assertSessionHasErrors('first_name')
        ->assertSessionMissing('_old_input.bootstrap_token')
        ->assertSessionMissing('_old_input.password')
        ->assertSessionMissing('_old_input.password_confirmation');

    $this->get(route('admin.bootstrap.create'))->assertOk()
        ->assertDontSee($datos['bootstrap_token'], false)
        ->assertDontSee($datos['password'], false);

    expect(DB::table('usuarios.admin_bootstrap')->exists())->toBeFalse();
});

test('bootstrap fails closed for disabled, missing, expired or invalid configuration', function () {
    foreach ([
        ['enabled' => false],
        ['token' => ''],
        ['expires_at' => ''],
        ['expires_at' => 'fecha-imposible'],
        ['expires_at' => now()->subMinute()->toIso8601String()],
        ['allowed_environments' => ['local']],
    ] as $cambio) {
        $original = config('administracion.bootstrap');
        config()->set('administracion.bootstrap', [...$original, ...$cambio]);
        $this->get(route('admin.bootstrap.create'))->assertNotFound();
        config()->set('administracion.bootstrap', $original);
    }
});

test('bootstrap rolls back its one-shot reservation when the account cannot be created', function () {
    config()->set('auth.internal_email_domains', ['otro.edu.ec']);

    $this->post(route('admin.bootstrap.store'), [
        'bootstrap_token' => 'token-efimero-de-prueba-no-real',
        'first_name' => 'Adrián',
        'last_name' => 'Troya',
        'email' => ' ADRIAN.TROYA@EPN.EDU.EC ',
        'password' => 'Inicial-Segura-2026!',
        'password_confirmation' => 'Inicial-Segura-2026!',
    ])->assertSessionHasErrors('email');

    expect(User::query()->exists())->toBeFalse()
        ->and(DB::table('usuarios.admin_bootstrap')->exists())->toBeFalse();

    Notification::assertNothingSent();
    $this->get(route('admin.bootstrap.create'))->assertOk();
});

test('a claimed bootstrap cannot be replayed while no administrator is visible', function () {
    DB::table('usuarios.admin_bootstrap')->insert([
        'id' => 'administrador-inicial',
        'created_at' => now(),
    ]);

    $this->post(route('admin.bootstrap.store'), [])->assertNotFound();
    expect(User::query()->exists())->toBeFalse();
});

test('unexpected bootstrap failures hide secrets from debug responses and logs', function () {
    config()->set('app.debug', true);
    $secreto = 'Secreto-No-Repetir-2026!';
    $token = config('administracion.bootstrap.token');
    Hash::shouldReceive('make')->once()->andThrow(new RuntimeException('SQL privado: '.$secreto.' '.$token));
    Log::spy();

    $this->post(route('admin.bootstrap.store'), [
        'bootstrap_token' => $token,
        'first_name' => 'Adrián',
        'last_name' => 'Troya',
        'email' => 'adrian.troya@epn.edu.ec',
        'password' => $secreto,
        'password_confirmation' => $secreto,
    ])->assertStatus(500)
        ->assertDontSee($secreto, false)
        ->assertDontSee($token, false)
        ->assertDontSee('SQL privado', false);

    Log::shouldHaveReceived('error')->once()->with('Fallo en administración de usuarios.', [
        'exception_type' => RuntimeException::class,
        'exception_code' => 0,
    ]);
    expect(DB::table('usuarios.admin_bootstrap')->exists())->toBeFalse();
    Notification::assertNothingSent();
});
