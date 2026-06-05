<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Adapters;

use Modules\GestionPrestamosRecepciones\Application\Ports\CatalogoCuraduriaPort;

// TODO: Reemplazar por un adapter real que consulte InventarioGestionColeccion
//       una vez ese módulo implemente el catálogo de curaduría con campos DwC por colección.
final class FakeCatalogoCuraduriaAdapter implements CatalogoCuraduriaPort
{
    public function camposRequeridos(string $coleccionId): array
    {
        return [];
    }
}
