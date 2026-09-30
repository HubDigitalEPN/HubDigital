<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

test('un ejemplar sin filo queda en curaduría y entra al CSV público al confirmar su linaje', function (): void {
    $ahora = now();
    $filo = (string) Str::uuid();
    $taxon = (string) Str::uuid();
    $especimen = (string) Str::uuid();
    $codigo = 'QA-CURA-'.Str::upper(Str::random(10));
    $ocurrencia = 'QA-CURA-'.Str::upper(Str::random(10));
    $nombre = 'Curatella '.Str::lower(Str::random(10));

    DB::table('taxonomia.taxones')->insert([
        ['id' => $filo, 'nombre_cientifico' => 'Filo QA '.substr($filo, 0, 8),
            'rango' => 'phylum', 'autor' => 'QA', 'anio_descripcion' => 2026,
            'estado' => 'activo', 'created_at' => $ahora, 'updated_at' => $ahora],
        ['id' => $taxon, 'nombre_cientifico' => $nombre,
            'rango' => 'especie', 'autor' => 'QA', 'anio_descripcion' => 2026,
            'estado' => 'activo', 'created_at' => $ahora, 'updated_at' => $ahora],
    ]);
    DB::table('taxonomia.especimenes')->insert([
        'id' => $especimen, 'codigo_catalogo' => $codigo,
        'occurrence_id' => $ocurrencia, 'taxon_id' => $taxon,
        'localidad' => 'Localidad de verificación curatorial',
        'fecha_colecta' => '2026-09-20', 'colector' => 'QA',
        'estado' => 'disponible', 'created_at' => $ahora, 'updated_at' => $ahora,
    ]);
    DB::table('divulgacion.especimenes_divulgables')->insert([
        'id' => (string) Str::uuid(), 'especimen_id' => $especimen,
        'publicado' => true, 'created_at' => $ahora, 'updated_at' => $ahora,
    ]);

    expect(DB::table('divulgacion.especimenes_divulgables')
        ->where('especimen_id', $especimen)->value('publicado'))->toBeFalse();

    $curador = User::factory()->curador()->create();
    $this->actingAs($curador)
        ->get(route('divulgacion.index', ['catalogo' => $ocurrencia, 'publicacion' => 'curaduria']))
        ->assertOk()->assertSee($ocurrencia);
    auth()->logout();

    $csvAntes = $this->get(route('portal.lista-especies'))->assertOk()->streamedContent();
    expect($csvAntes)->not->toContain($nombre);

    DB::table('taxonomia.taxones')->where('id', $taxon)->update(['padre_id' => $filo]);
    expect(DB::table('divulgacion.especimenes_divulgables')
        ->where('especimen_id', $especimen)->value('publicado'))->toBeTrue();
    $csvDespues = $this->get(route('portal.lista-especies'))->assertOk()->streamedContent();
    expect($csvDespues)->toContain($nombre);
});

test('mapa y estadísticas responde como primera pantalla pública', function (): void {
    $this->get(route('portal.inicio'))->assertRedirect(route('portal.estadisticas'));
    $this->get(route('portal.estadisticas'))
        ->assertOk()
        ->assertSee('Mapa y estadísticas')
        ->assertSee('Registros públicos');
    $this->get('/portal/comparar-especies')->assertNotFound();
});
