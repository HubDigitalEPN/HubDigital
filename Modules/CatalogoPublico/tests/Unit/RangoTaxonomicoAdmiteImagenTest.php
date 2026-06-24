<?php

declare(strict_types=1);

use Modules\CatalogoPublico\Domain\ValueObjects\RangoTaxonomico;

test('solo género y especie admiten imagen', function (RangoTaxonomico $nivel): void {
    expect($nivel->admiteImagen())->toBeTrue();
})->with([
    'género' => [RangoTaxonomico::Genus],
    'especie' => [RangoTaxonomico::Species],
]);

test('los niveles superiores a género no admiten imagen', function (RangoTaxonomico $nivel): void {
    expect($nivel->admiteImagen())->toBeFalse();
})->with([
    'filo' => [RangoTaxonomico::Phylum],
    'clase' => [RangoTaxonomico::Class_],
    'orden' => [RangoTaxonomico::Order],
    'familia' => [RangoTaxonomico::Family],
]);
