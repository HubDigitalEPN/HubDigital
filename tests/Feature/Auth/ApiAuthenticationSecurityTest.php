<?php

use App\Models\User;

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
