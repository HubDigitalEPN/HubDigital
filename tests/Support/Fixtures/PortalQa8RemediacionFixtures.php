<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\CatalogoPublico\Application\Services\ReferenciaTaxonomicaPublica;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\AsistentePortal;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConsultarChatBotHandler;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConsultaCatalogoPublico;
use Modules\CatalogoPublico\Presentation\Http\Controllers\ChatBotWidget;
use Modules\CatalogoPublico\Presentation\Http\Controllers\PortalCatalogo;

function qa8HormigasFixture(): array
{
    $fixture = qa7ColeccionFixture('especie', 4);
    foreach (['filo' => 'Arthropoda', 'familia' => 'Formicidae', 'genero' => 'Camponotus', 'taxon' => 'Camponotus femoratus'] as $clave => $nombre) {
        DB::table('taxonomia.taxones')->where('id', $fixture[$clave])->update(['nombre_cientifico' => $nombre]);
    }
    $otra = (string) Str::uuid(); $cf = (string) Str::uuid();
    DB::table('taxonomia.taxones')->insert([
        ['id' => $otra, 'padre_id' => $fixture['genero'], 'rango' => 'especie', 'nombre_cientifico' => 'Camponotus alpha'],
        ['id' => $cf, 'padre_id' => $fixture['genero'], 'rango' => 'especie', 'nombre_cientifico' => 'Camponotus femoratus cf'],
    ]);
    DB::table('taxonomia.especimenes')->where('id', $fixture['ids'][2])->update(['taxon_id' => $otra]);
    DB::table('taxonomia.especimenes')->where('id', $fixture['ids'][3])->update(['taxon_id' => $cf]);
    return $fixture;
}

function qa8CaracolesFixture(): array
{
    $fixture = qa7ColeccionFixture('especie', 2);
    foreach (['filo' => 'Mollusca', 'familia' => 'Orthalicidae', 'genero' => 'Naesiotus', 'taxon' => 'Naesiotus eschariferus'] as $clave => $nombre) {
        DB::table('taxonomia.taxones')->where('id', $fixture[$clave])->update(['nombre_cientifico' => $nombre]);
    }
    DB::table('taxonomia.especimenes')->whereIn('id', $fixture['ids'])->update(['state_province' => 'Galápagos', 'individual_count' => 3]);
    return $fixture;
}

function qa8Enlace(array $respuesta, string $vista): array
{
    foreach ($respuesta['opciones'] as $opcion) {
        parse_str((string) parse_url($opcion['url'] ?? '', PHP_URL_QUERY), $p);
        if (($p['vista'] ?? '') === $vista) return $p;
    }
    throw new RuntimeException('Falta el enlace de la vista '.$vista);
}
