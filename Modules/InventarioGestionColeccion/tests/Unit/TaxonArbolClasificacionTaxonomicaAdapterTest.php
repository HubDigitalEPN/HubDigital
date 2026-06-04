<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Taxon;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\TaxonRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoTaxon;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\RangoTaxonomico;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TaxonId;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Adapters\TaxonArbolClasificacionTaxonomicaAdapter;

/**
 * Repositorio de taxones en memoria, solo para estos tests.
 */
function repoTaxonesEnMemoria(array $taxones): TaxonRepositoryInterface
{
    return new class($taxones) implements TaxonRepositoryInterface
    {
        /** @param array<string, Taxon> $porId */
        public function __construct(private array $porId) {}

        public function nextIdentity(): TaxonId
        {
            return TaxonId::generar();
        }

        public function guardar(Taxon $taxon): void
        {
            $this->porId[(string) $taxon->id()] = $taxon;
        }

        public function buscarPorId(TaxonId $id): ?Taxon
        {
            return $this->porId[(string) $id] ?? null;
        }

        public function buscarPorNombreYRango(string $nombreCientifico, RangoTaxonomico $rango): ?Taxon
        {
            return null;
        }

        public function buscarPorNombreContiene(string $nombre): array
        {
            return [];
        }

        public function buscarTodos(): array
        {
            return array_values($this->porId);
        }

        public function buscarPorIds(array $ids): array
        {
            return array_values(array_intersect_key($this->porId, array_flip($ids)));
        }

        public function listarDescendientesIds(string $taxonId): array
        {
            return [$taxonId];
        }
    };
}

test('resuelve los cinco niveles recorriendo el arbol desde la especie hacia la raiz', function (): void {
    $ordenId = TaxonId::generar();
    $familiaId = TaxonId::generar();
    $subfamiliaId = TaxonId::generar();
    $generoId = TaxonId::generar();
    $especieId = TaxonId::generar();

    $repo = repoTaxonesEnMemoria([
        (string) $ordenId => Taxon::reconstituir($ordenId, 'Lepidoptera', RangoTaxonomico::Orden, 'Linnaeus', 1758, EstadoTaxon::Activo, null),
        (string) $familiaId => Taxon::reconstituir($familiaId, 'Nymphalidae', RangoTaxonomico::Familia, 'Rafinesque', 1815, EstadoTaxon::Activo, $ordenId),
        (string) $subfamiliaId => Taxon::reconstituir($subfamiliaId, 'Nymphalinae', RangoTaxonomico::Subfamilia, 'Rafinesque', 1815, EstadoTaxon::Activo, $familiaId),
        (string) $generoId => Taxon::reconstituir($generoId, 'Nymphalis', RangoTaxonomico::Genero, 'Kluk', 1780, EstadoTaxon::Activo, $subfamiliaId),
        (string) $especieId => Taxon::reconstituir($especieId, 'Nymphalis antiopa', RangoTaxonomico::Especie, 'Linnaeus', 1758, EstadoTaxon::Activo, $generoId),
    ]);

    $adapter = new TaxonArbolClasificacionTaxonomicaAdapter($repo);

    $cls = $adapter->resolverParaTaxon((string) $especieId);

    expect($cls)->not->toBeNull()
        ->and($cls->orden())->toBe('Lepidoptera')
        ->and($cls->familia())->toBe('Nymphalidae')
        ->and($cls->subfamilia())->toBe('Nymphalinae')
        ->and($cls->genero())->toBe('Nymphalis')
        ->and($cls->especie())->toBe('Nymphalis antiopa')
        ->and($cls->suborden())->toBeNull()
        ->and($cls->superfamilia())->toBeNull();
});

test('resuelve clasificacion parcial cuando el taxon de partida no es la especie', function (): void {
    $familiaId = TaxonId::generar();
    $subfamiliaId = TaxonId::generar();

    $repo = repoTaxonesEnMemoria([
        (string) $familiaId => Taxon::reconstituir($familiaId, 'Nymphalidae', RangoTaxonomico::Familia, 'Rafinesque', 1815, EstadoTaxon::Activo, null),
        (string) $subfamiliaId => Taxon::reconstituir($subfamiliaId, 'Nymphalinae', RangoTaxonomico::Subfamilia, 'Rafinesque', 1815, EstadoTaxon::Activo, $familiaId),
    ]);

    $adapter = new TaxonArbolClasificacionTaxonomicaAdapter($repo);

    $cls = $adapter->resolverParaTaxon((string) $subfamiliaId);

    expect($cls)->not->toBeNull()
        ->and($cls->familia())->toBe('Nymphalidae')
        ->and($cls->subfamilia())->toBe('Nymphalinae')
        ->and($cls->genero())->toBeNull()
        ->and($cls->especie())->toBeNull();
});

test('retorna null cuando el taxon no existe', function (): void {
    $adapter = new TaxonArbolClasificacionTaxonomicaAdapter(repoTaxonesEnMemoria([]));

    expect($adapter->resolverParaTaxon((string) TaxonId::generar()))->toBeNull();
});

test('retorna null cuando el taxonId tiene formato invalido', function (): void {
    $adapter = new TaxonArbolClasificacionTaxonomicaAdapter(repoTaxonesEnMemoria([]));

    expect($adapter->resolverParaTaxon('no-es-un-uuid'))->toBeNull();
});

test('no entra en bucle infinito si el arbol tiene un ciclo', function (): void {
    $aId = TaxonId::generar();
    $bId = TaxonId::generar();

    // a -> b -> a (ciclo)
    $repo = repoTaxonesEnMemoria([
        (string) $aId => Taxon::reconstituir($aId, 'Familia A', RangoTaxonomico::Familia, 'Autor', 2000, EstadoTaxon::Activo, $bId),
        (string) $bId => Taxon::reconstituir($bId, 'Orden B', RangoTaxonomico::Orden, 'Autor', 2000, EstadoTaxon::Activo, $aId),
    ]);

    $adapter = new TaxonArbolClasificacionTaxonomicaAdapter($repo);

    $cls = $adapter->resolverParaTaxon((string) $aId);

    expect($cls)->not->toBeNull()
        ->and($cls->familia())->toBe('Familia A')
        ->and($cls->orden())->toBe('Orden B');
});
