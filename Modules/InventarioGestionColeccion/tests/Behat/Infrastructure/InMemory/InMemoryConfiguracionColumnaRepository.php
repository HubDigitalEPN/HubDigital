<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory;

use Illuminate\Support\Str;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\ConfiguracionColumna;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\ConfiguracionColumnaRepositoryInterface;

final class InMemoryConfiguracionColumnaRepository implements ConfiguracionColumnaRepositoryInterface
{
    /** @var array<string, ConfiguracionColumna> Key: pantalla|claveColumna */
    private array $store = [];

    public function nextIdentity(): string
    {
        return (string) Str::uuid();
    }

    public function buscarPorPantallaYClave(string $pantalla, string $claveColumna): ?ConfiguracionColumna
    {
        return $this->store[$pantalla.'|'.$claveColumna] ?? null;
    }

    /** @return ConfiguracionColumna[] */
    public function listarPorPantalla(string $pantalla): array
    {
        return array_values(array_filter(
            $this->store,
            fn (ConfiguracionColumna $c) => $c->pantalla() === $pantalla
        ));
    }

    public function guardar(ConfiguracionColumna $config): void
    {
        $this->store[$config->pantalla().'|'.$config->claveColumna()] = $config;
    }
}
