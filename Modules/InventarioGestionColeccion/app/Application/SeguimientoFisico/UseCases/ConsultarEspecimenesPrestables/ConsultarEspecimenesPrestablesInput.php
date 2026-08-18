<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarEspecimenesPrestables;

/**
 * Qué especímenes prestables se piden: por texto de búsqueda o por identificador.
 *
 * @param  string[]  $ids  Vacío para buscar por texto; con ids se ignora el límite.
 */
final readonly class ConsultarEspecimenesPrestablesInput
{
    public function __construct(
        public ?string $texto = null,
        public array $ids = [],
        public int $limite = 15,
    ) {}

    /** @param string[] $ids */
    public static function porIds(array $ids): self
    {
        return new self(ids: $ids);
    }

    public static function porTexto(string $texto, int $limite = 15): self
    {
        return new self(texto: $texto, limite: $limite);
    }
}
