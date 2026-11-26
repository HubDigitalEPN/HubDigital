<?php

use App\Enums\RolUsuario;
use App\Livewire\Administracion\GestionMenuLateral;
use App\Models\User;
use App\Support\MenuLateral;
use Livewire\Livewire;

uses(Tests\DatabaseFeatureTestCase::class);

test('el árbol usa las opciones del rol elegido en el mismo orden que el menú lateral', function (): void {
    $this->actingAs(User::factory()->administrador()->create(['must_change_password' => false]));
    $menu = app(MenuLateral::class);
    $menu->guardar('qa-oculta', [
        'texto' => 'Opción oculta de prueba', 'ruta' => 'portal.catalogo', 'grupo' => 'catalogo',
        'roles' => [RolUsuario::ADMIN->value], 'visible' => false,
    ]);

    $leerArbol = static function (string $html): array {
        $dom = new DOMDocument;
        $dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        $xpath = new DOMXPath($dom);
        $arbol = [];
        foreach ($xpath->query('//*[@data-menu-raices]/li') as $raiz) {
            $arbol[$raiz->getAttribute('data-menu-nodo')] = array_map(
                static fn (DOMElement $hijo): string => $hijo->getAttribute('data-menu-nodo'),
                iterator_to_array($xpath->query('./ul/li', $raiz)),
            );
        }
        return $arbol;
    };
    $esperado = static fn (RolUsuario $rol): array => array_map(
        static fn (array $entrada): array => array_keys($entrada['hijos']), $menu->visiblePara($rol),
    );

    $ui = Livewire::test(GestionMenuLateral::class)->assertSet('rolVista', RolUsuario::ADMIN->value);
    expect($leerArbol($ui->html()))->toBe($esperado(RolUsuario::ADMIN));

    $ui->call('seleccionarRol', RolUsuario::DEPOSITANTE->value);
    expect($leerArbol($ui->html()))->toBe($esperado(RolUsuario::DEPOSITANTE));
    $ui->call('seleccionarRol', RolUsuario::PASANTE->value);
    expect($leerArbol($ui->html()))->toBe($esperado(RolUsuario::PASANTE));

    $ui->call('seleccionarRol', 'todas')->assertSeeHtml('data-menu-nodo="qa-oculta"');
    $ui->call('editar', 'qa-oculta')->set('texto', 'Opción oculta actualizada')->call('guardar')
        ->assertHasNoErrors()->assertRedirect(route('admin.menu-lateral', ['rol' => 'todas']));
    expect(auth()->user()->rolActivo())->toBe(RolUsuario::ADMIN);
});

test('Administrador-Curador configura textos, grupos y visibilidad del menú según el rol', function (): void {
    $this->actingAs(User::factory()->curador()->create(['must_change_password' => false]));
    $menu = app(MenuLateral::class);
    $this->get(route('admin.menu-lateral'))->assertOk();
    expect($menu->visiblePara(RolUsuario::ADMIN)['administracion']['hijos']['botones']['ruta'])->toBe('admin.botones')
        ->and($menu->visiblePara(RolUsuario::PASANTE)['catalogo']['hijos']['captura-dwc']['texto'])->toBe('Creación de especímenes');

    Livewire::test(GestionMenuLateral::class)->call('editar', 'especimenes')
        ->set('texto', 'Inventario de la colección')->set('roles', ['CURADOR'])
        ->call('guardar')->assertHasNoErrors()->assertRedirect(route('admin.menu-lateral'));

    expect($menu->visiblePara(RolUsuario::CURADOR)['catalogo']['hijos']['especimenes']['texto'])->toBe('Inventario de la colección')
        ->and($menu->visiblePara(RolUsuario::AYUDANTE_CURADOR)['catalogo']['hijos'])->not->toHaveKey('especimenes');

    Livewire::test(GestionMenuLateral::class)->call('nueva')
        ->set('texto', 'Colección para consulta')->set('ruta', 'portal.catalogo')->set('grupo', 'catalogo')
        ->set('roles', ['PASANTE'])->call('guardar')->assertHasNoErrors();
    $clave = array_search('Colección para consulta', array_column($menu->opciones(), 'texto'), true);
    $id = array_keys($menu->opciones())[$clave];
    expect($menu->visiblePara(RolUsuario::PASANTE)['catalogo']['hijos'][$id]['ruta'])->toBe('portal.catalogo')
        ->and($menu->visiblePara(RolUsuario::CURADOR)['catalogo']['hijos'])->not->toHaveKey($id);

    Livewire::test(GestionMenuLateral::class)->call('editar', $id)->set('visible', false)
        ->call('guardar')->assertHasNoErrors();
    expect($menu->visiblePara(RolUsuario::PASANTE)['catalogo']['hijos'])->not->toHaveKey($id);
    Livewire::test(GestionMenuLateral::class)->call('eliminar', $id)->assertHasNoErrors();
    expect($menu->opciones())->not->toHaveKey($id);
});

test('la gestión del menú respeta los permisos del destino y protege grupos con opciones', function (): void {
    $this->actingAs(User::factory()->administrador()->create(['must_change_password' => false]));
    Livewire::test(GestionMenuLateral::class)->call('nueva')
        ->set('texto', 'Acceso inválido')->set('ruta', 'admin.bootstrap.store')->set('roles', ['PASANTE'])
        ->call('guardar')->assertHasErrors(['ruta' => 'in']);
    Livewire::test(GestionMenuLateral::class)->call('eliminar', 'catalogo')->assertHasErrors('eliminar');
    $this->actingAs(User::factory()->receptor()->create());
    $this->get(route('admin.menu-lateral'))->assertForbidden();
});
