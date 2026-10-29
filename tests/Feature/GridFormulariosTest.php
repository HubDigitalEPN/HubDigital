<?php

use App\Livewire\Administracion\ConfiguracionFormatoIpt;
use App\Livewire\Administracion\ConfiguracionTextosSistema;
use App\Livewire\Administracion\GestionBotones;
use App\Models\User;
use Livewire\Livewire;

uses(\Tests\DatabaseFeatureTestCase::class);

test('los tres formularios adaptados renderizan el grid y mantienen formularios HTML válidos', function () {
    $admin = User::factory()->administrador()->create();
    foreach ([GestionBotones::class, ConfiguracionFormatoIpt::class, ConfiguracionTextosSistema::class] as $clase) {
        $c = Livewire::actingAs($admin)->test($clase)->assertOk()->assertSee('data-hub-grid', false)->assertSee('Acciones');
        $dom = new DOMDocument(); @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$c->html());
        $xpath = new DOMXPath($dom);
        expect($xpath->query('//form//form')->length)->toBe(0);
        if ($clase === GestionBotones::class) {
            expect($xpath->query('//form[@*[name()="wire:submit"]="guardar"]//input[@type="checkbox"]')->length)->toBeGreaterThan(0);
            expect($xpath->query('//form[@*[name()="wire:submit"]="guardar"]//button[@type="submit"]')->length)->toBe(1);
        }
    }
});
