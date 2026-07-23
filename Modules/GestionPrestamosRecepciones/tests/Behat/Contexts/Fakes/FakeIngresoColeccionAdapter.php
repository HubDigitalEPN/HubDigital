<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\Fakes;

use Modules\GestionPrestamosRecepciones\Application\Ports\IngresoColeccionPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\ResultadoIngresoColeccion;
use Modules\GestionPrestamosRecepciones\Application\Ports\ResumenIngresoColeccion;

/**
 * Sustituto del módulo de colección: anota los lotes que se le pidió ingresar.
 *
 * Permite que los escenarios comprueben que los especímenes llegan de verdad a la
 * colección y con qué régimen de tenencia, en vez de conformarse con que el agregado
 * de recepción lo haya anotado.
 */
final class FakeIngresoColeccionAdapter implements IngresoColeccionPort
{
    /** @var list<array{solicitudId: string, estadoColeccion: string}> */
    private array $ingresos = [];

    /** Especímenes que el falso módulo dará por ingresados en cada llamada. */
    public function __construct(private readonly int $especimenesPorLote = 3) {}

    public function ingresarLote(string $solicitudId, string $estadoColeccion): ResultadoIngresoColeccion
    {
        $this->ingresos[] = [
            'solicitudId' => $solicitudId,
            'estadoColeccion' => $estadoColeccion,
        ];

        return new ResultadoIngresoColeccion(
            especimenesCreados: $this->especimenesPorLote,
            omitidosPorDuplicado: 0,
            marcadosParaRevision: 0,
        );
    }

    public function resumenDeLote(string $solicitudId): ResumenIngresoColeccion
    {
        $ingresados = 0;
        foreach ($this->ingresos as $ingreso) {
            if ($ingreso['solicitudId'] === $solicitudId) {
                $ingresados += $this->especimenesPorLote;
            }
        }

        return new ResumenIngresoColeccion(
            especimenesEnColeccion: $ingresados,
            pendientesRevision: 0,
            registrosEnMatriz: $this->especimenesPorLote,
        );
    }

    public function devolverLote(string $solicitudId, \DateTimeImmutable $devueltoEn): int
    {
        $devueltos = 0;

        foreach ($this->ingresos as $i => $ingreso) {
            if ($ingreso['solicitudId'] !== $solicitudId || $ingreso['estadoColeccion'] !== 'Temporal') {
                continue;
            }
            // Igual que en la colección real: solo sale el material en custodia temporal.
            unset($this->ingresos[$i]);
            $devueltos += $this->especimenesPorLote;
        }

        $this->ingresos = array_values($this->ingresos);

        return $devueltos;
    }

    /** @return list<array{solicitudId: string, estadoColeccion: string}> */
    public function ingresos(): array
    {
        return $this->ingresos;
    }

    /** Régimen con el que se ingresó el lote de la solicitud dada, si se ingresó. */
    public function estadoColeccionDe(string $solicitudId): ?string
    {
        foreach ($this->ingresos as $ingreso) {
            if ($ingreso['solicitudId'] === $solicitudId) {
                return $ingreso['estadoColeccion'];
            }
        }

        return null;
    }
}
