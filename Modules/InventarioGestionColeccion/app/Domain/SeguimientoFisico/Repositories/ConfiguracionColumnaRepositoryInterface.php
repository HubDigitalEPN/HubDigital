<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\ConfiguracionColumna;

interface ConfiguracionColumnaRepositoryInterface
{
    public function buscarPorPantallaYClave(string $pantalla, string $claveColumna): ?ConfiguracionColumna;

    /** @return ConfiguracionColumna[] */
    public function listarPorPantalla(string $pantalla): array;

    public function guardar(ConfiguracionColumna $config): void;

    public function nextIdentity(): string;
}
