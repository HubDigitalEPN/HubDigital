<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ReemplazarTextoEnEspecimenes;

final readonly class ReemplazarTextoEnEspecimenesInput
{
    /**
     * @param  string[]  $especimenIds
     * @param  string  $campo  Debe estar en clavesEditablesDeTexto().
     * @param  string  $buscar  Texto LITERAL, no una expresión regular.
     * @param  bool  $palabraCompleta  Evita partir palabras que contienen el
     *                                 patrón (reemplazar "sp" por "sp." no debe
     *                                 tocar "Aspidosperma").
     */
    public function __construct(
        public array $especimenIds,
        public string $campo,
        public string $buscar,
        public string $reemplazo = '',
        public bool $distinguirMayusculas = true,
        public bool $palabraCompleta = false,
        public bool $simular = false,
        public ?string $actorId = null,
        public ?string $actorNombre = null,
    ) {}
}
