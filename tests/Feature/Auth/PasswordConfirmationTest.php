<?php

use App\Models\User;

test('the previous confirmation page opens security settings directly', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('password.confirm'));

    $response->assertRedirect(route('security.edit'));
});
