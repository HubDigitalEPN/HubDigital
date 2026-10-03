<?php

use App\Support\InformacionRelease;

uses(Tests\InfrastructureTestCase::class);

beforeEach(function () {
    $this->directorioReleaseQa4 = sys_get_temp_dir().'/hubdigital-release-qa4-'.bin2hex(random_bytes(8));
    mkdir($this->directorioReleaseQa4, 0700);
    app()->instance(InformacionRelease::class, new InformacionRelease($this->directorioReleaseQa4));
});

afterEach(function () {
    foreach (['SOURCE-METADATA.json', 'RELEASE-STATUS.json'] as $archivo) {
        $ruta = $this->directorioReleaseQa4.'/'.$archivo;
        if (is_file($ruta)) {
            unlink($ruta);
        }
    }
    rmdir($this->directorioReleaseQa4);
});

function escribirIdentidadPortalQa4(string $directorio, array $fuente, ?array $activacion = null): void
{
    file_put_contents($directorio.'/SOURCE-METADATA.json', json_encode($fuente, JSON_THROW_ON_ERROR));
    if ($activacion !== null) {
        file_put_contents($directorio.'/RELEASE-STATUS.json', json_encode($activacion, JSON_THROW_ON_ERROR));
    }
}

function fuentePortalQa4(array $cambios = []): array
{
    return array_replace([
        'format_version' => 1,
        'repository' => 'https://github.com/HubDigitalEPN/HubDigital',
        'git_branch' => 'main',
        'git_commit' => str_repeat('a', 40),
        'built_at_utc' => '2026-10-02T19:00:00Z',
    ], $cambios);
}

it('publica la identidad del artefacto activo y sus fechas sin exponer otros metadatos', function () {
    escribirIdentidadPortalQa4($this->directorioReleaseQa4, fuentePortalQa4([
        'DB_PASSWORD' => 'dato-que-no-es-publico', 'ruta_servidor' => '/srv/privado',
    ]), [
        'release_id' => str_repeat('b', 16), 'git_commit' => str_repeat('a', 40),
        'activated_at_utc' => '2026-10-02T20:00:00Z', 'token' => 'otro-dato-no-publico',
    ]);

    $respuesta = $this->get('/version');
    $respuesta
        ->assertOk()
        ->assertExactJson([
            'disponible' => true, 'version' => 'aaaaaaaa', 'commit' => str_repeat('a', 40),
            'repositorio' => 'https://github.com/HubDigitalEPN/HubDigital',
            'compilado_en' => '2026-10-02T19:00:00Z', 'desplegado_en' => '2026-10-02T20:00:00Z',
        ]);
    expect($respuesta->headers->get('Cache-Control'))->toContain('no-store');
});

it('no atribuye la fecha de otra release al commit del paquete actual', function () {
    escribirIdentidadPortalQa4($this->directorioReleaseQa4, fuentePortalQa4(), [
        'release_id' => str_repeat('b', 16), 'git_commit' => str_repeat('c', 40),
        'activated_at_utc' => '2026-10-02T20:00:00Z',
    ]);

    $this->get('/version')->assertOk()
        ->assertJsonPath('commit', str_repeat('a', 40))
        ->assertJsonPath('desplegado_en', null);
});

it('conserva una identidad anterior sin inventar fechas no registradas', function () {
    $fuente = fuentePortalQa4();
    unset($fuente['built_at_utc']);
    escribirIdentidadPortalQa4($this->directorioReleaseQa4, $fuente);

    $this->get('/version')->assertOk()->assertJsonPath('disponible', true)
        ->assertJsonPath('compilado_en', null)->assertJsonPath('desplegado_en', null);
});

it('no permite sustituir la identidad del artefacto por parámetros de URL o metadatos ajenos', function () {
    foreach ([
        fuentePortalQa4(['git_branch' => 'trabajo']),
        fuentePortalQa4(['repository' => 'https://example.test/otro']),
        fuentePortalQa4(['git_commit' => '../secreto']),
    ] as $fuente) {
        escribirIdentidadPortalQa4($this->directorioReleaseQa4, $fuente);
        $this->get('/version?commit='.str_repeat('d', 40))->assertOk()->assertExactJson([
            'disponible' => false,
            'mensaje' => 'La identidad de esta versión todavía no está disponible.',
        ]);
    }

    file_put_contents($this->directorioReleaseQa4.'/SOURCE-METADATA.json', '{incompleto');
    $this->get('/version')->assertOk()->assertJsonPath('disponible', false);
});
