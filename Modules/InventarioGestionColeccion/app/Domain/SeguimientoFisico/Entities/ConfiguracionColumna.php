<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\PrioridadColumna;

/**
 * Override de prioridad para una columna específica en una pantalla.
 *
 * Cuando NO hay un registro de este tipo, la pantalla cae al default
 * hardcoded en RegistroColumnasEspecimen / RegistroColumnasMuestra.
 */
class ConfiguracionColumna
{
    private function __construct(
        private readonly string $id,
        private readonly string $pantalla,
        private readonly string $claveColumna,
        private PrioridadColumna $prioridad,
        private ?string $actualizadoPorId,
    ) {}

    public static function crear(
        string $id,
        string $pantalla,
        string $claveColumna,
        string $prioridad,
        ?string $actualizadoPorId = null,
    ): self {
        $vo = PrioridadColumna::desdeValorFlexible($prioridad);
        if ($vo === null) {
            throw new \InvalidArgumentException(
                "Prioridad inválida: '{$prioridad}'. Aceptados: ".
                implode(', ', PrioridadColumna::valoresAceptados())
            );
        }

        return new self(
            id: $id,
            pantalla: self::validar($pantalla, 'pantalla'),
            claveColumna: self::validar($claveColumna, 'claveColumna'),
            prioridad: $vo,
            actualizadoPorId: $actualizadoPorId,
        );
    }

    public static function reconstituir(
        string $id,
        string $pantalla,
        string $claveColumna,
        PrioridadColumna $prioridad,
        ?string $actualizadoPorId,
    ): self {
        return new self($id, $pantalla, $claveColumna, $prioridad, $actualizadoPorId);
    }

    public function cambiarPrioridad(string $prioridad, ?string $actualizadoPorId = null): void
    {
        $vo = PrioridadColumna::desdeValorFlexible($prioridad);
        if ($vo === null) {
            throw new \InvalidArgumentException("Prioridad inválida: '{$prioridad}'.");
        }
        $this->prioridad = $vo;
        $this->actualizadoPorId = $actualizadoPorId;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function pantalla(): string
    {
        return $this->pantalla;
    }

    public function claveColumna(): string
    {
        return $this->claveColumna;
    }

    public function prioridad(): PrioridadColumna
    {
        return $this->prioridad;
    }

    public function actualizadoPorId(): ?string
    {
        return $this->actualizadoPorId;
    }

    private static function validar(string $valor, string $campo): string
    {
        $v = trim($valor);
        if ($v === '') {
            throw new \InvalidArgumentException("{$campo} no puede estar vacío.");
        }

        return $v;
    }
}
