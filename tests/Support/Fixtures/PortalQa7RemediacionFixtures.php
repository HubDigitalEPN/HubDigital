<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\AsistentePortal;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConsultarChatBotHandler;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConsultaCatalogoPublico;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ContextoChat;
use Modules\CatalogoPublico\Domain\ValueObjects\FiltrosBusqueda;
use Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Repositories\EloquentProveedorEspecimenesParaArbol;
use Modules\CatalogoPublico\Presentation\Http\Controllers\ChatBotWidget;
use Modules\CatalogoPublico\Presentation\Http\Controllers\PortalCatalogo;

function qa7ColeccionFixture(string $rango = 'especie', int $cantidad = 3): array
{
    $filo = (string) Str::uuid(); $familia = (string) Str::uuid(); $genero = (string) Str::uuid(); $taxon = (string) Str::uuid();
    DB::table('taxonomia.taxones')->insert([
        ['id' => $filo, 'padre_id' => null, 'rango' => 'phylum', 'nombre_cientifico' => 'Qaheptapoda'],
        ['id' => $familia, 'padre_id' => $filo, 'rango' => 'familia', 'nombre_cientifico' => 'Qaheptidae'],
        ['id' => $genero, 'padre_id' => $familia, 'rango' => 'genero', 'nombre_cientifico' => 'Qaheptus'],
        ['id' => $taxon, 'padre_id' => $rango === 'familia' ? $filo : ($rango === 'genero' ? $familia : $genero), 'rango' => $rango, 'nombre_cientifico' => 'Qaheptus alpha'],
    ]);
    $ids = [];
    foreach (range(0, $cantidad - 1) as $i) {
        $ids[$i] = (string) Str::uuid();
        DB::table('taxonomia.especimenes')->insert([
            'id' => $ids[$i], 'taxon_id' => $taxon, 'codigo_catalogo' => 'QA7-INV-'.($i + 1), 'occurrence_id' => 'QA7-INV-'.($i + 1),
            'localidad' => 'Sitio QA7', 'locality_name' => 'Sitio QA7', 'country' => 'Ecuador',
            'state_province' => $i === 2 ? 'Napo' : 'Orellana', 'fila_origen_excel' => 70000 + $i,
            'fecha_colecta' => '1994-01-10', 'colector' => 'Colectora QA7', 'sampling_protocol' => 'hand',
            'decimal_latitude' => -1.8910422, 'decimal_longitude' => -77.765439,
            'life_stage' => 'adult', 'type_status' => $i === 0 ? 'paratype' : null,
        ]);
        DB::table('divulgacion.especimenes_divulgables')->insert(['id' => (string) Str::uuid(), 'especimen_id' => $ids[$i], 'publicado' => true]);
    }
    return compact('filo', 'familia', 'genero', 'taxon', 'ids');
}

function qa7ParametrosRespuesta(array $respuesta): array
{
    parse_str((string) parse_url($respuesta['opciones'][0]['url'], PHP_URL_QUERY), $parametros);
    return $parametros;
}
