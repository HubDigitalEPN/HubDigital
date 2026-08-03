<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\AplicarEdicionMasivaEspecimenes;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TipoEdicionMasiva;

final readonly class AplicarEdicionMasivaEspecimenesInput
{
    /**
     * @param  string[]  $especimenIds  Selección manual del curador.
     * @param  string  $campo  Clave de RegistroColumnasEspecimen::clavesEditablesEnMasa()
     * @param  ?string  $valor  Valor a escribir; ignorado si `$vaciar` es true.
     * @param  bool  $vaciar  Distingue "vaciar el campo" de "no lo toques". Un
     *                        input de texto vacío es ambiguo, y confundir ambos
     *                        borraría columnas enteras sin que nadie lo pidiera.
     * @param  bool  $simular  Calcula el efecto sin escribir nada (vista previa).
     */
    public function __construct(
        public array $especimenIds,
        public string $campo,
        public ?string $valor = null,
        public bool $vaciar = false,
        public bool $simular = false,
        public TipoEdicionMasiva $tipo = TipoEdicionMasiva::FijarValor,
        public ?string $actorId = null,
        public ?string $actorNombre = null,
    ) {}
}
