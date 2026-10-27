<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Laravel\Fortify\Features;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
});

test('login requires the turnstile token in the browser and on the server', function () {
    config()->set('services.turnstile.enabled', true);
    config()->set('services.turnstile.site_key', 'site-key-for-test');
    config()->set('services.turnstile.secret', 'secret-for-test');

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('class="cf-turnstile', false)
        ->assertSee('data-action="turnstile-spin-v2"', false)
        ->assertSee('x-bind:disabled="!turnstileVerified"', false);

    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors('cf-turnstile-response');

    $this->assertGuest();
});

test('login validates turnstile before authenticating', function () {
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

    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
        'cf-turnstile-response' => 'valid-token',
    ])->assertRedirect(route('portal.catalogo', ['vista' => 'mapa'], absolute: false));

    Http::assertSent(fn (\Illuminate\Http\Client\Request $request): bool =>
        $request->url() === 'https://challenges.cloudflare.com/turnstile/v0/siteverify'
        && $request['response'] === 'valid-token'
    );
    $this->assertAuthenticatedAs($user);
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('portal.catalogo', ['vista' => 'mapa'], absolute: false));

    $this->assertAuthenticated();
});

test('email is canonicalized before authentication', function () {
    $user = User::factory()->create(['email' => 'persona@example.com']);

    $this->post(route('login.store'), [
        'email' => '  PERSONA@EXAMPLE.COM  ',
        'password' => 'password',
    ])->assertRedirect(route('portal.catalogo', ['vista' => 'mapa'], absolute: false));

    $this->assertAuthenticatedAs($user);
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrorsIn('email');

    $this->assertGuest();
});

test('users with two factor enabled are redirected to two factor challenge', function () {
    $this->skipUnlessFortifyFeature(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('home'));

    $this->assertGuest();
});

test('login ignores a forged remember option and expires its persistent cookie', function () {
    $user = User::factory()->create();
    $this->get(route('login'))->assertOk()->assertDontSee('Recordarme en este dispositivo');
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password', 'remember' => '1'])
        ->assertSessionHasNoErrors()
        ->assertCookieExpired(\Illuminate\Support\Facades\Auth::guard('web')->getRecallerName());
    $this->assertAuthenticatedAs($user);
    expect(\Illuminate\Support\Facades\Auth::guard('web')->viaRemember())->toBeFalse();
});

test('an old remember cookie cannot restore a session without entering the password', function () {
    $user = User::factory()->create(['remember_token' => 'recuerdo-antiguo-de-prueba']);
    $recaller = \Illuminate\Support\Facades\Auth::guard('web')->getRecallerName();
    $this->withCookie($recaller, $user->id.'|'.$user->remember_token.'|'.$user->password)
        ->get(route('dashboard'))->assertRedirect(route('login'))->assertCookieExpired($recaller);
    $this->assertGuest();
});
