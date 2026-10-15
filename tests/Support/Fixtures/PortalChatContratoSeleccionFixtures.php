<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\AsistentePortal;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConsultarChatBotHandler;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConsultaCatalogoPublico;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ContextoChat;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\DetectorEntidadesChat;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\EnlaceSeleccionCatalogo;
use Modules\CatalogoPublico\Presentation\Http\Controllers\ChatBotWidget;
use Modules\CatalogoPublico\Presentation\Http\Controllers\PortalCatalogo;
use Tests\DatabaseFeatureTestCase;

function registrosParaContratoChat(): void
{
    $phylum = (string) Str::uuid();
    $genus = (string) Str::uuid();
    $alpha = (string) Str::uuid();
    $beta = (string) Str::uuid();
    DB::table('taxonomia.taxones')->insert([
        ['id' => $phylum, 'nombre_cientifico' => 'Filo QA '.substr($phylum, 0, 8), 'rango' => 'phylum', 'autor' => 'Prueba', 'anio_descripcion' => 2026, 'padre_id' => null],
        ['id' => $genus, 'nombre_cientifico' => 'Chatobius', 'rango' => 'genero', 'autor' => 'Prueba', 'anio_descripcion' => 2026, 'padre_id' => $phylum],
        ['id' => $alpha, 'nombre_cientifico' => 'Chatobius alpha', 'rango' => 'especie', 'autor' => 'Prueba', 'anio_descripcion' => 2026, 'padre_id' => $genus],
        ['id' => $beta, 'nombre_cientifico' => 'Chatobius beta', 'rango' => 'especie', 'autor' => 'Prueba', 'anio_descripcion' => 2026, 'padre_id' => $genus],
    ]);
    foreach ([
        ['QA3-CHAT-1', $alpha, 'Pichincha', 'Pichincha', 'Peru', '2000-05-01', 1500],
        ['QA3-CHAT-2', $beta, 'Pichincha', 'Provincia de pichincha', 'Perú', '2000-05-05', 2500],
        ['QA3-CHAT-3', $beta, 'Pichincha', 'Quito', 'Peru', '2000-02-08', 1800],
        ['QA3-CHAT-4', $alpha, 'Esmeraldas', 'Playa de oro', 'Ecuador', '2000-05-05', 1200],
    ] as [$codigo, $taxon, $provincia, $localidad, $pais, $fecha, $elevacion]) {
        $id = (string) Str::uuid();
        DB::table('taxonomia.especimenes')->insert(['id' => $id, 'codigo_catalogo' => $codigo, 'occurrence_id' => $codigo,
            'taxon_id' => $taxon, 'localidad' => $localidad, 'locality_name' => $localidad,
            'state_province' => $provincia, 'country' => $pais, 'fecha_colecta' => $fecha,
            'elevation_min_m' => $elevacion, 'elevation_max_m' => $elevacion, 'colector' => 'Prueba',
            'decimal_latitude' => -0.5, 'decimal_longitude' => -78.5]);
        DB::table('divulgacion.especimenes_divulgables')->insert(['id' => (string) Str::uuid(), 'especimen_id' => $id]);
    }
}

function parametrosEnlaceChat(array $respuesta): array
{
    parse_str((string) parse_url($respuesta['opciones'][0]['url'], PHP_URL_QUERY), $parametros);
    return $parametros;
}

function seleccionChatRenderizada(object $catalogo): array
{
    expect(preg_match('/\bdata-catalogo-seleccion="([^"]*)"/u', $catalogo->html(), $atributo))->toBe(1);

    return json_decode(html_entity_decode($atributo[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), true, flags: JSON_THROW_ON_ERROR);
}

function jerarquiaGenerosChat(): array
{
    registrosParaContratoChat();
    $genero = DB::table('taxonomia.taxones')->where('nombre_cientifico', 'Chatobius')->first();
    $reino = (string) Str::uuid(); $clase = (string) Str::uuid(); $orden = (string) Str::uuid(); $familia = (string) Str::uuid();
    $otroGenero = (string) Str::uuid(); $otraEspecie = (string) Str::uuid();
    DB::table('taxonomia.taxones')->insert([
        ['id' => $reino, 'padre_id' => null, 'rango' => 'reino', 'nombre_cientifico' => 'Chatanimalia'],
        ['id' => $clase, 'padre_id' => $genero->padre_id, 'rango' => 'clase', 'nombre_cientifico' => 'Chatinsecta'],
        ['id' => $orden, 'padre_id' => $clase, 'rango' => 'orden', 'nombre_cientifico' => 'Chathymenoptera'],
        ['id' => $familia, 'padre_id' => $orden, 'rango' => 'familia', 'nombre_cientifico' => 'Chatformicidae'],
        ['id' => $otroGenero, 'padre_id' => $familia, 'rango' => 'genero', 'nombre_cientifico' => 'Chatoterus'],
        ['id' => $otraEspecie, 'padre_id' => $otroGenero, 'rango' => 'especie', 'nombre_cientifico' => 'Chatoterus gamma'],
    ]);
    DB::table('taxonomia.taxones')->where('id', $genero->padre_id)->update(['nombre_cientifico' => 'Chatarthropoda', 'padre_id' => $reino]);
    DB::table('taxonomia.taxones')->where('id', $genero->id)->update(['padre_id' => $familia]);
    DB::table('taxonomia.especimenes')->where('codigo_catalogo', 'QA3-CHAT-4')->update(['taxon_id' => $otraEspecie]);

    return ['familia' => $familia];
}
