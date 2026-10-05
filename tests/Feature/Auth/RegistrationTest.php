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

test('registration preselects only public purposes and preserves a previous form choice', function (mixed $entrada, string $esperado): void {
    $response = $this->get(route('register', ['rol' => $entrada]))->assertOk();
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $radios = (new DOMXPath($dom))->query('//input[@name="rol" and @checked]');
    expect($radios->length)->toBe(1)->and($radios->item(0)->getAttribute('value'))->toBe($esperado);

    $response = $this->withSession(['_old_input' => ['rol' => 'PRESTAMISTA']])
        ->get(route('register', ['rol' => 'DEPOSITANTE']))->assertOk();
    @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $radios = (new DOMXPath($dom))->query('//input[@name="rol" and @checked]');
    expect($radios->length)->toBe(1)->and($radios->item(0)->getAttribute('value'))->toBe('PRESTAMISTA');
})->with([
    'generic registration' => [null, 'PRESTAMISTA'],
    'deposit intent' => ['DEPOSITANTE', 'DEPOSITANTE'],
    'loan intent' => ['PRESTAMISTA', 'PRESTAMISTA'],
    'internal role rejected' => ['ADMIN', 'PRESTAMISTA'],
    'malformed query rejected' => [['DEPOSITANTE'], 'PRESTAMISTA'],
]);

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
        'rol' => RolUsuario::PRESTAMISTA->value,
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
        'rol' => RolUsuario::PRESTAMISTA->value,
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
        'rol' => RolUsuario::PRESTAMISTA->value,
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
        'rol' => RolUsuario::PRESTAMISTA->value,
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
    RolUsuario::RECEPTOR,
    RolUsuario::ADMIN,
]);
