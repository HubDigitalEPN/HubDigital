<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Features;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function () {
    $this->skipUnlessFortifyFeature(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);
});

test('security settings page can be rendered', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertOk()
        ->assertSee('Autenticación de dos factores')
        ->assertSee('Activar 2FA');
});

test('security settings opens directly and protects only two factor management with password confirmation', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('security.edit'));

    $response->assertOk()
        ->assertSee('Actualizar contraseña')
        ->assertSee('Administrar autenticación de dos factores')
        ->assertDontSee('Activar 2FA');
});

test('security settings page renders without two factor when feature is disabled', function () {
    config(['fortify.features' => []]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertOk()
        ->assertSee('Actualizar contraseña')
        ->assertDontSee('Autenticación de dos factores');
});

test('two factor authentication disabled when confirmation abandoned between requests', function () {
    $user = User::factory()->create();

    $user->forceFill([
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
        'two_factor_confirmed_at' => null,
    ])->save();

    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);

    $component = Livewire::test('pages::settings.security');

    $component->assertSet('twoFactorEnabled', false);

    $this->assertDatabaseHas('usuarios.users', [
        'id' => $user->id,
        'two_factor_secret' => null,
        'two_factor_recovery_codes' => null,
    ]);
});

test('password can be updated', function () {
    config(['session.driver' => 'database']);

    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $user->createToken('antes-del-cambio');
    DB::table('sessions')->insert([
        'id' => 'sesion-anterior-password',
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Pest',
        'payload' => '',
        'last_activity' => time(),
    ]);

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.security')
        ->set('current_password', 'password')
        ->set('password', 'new-password')
        ->set('password_confirmation', 'new-password')
        ->call('updatePassword');

    $response->assertHasNoErrors();

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue()
        ->and($user->tokens()->count())->toBe(0);

    $this->assertDatabaseMissing('sessions', [
        'id' => 'sesion-anterior-password',
    ]);
    $this->assertAuthenticatedAs($user);
});

test('disabling two factor authentication revokes previous tokens and sessions', function () {
    config(['session.driver' => 'database']);

    $user = User::factory()->withTwoFactor()->create();
    $user->createToken('antes-de-desactivar-2fa');

    DB::table('sessions')->insert([
        'id' => 'sesion-anterior-2fa',
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Pest',
        'payload' => '',
        'last_activity' => time(),
    ]);

    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);

    Livewire::test('pages::settings.security')
        ->call('disable')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->hasEnabledTwoFactorAuthentication())->toBeFalse()
        ->and($user->tokens()->count())->toBe(0);

    $this->assertDatabaseMissing('sessions', ['id' => 'sesion-anterior-2fa']);
    $this->assertAuthenticatedAs($user);
});

test('confirming two factor authentication revokes tokens and prior sessions', function () {
    config(['session.driver' => 'database']);

    $secret = (new Google2FA)->generateSecretKey();
    $user = User::factory()->create();
    $user->forceFill([
        'two_factor_secret' => encrypt($secret),
        'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code'])),
        'two_factor_confirmed_at' => null,
    ])->save();
    $user->createToken('antes-de-activar-2fa');

    DB::table('sessions')->insert([
        'id' => 'sesion-anterior-activar-2fa',
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Pest',
        'payload' => '',
        'last_activity' => time(),
    ]);

    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);

    Livewire::test('pages::settings.two-factor-setup-modal', [
        'requiresConfirmation' => true,
    ])->set('code', (new Google2FA)->getCurrentOtp($secret))
        ->call('confirmTwoFactor')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->hasEnabledTwoFactorAuthentication())->toBeTrue()
        ->and($user->tokens()->count())->toBe(0);

    $this->assertDatabaseMissing('sessions', ['id' => 'sesion-anterior-activar-2fa']);
    $this->assertAuthenticatedAs($user);
});

test('correct password must be provided to update password', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.security')
        ->set('current_password', 'wrong-password')
        ->set('password', 'new-password')
        ->set('password_confirmation', 'new-password')
        ->call('updatePassword');

    $response->assertHasErrors(['current_password']);
});
