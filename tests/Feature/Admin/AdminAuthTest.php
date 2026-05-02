<?php

use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\Login;
use Livewire\Livewire;

describe('Admin login', function () {
    it('muestra la página de login', function () {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSeeLivewire(Login::class);
    });

    it('redirige a login cuando no está autenticado', function () {
        $this->get('/admin')
            ->assertRedirect('/admin/login');
    });

    it('autentica con credenciales correctas', function () {
        Livewire::test(Login::class)
            ->set('username', 'admin')
            ->set('password', 'admin123')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.dashboard'));

        expect(session('admin_authenticated'))->toBeTrue();
    });

    it('rechaza credenciales incorrectas', function () {
        Livewire::test(Login::class)
            ->set('username', 'admin')
            ->set('password', 'wrong')
            ->call('login')
            ->assertHasErrors('username');

        expect(session('admin_authenticated'))->toBeFalsy();
    });

    it('valida que los campos son requeridos', function () {
        Livewire::test(Login::class)
            ->set('username', '')
            ->set('password', '')
            ->call('login')
            ->assertHasErrors(['username', 'password']);
    });
});

describe('Admin panel protegido', function () {
    it('accede al dashboard con sesión activa', function () {
        session(['admin_authenticated' => true]);

        $this->get('/admin')
            ->assertOk()
            ->assertSeeLivewire(Dashboard::class);
    });

    it('hace logout correctamente', function () {
        session(['admin_authenticated' => true]);

        $this->post('/admin/logout')
            ->assertRedirect('/admin/login');

        expect(session('admin_authenticated'))->toBeFalsy();
    });
});
