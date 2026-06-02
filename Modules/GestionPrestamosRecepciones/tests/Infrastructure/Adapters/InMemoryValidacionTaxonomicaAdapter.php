<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Adapters;

use Modules\GestionPrestamosRecepciones\Application\Ports\ValidacionTaxonomicaPort;

// TODO: Reemplazar por adapter real cuando InventarioGestionColeccion
//       implemente el catálogo de referencia taxonómico.
final class InMemoryValidacionTaxonomicaAdapter implements ValidacionTaxonomicaPort
{
    /** @var array<string, array{estado: string, sugerencia: ?string}> */
    private array $resultados = [];

    /**
     * Configura el resultado de validación para una especie (uso en tests).
     */
    public function configurarResultado(string $nombreCientifico, string $estado, ?string $sugerencia = null): void
    {
        $this->resultados[$nombreCientifico] = [
            'estado' => $estado,
            'sugerencia' => $sugerencia,
        ];
    }

    public function validarEspecies(array $nombresCientificos): array
    {
        $resultados = [];

        foreach ($nombresCientificos as $nombre) {
            $config = $this->resultados[$nombre] ?? ['estado' => 'catalogado', 'sugerencia' => null];

            $resultados[] = [
                'nombreCientifico' => $nombre,
                'estado' => $config['estado'],
                'sugerencia' => $config['sugerencia'],
            ];
        }

        return $resultados;
    }
}
