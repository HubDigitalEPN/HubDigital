<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarPrioridadColumna\ActualizarPrioridadColumnaHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarPrioridadColumna\ActualizarPrioridadColumnaInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services\RegistroColumnasEspecimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services\RegistroColumnasMuestra;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services\ResolverPrioridadColumnas;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Concerns\TraduceErroresPersistencia;

#[Layout('layouts.app', params: ['title' => 'Configurar columnas'])]
final class ConfiguracionColumnasIndex extends Component
{
    use TraduceErroresPersistencia;

    /**
     * @var array<string, list<array{clave:string, etiqueta:string, grupo:string, prioridad:string}>>
     */
    public array $pantallas = [];

    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    public function mount(ResolverPrioridadColumnas $resolver): void
    {
        $this->recargar($resolver);
    }

    public function recargar(ResolverPrioridadColumnas $resolver): void
    {
        $this->pantallas = [
            'especimenes' => array_map(
                fn ($c) => ['clave' => $c['clave'], 'etiqueta' => $c['etiqueta'], 'grupo' => $c['grupo'], 'prioridad' => $c['prioridad']],
                $resolver->aplicar('especimenes', RegistroColumnasEspecimen::todas()),
            ),
            'muestras' => array_map(
                fn ($c) => ['clave' => $c['clave'], 'etiqueta' => $c['etiqueta'], 'grupo' => $c['grupo'], 'prioridad' => $c['prioridad']],
                $resolver->aplicar('muestras', RegistroColumnasMuestra::todas()),
            ),
        ];
    }

    public function cambiar(
        ActualizarPrioridadColumnaHandler $handler,
        ResolverPrioridadColumnas $resolver,
        string $pantalla,
        string $clave,
        string $prioridad,
    ): void {
        try {
            $handler->handle(new ActualizarPrioridadColumnaInput(
                pantalla: $pantalla,
                claveColumna: $clave,
                prioridad: $prioridad,
                actualizadoPorId: auth()->id() !== null ? (string) auth()->id() : null,
            ));

            $this->recargar($resolver);
            $this->successMessage = "Prioridad actualizada para '{$clave}' en '{$pantalla}'.";
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    public function render(): View
    {
        return view('inventariogestioncoleccion::admin.taxonomia.columnas.config', [
            'pantallasMeta' => [
                'especimenes' => ['titulo' => 'Especímenes', 'descripcion' => 'Columnas de la pantalla principal del catálogo.'],
                'muestras' => ['titulo' => 'Muestras de colecta', 'descripcion' => 'Columnas de la bandeja de muestras.'],
            ],
        ]);
    }
}
