<?php

use App\Livewire\Administracion\GestionUsuarios;
use App\Models\User;
use Livewire\Livewire;

test('la grilla ordena toda la consulta y conserva el orden al cambiar de página', function (): void {
    $admin = User::factory()->administrador()->create();
    foreach (range(1, 18) as $numero) {
        User::factory()->depositante()->create(['first_name' => sprintf('Rejilla %02d', $numero)]);
    }

    Livewire::actingAs($admin)->test(GestionUsuarios::class)
        ->set('busqueda', 'Rejilla ')
        ->call('ordenarGrilla', 'persona')
        ->call('aplicarOrdenGrilla', 'persona', 'desc')
        ->assertViewHas('usuarios', fn ($filas) => $filas->total() === 18 && $filas->first()->first_name === 'Rejilla 18')
        ->call('gotoPage', 2)
        ->assertViewHas('usuarios', fn ($filas) => $filas->count() === 3 && $filas->first()->first_name === 'Rejilla 03')
        ->set('gridPerPage', 25)
        ->assertViewHas('usuarios', fn ($filas) => $filas->currentPage() === 1 && $filas->count() === 18);
});

test('la grilla rechaza columnas arbitrarias y limita el tamaño de página', function (): void {
    $admin = User::factory()->administrador()->create();
    Livewire::actingAs($admin)->test(GestionUsuarios::class)
        ->set('gridPerPage', 100000)
        ->assertSet('gridPerPage', 15)
        ->set('gridSort', 'password')
        ->assertViewHas('usuarios', fn ($filas) => $filas->perPage() === 15)
        ->call('ordenarGrilla', 'password')
        ->assertStatus(422);
});

test('restablecer la grilla conserva los datos y limpia búsqueda rol orden y página', function (): void {
    $admin = User::factory()->administrador()->create();
    $total = User::count();
    Livewire::actingAs($admin)->test(GestionUsuarios::class)
        ->set('busqueda', 'sin coincidencias')
        ->set('filtroRol', 'depositante')
        ->set('gridPerPage', 50)
        ->call('ordenarGrilla', 'alta')
        ->call('restablecerGrilla')
        ->assertSet('busqueda', '')
        ->assertSet('filtroRol', '')
        ->assertSet('gridSort', '')
        ->assertSet('gridPerPage', 15)
        ->assertViewHas('usuarios', fn ($filas) => $filas->total() === $total);
    expect(User::count())->toBe($total);
});
