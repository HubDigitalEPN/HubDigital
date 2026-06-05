<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Adapters;

use Modules\GestionPrestamosRecepciones\Application\Ports\CatalogoCuraduriaPort;

// TODO: Reemplazar por el adapter real (EloquentCatalogoCuraduriaAdapter o similar)
//       cuando InventarioGestionColeccion implemente el catálogo de curaduría.
final class InMemoryCatalogoCuraduriaAdapter implements CatalogoCuraduriaPort
{
    /** @var array<string, string[]> coleccionId => campos requeridos */
    private array $camposPorColeccion = [];

    /**
     * Configura los campos requeridos para una colección (uso en tests).
     *
     * @param  string[]  $campos
     */
    public function configurarCamposRequeridos(string $coleccionId, array $campos): void
    {
        $this->camposPorColeccion[$coleccionId] = $campos;
    }

    public function camposRequeridos(string $coleccionId): array
    {
        return $this->camposPorColeccion[$coleccionId] ?? [];
    }
}
