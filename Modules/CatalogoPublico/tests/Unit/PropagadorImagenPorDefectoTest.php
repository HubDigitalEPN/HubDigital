<?php

declare(strict_types=1);

use Modules\CatalogoPublico\Domain\Entities\ImagenPorDefectoDeTaxon;
use Modules\CatalogoPublico\Domain\Exceptions\NivelNoSobrescribibleException;
use Modules\CatalogoPublico\Domain\Services\PropagadorImagenPorDefecto;
use Modules\CatalogoPublico\Domain\ValueObjects\ImagenTaxonomicaId;
use Modules\CatalogoPublico\Domain\ValueObjects\JerarquiaTaxonomica;
use Modules\CatalogoPublico\Domain\ValueObjects\RangoTaxonomico;

function jerarquiaAtta(): JerarquiaTaxonomica
{
    return JerarquiaTaxonomica::desde(
        phylum: 'Arthropoda',
        class: 'Insecta',
        order: 'Hymenoptera',
        family: 'Formicidae',
        genus: 'Atta',
        specificEpithet: 'cephalotes',
        scientificName: 'Atta cephalotes',
    );
}

test('la imagen subida a un espécimen se propaga solo a especie y género', function (): void {
    $propagador = new PropagadorImagenPorDefecto;
    $imagenId = ImagenTaxonomicaId::generate();

    $nuevos = $propagador->nuevosDefectos(jerarquiaAtta(), $imagenId, nivelesConDefecto: []);

    expect($nuevos)->toHaveCount(2);

    $porNivel = [];
    foreach ($nuevos as $defecto) {
        $porNivel[$defecto->nivel()->value] = $defecto;
    }

    expect($porNivel)->toHaveKeys(['species', 'genus'])
        ->and($porNivel['species']->valorTaxon())->toBe('Atta cephalotes')
        ->and($porNivel['genus']->valorTaxon())->toBe('Atta')
        ->and($porNivel['species']->imagenId()->equals($imagenId))->toBeTrue()
        ->and($porNivel['genus']->imagenId()->equals($imagenId))->toBeTrue();
});

test('no propaga a filo, clase, orden ni familia', function (): void {
    $propagador = new PropagadorImagenPorDefecto;

    $nuevos = $propagador->nuevosDefectos(jerarquiaAtta(), ImagenTaxonomicaId::generate(), nivelesConDefecto: []);

    $niveles = array_map(fn (ImagenPorDefectoDeTaxon $d): string => $d->nivel()->value, $nuevos);

    expect($niveles)->not->toContain('phylum')
        ->and($niveles)->not->toContain('class')
        ->and($niveles)->not->toContain('order')
        ->and($niveles)->not->toContain('family');
});

test('la primera imagen del subárbol gana: no reasigna niveles que ya tienen defecto', function (): void {
    $propagador = new PropagadorImagenPorDefecto;

    $nuevos = $propagador->nuevosDefectos(
        jerarquiaAtta(),
        ImagenTaxonomicaId::generate(),
        nivelesConDefecto: ['genus:Atta', 'species:Atta cephalotes'],
    );

    expect($nuevos)->toBeEmpty();
});

test('si solo el género tiene defecto, solo se crea el de especie', function (): void {
    $propagador = new PropagadorImagenPorDefecto;

    $nuevos = $propagador->nuevosDefectos(
        jerarquiaAtta(),
        ImagenTaxonomicaId::generate(),
        nivelesConDefecto: ['genus:Atta'],
    );

    expect($nuevos)->toHaveCount(1)
        ->and($nuevos[0]->nivel())->toBe(RangoTaxonomico::Species);
});

test('asignar una portada en un nivel superior a género lanza excepción', function (RangoTaxonomico $nivel): void {
    ImagenPorDefectoDeTaxon::asignar($nivel, 'cualquiera', ImagenTaxonomicaId::generate());
})->throws(NivelNoSobrescribibleException::class)->with([
    'filo' => [RangoTaxonomico::Phylum],
    'clase' => [RangoTaxonomico::Class_],
    'orden' => [RangoTaxonomico::Order],
    'familia' => [RangoTaxonomico::Family],
]);
