<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories;

use Illuminate\Support\Facades\Cache;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\ConfiguracionColumna;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\ConfiguracionColumnaRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\PrioridadColumna;

/**
 * Decorador con caché sobre los overrides de prioridad de columnas.
 *
 * Esa tabla se lee en CADA render de la hoja de inventario y de las bandejas
 * (Livewire re-renderiza en cada interacción), pero se escribe desde una única
 * pantalla de administración y en la práctica cambia una vez cada muchos meses.
 * Contra una base remota ese viaje costaba ~350 ms por interacción para
 * devolver un puñado de filas — o ninguna.
 *
 * La invalidación vive AQUÍ, en `guardar()`, y no en el componente que edita:
 * así es estructuralmente imposible añadir un escritor nuevo que olvide limpiar
 * la caché.
 *
 * Se cachean arrays planos y no entidades: el objeto de dominio se reconstruye
 * al leer, de modo que la caché nunca guarda objetos serializados que un cambio
 * futuro de la entidad dejaría irrecuperables.
 */
final class CachedConfiguracionColumnaRepository implements ConfiguracionColumnaRepositoryInterface
{
    private const PREFIJO = 'inventario:configuracion_columnas:';

    /** TTL de respaldo: acota el peor caso si el `forget` no alcanza a otra instancia. */
    private const TTL_SEGUNDOS = 600;

    public function __construct(
        private readonly EloquentConfiguracionColumnaRepository $interno,
    ) {}

    /** @return ConfiguracionColumna[] */
    public function listarPorPantalla(string $pantalla): array
    {
        $filas = Cache::remember(
            self::PREFIJO.$pantalla,
            self::TTL_SEGUNDOS,
            fn () => array_map(
                fn (ConfiguracionColumna $c) => [
                    'id' => $c->id(),
                    'pantalla' => $c->pantalla(),
                    'claveColumna' => $c->claveColumna(),
                    'prioridad' => $c->prioridad()->value,
                    'actualizadoPorId' => $c->actualizadoPorId(),
                ],
                $this->interno->listarPorPantalla($pantalla),
            ),
        );

        return array_map(
            fn (array $f) => ConfiguracionColumna::reconstituir(
                id: $f['id'],
                pantalla: $f['pantalla'],
                claveColumna: $f['claveColumna'],
                prioridad: PrioridadColumna::from($f['prioridad']),
                actualizadoPorId: $f['actualizadoPorId'],
            ),
            $filas,
        );
    }

    public function guardar(ConfiguracionColumna $config): void
    {
        $this->interno->guardar($config);
        Cache::forget(self::PREFIJO.$config->pantalla());
    }

    public function buscarPorPantallaYClave(string $pantalla, string $claveColumna): ?ConfiguracionColumna
    {
        return $this->interno->buscarPorPantallaYClave($pantalla, $claveColumna);
    }

    public function nextIdentity(): string
    {
        return $this->interno->nextIdentity();
    }
}
