<?php

use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyFeature(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('production passwords accept eight characters and preserve the other password checks', function () {
    $environment = app()->environment();
    app()->instance('env', 'production');
    $this->mock(\Illuminate\Contracts\Validation\UncompromisedVerifier::class)
        ->shouldReceive('verify')->andReturnTrue();
    try {
        $validate = static fn (string $password): bool => \Illuminate\Support\Facades\Validator::make(
            ['password' => $password], ['password' => ['required', \Illuminate\Validation\Rules\Password::defaults()]],
        )->passes();
        expect($validate('Aa1!aaaa'))->toBeTrue()
            ->and($validate('Aa1!aaa'))->toBeFalse()
            ->and($validate('aaaaaaaa'))->toBeFalse();
    } finally {
        app()->instance('env', $environment);
    }
});

test('el registro público siempre ofrece Depositante y nunca perfiles internos', function (mixed $entrada): void {
    $response = $this->withSession(['_old_input' => ['rol' => 'ADMIN']])->get(route('register', ['rol' => $entrada]))->assertOk();
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $roles = (new DOMXPath($dom))->query('//input[@name="rol"]');
    expect($roles->length)->toBe(1)->and($roles->item(0)->getAttribute('value'))->toBe('DEPOSITANTE');
    $response->assertDontSee('name="rol" value="PASANTE"', false)->assertDontSee('name="rol" value="ADMIN"', false);
})->with([null, 'DEPOSITANTE', 'PRESTAMISTA', 'ADMIN', ['DEPOSITANTE']]);

test('registration renders turnstile and keeps submit disabled until verification', function () {
    config()->set('services.turnstile.enabled', true);
    config()->set('services.turnstile.site_key', 'site-key-for-test');

    $this->get(route('register'))
        ->assertOk()
        ->assertSee('class="cf-turnstile', false)
        ->assertSee('data-action="turnstile-spin-v2"', false)
        ->assertSee('data-size="flexible"', false)
        ->assertSee('x-bind:disabled="!turnstileVerified"', false);
});

test('registration validates the turnstile token before creating the account', function () {
    config()->set('services.turnstile.enabled', true);
    config()->set('services.turnstile.secret', 'secret-for-test');
    config()->set('services.turnstile.expected_hostname', 'dev.labinvepn.org');

    Http::fake([
        'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
            'success' => true,
            'hostname' => 'dev.labinvepn.org',
            'action' => 'turnstile-spin-v2',
        ]),
    ]);

    $this->post(route('register.store'), [
        'first_name' => 'Captcha',
        'last_name' => 'Validado',
        'email' => 'captcha@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'rol' => RolUsuario::DEPOSITANTE->value,
        'cargo' => 'Investigador', 'institucion' => 'Institución de prueba',
        'cf-turnstile-response' => 'valid-token',
    ])->assertSessionHasNoErrors();

    Http::assertSentCount(1);
    expect(User::query()->where('email_normalizado', 'captcha@example.com')->exists())->toBeTrue();
});

test('registration rejects a missing turnstile token without creating an account', function () {
    config()->set('services.turnstile.enabled', true);
    config()->set('services.turnstile.secret', 'secret-for-test');

    $this->post(route('register.store'), [
        'first_name' => 'Captcha',
        'last_name' => 'Ausente',
        'email' => 'sin-captcha@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'rol' => RolUsuario::DEPOSITANTE->value,
        'cargo' => 'Investigador', 'institucion' => 'Institución de prueba',
    ])->assertSessionHasErrors('cf-turnstile-response');

    expect(User::query()->where('email_normalizado', 'sin-captcha@example.com')->exists())->toBeFalse();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'rol' => RolUsuario::DEPOSITANTE->value,
        'cargo' => 'Investigador', 'institucion' => 'Institución de prueba',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
    expect(auth()->user()->rol)->toBe(RolUsuario::PRESTAMISTA);
});

test('public registration cannot create a curator account', function () {
    $response = $this->post(route('register.store'), [
        'first_name' => 'Curador',
        'last_name' => 'No Autorizado',
        'email' => 'curador-publico@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'rol' => RolUsuario::CURADOR->value,
    ]);

    $response->assertSessionHasErrors('rol');
    $this->assertGuest();
});

test('registration canonicalizes email and never stores a plaintext password', function () {
    $this->post(route('register.store'), [
        'first_name' => 'Ana',
        'last_name' => 'Pérez',
        'email' => '  ANA.PEREZ@EXAMPLE.COM ',
        'password' => 'password',
        'password_confirmation' => 'password',
        'rol' => RolUsuario::DEPOSITANTE->value,
        'cargo' => 'Investigador', 'institucion' => 'Institución de prueba',
    ])->assertSessionHasNoErrors();

    $user = User::query()->where('email_normalizado', 'ana.perez@example.com')->sole();

    expect($user->email)->toBe('ana.perez@example.com');
    expect($user->password)->not->toBe('password');
    expect(Hash::check('password', $user->password))->toBeTrue();
});

test('registration rejects a duplicate email regardless of case or spaces', function () {
    User::factory()->create(['email' => 'duplicado@example.com']);

    $this->post(route('register.store'), [
        'first_name' => 'Otra',
        'last_name' => 'Persona',
        'email' => ' DUPLICADO@EXAMPLE.COM ',
        'password' => 'password',
        'password_confirmation' => 'password',
        'rol' => RolUsuario::DEPOSITANTE->value,
        'cargo' => 'Consultora',
        'institucion' => 'Entidad de prueba',
    ])->assertSessionHasErrors('email');

    expect(User::query()->where('email_normalizado', 'duplicado@example.com')->count())->toBe(1);
    $this->assertGuest();
});

test('public registration rejects every internal role', function (RolUsuario $rol) {
    $this->post(route('register.store'), [
        'first_name' => 'Cuenta',
        'last_name' => 'No autorizada',
        'email' => strtolower($rol->value).'@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'rol' => $rol->value,
    ])->assertSessionHasErrors('rol');

    $this->assertGuest();
})->with([
    RolUsuario::CURADOR,
    RolUsuario::AYUDANTE_CURADOR,
    RolUsuario::PASANTE,
    RolUsuario::ADMIN,
]);
