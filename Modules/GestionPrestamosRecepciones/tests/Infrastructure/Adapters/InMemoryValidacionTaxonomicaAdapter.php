<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Adapters;

use Modules\GestionPrestamosRecepciones\Application\Ports\ValidacionTaxonomicaPort;

final class InMemoryValidacionTaxonomicaAdapter implements ValidacionTaxonomicaPort
{
    /** @var array<string, array{estado: string, sugerencia: ?string, sugerencias: list<string>}> */
    private array $resultados = [];

    /**
     * Configura el resultado de validación para una especie (uso en tests).
     *
     * @param  list<string>|null  $sugerencias  Lista completa de candidatos; si es null se deriva de $sugerencia.
     */
    public function configurarResultado(string $nombreCientifico, string $estado, ?string $sugerencia = null, ?array $sugerencias = null): void
    {
        $this->resultados[$nombreCientifico] = [
            'estado' => $estado,
            'sugerencia' => $sugerencia,
            'sugerencias' => $sugerencias ?? ($sugerencia !== null ? [$sugerencia] : []),
        ];
    }

    public function validarEspecies(array $nombresCientificos): array
    {
        $resultados = [];

        foreach ($nombresCientificos as $nombre) {
            $config = $this->resultados[$nombre] ?? ['estado' => 'catalogado', 'sugerencia' => null, 'sugerencias' => []];

            $resultados[] = [
                'nombreCientifico' => $nombre,
                'estado' => $config['estado'],
                'sugerencia' => $config['sugerencia'],
                'sugerencias' => $config['sugerencias'] ?? ($config['sugerencia'] !== null ? [$config['sugerencia']] : []),
            ];
        }

        return $resultados;
    }
}
