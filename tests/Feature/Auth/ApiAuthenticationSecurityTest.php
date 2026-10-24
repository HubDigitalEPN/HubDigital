<?php

use App\Models\User;

test('api login cannot issue a token before the initial password is changed', function () {
    $user = User::factory()->curador()->create(['must_change_password' => true]);
    $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
        ->assertForbidden()->assertJsonPath('must_change_password', true);
    expect($user->tokens()->count())->toBe(0);
});

test('existing api tokens cannot read or write while an initial password change is pending', function () {
    $user = User::factory()->curador()->create(['must_change_password' => true]);
    $token = $user->createToken('existing', ['curaduria:gestionar'])->plainTextToken;
    $this->withToken($token)->getJson('/api/me')->assertForbidden();
    $this->withToken($token)->postJson('/api/v1/taxonomia/taxones', [])->assertForbidden();
    $this->withToken($token)->postJson('/api/logout')->assertOk();
    expect($user->tokens()->count())->toBe(0);
});

test('login account quota survives a change of source address', function () {
    $email = \Illuminate\Support\Str::uuid().'@example.org';
    for ($i = 1; $i <= 5; $i++) {
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.'.$i])
            ->postJson('/api/login', ['email' => $email, 'password' => 'incorrect-password'])->assertUnauthorized();
    }
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.6'])
        ->postJson('/api/login', ['email' => $email, 'password' => 'incorrect-password'])->assertTooManyRequests();
});

test('login source quota survives a change of account', function () {
    $prefix = (string) \Illuminate\Support\Str::uuid();
    for ($i = 1; $i <= 20; $i++) {
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.80'])
            ->postJson('/api/login', ['email' => $prefix.$i.'@example.org', 'password' => 'incorrect-password'])->assertUnauthorized();
    }
    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.80'])
        ->postJson('/api/login', ['email' => $prefix.'21@example.org', 'password' => 'incorrect-password'])->assertTooManyRequests();
});

test('api login issues a short lived token limited to the user role', function () {
    config(['auth.api_token_lifetime' => 30]);

    $user = User::factory()->depositante()->create([
        'email' => 'depositante@example.org',
    ]);

    $before = now();

    $this->postJson('/api/login', [
        'email' => ' DEPOSITANTE@EXAMPLE.ORG ',
        'password' => 'password',
    ])->assertOk()->assertJsonPath('user.id', $user->id);

    $token = $user->tokens()->sole();

    expect($token->abilities)->toBe(['depositos:gestionar'])
        ->and($token->abilities)->not->toContain('*')
        ->and($token->expires_at)->not->toBeNull()
        ->and($token->expires_at->betweenIncluded(
            $before->copy()->addMinutes(29),
            $before->copy()->addMinutes(31),
        ))->toBeTrue();
});

test('api login never bypasses two factor authentication', function () {
    $user = User::factory()->depositante()->withTwoFactor()->create();

    $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertStatus(409)->assertJson([
        'two_factor_required' => true,
    ]);

    expect($user->tokens()->count())->toBe(0);
});

test('api login does not issue tokens to unverified accounts', function () {
    $user = User::factory()->depositante()->unverified()->create();

    $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertUnauthorized()->assertJson([
        'message' => 'Credenciales inválidas',
    ]);

    expect($user->tokens()->count())->toBe(0);
});
