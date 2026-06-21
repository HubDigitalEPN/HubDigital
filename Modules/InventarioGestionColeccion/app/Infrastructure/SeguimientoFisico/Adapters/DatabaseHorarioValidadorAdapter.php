<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Adapters;

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\HorarioValidadorPort;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\HorarioRepository;

/**
 * Adaptador que resuelve el horario laboral a partir del horario único persistido en la
 * base de datos, delegando la decisión en la propia entidad de dominio. Es la
 * implementación real del {@see HorarioValidadorPort} usada en producción.
 */
class DatabaseHorarioValidadorAdapter implements HorarioValidadorPort
{
    public function __construct(
        private readonly HorarioRepository $horarioRepository,
    ) {}

    /**
     * Carga el horario único configurado y le delega la evaluación de si la fecha queda
     * fuera de la jornada, de modo que la regla de negocio vive en la entidad y no aquí.
     */
    public function esFueraDeHorario(\DateTimeImmutable $fecha): bool
    {
        $horario = $this->horarioRepository->obtenerUnico();

        return $horario->estaFueraDeHorario($fecha);
    }
}
