<?php

// Hace cumplir la "Regla de Oro" y la pureza del Domain.

$modulos = ['GestionPrestamosRecepciones', 'InventarioGestionColeccion', 'CatalogoPublico'];

// Domain sin dependencias de Laravel ni Carbon (usar DateTimeImmutable).
foreach ($modulos as $modulo) {
    arch("domain de {$modulo} es puro")
        ->expect("Modules\\{$modulo}\\Domain")
        ->not->toUse(['Illuminate', 'Carbon']);
}

// Regla de Oro: prohibido importar el Domain/ de otro modulo.
//
// El check cubre Domain + Application (el corazon del bounded
// context). No escanea modulos completos a proposito: (1) Infrastructure
// cruza legitimamente via adaptadores ACL (Customer-Supplier, ver
// claude.local.md) y (2) escanear Presentation entera con Livewire revienta
// el reflector de pest-plugin-arch (exit 255). Ampliar a Presentation si
// alguna vez se cuela un import de Domain ajeno en una vista.
foreach ($modulos as $modulo) {
    $otros = array_map(
        fn ($m) => "Modules\\{$m}\\Domain",
        array_values(array_filter($modulos, fn ($m) => $m !== $modulo))
    );

    arch("{$modulo} no importa el Domain de otros modulos")
        ->expect(["Modules\\{$modulo}\\Domain", "Modules\\{$modulo}\\Application"])
        ->not->toUse($otros);
}
