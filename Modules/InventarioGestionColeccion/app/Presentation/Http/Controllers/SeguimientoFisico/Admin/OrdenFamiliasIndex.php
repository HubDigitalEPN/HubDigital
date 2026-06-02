<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Admin;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarOrdenEsperadoFamilias\ActualizarOrdenEsperadoFamiliasHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarOrdenEsperadoFamilias\ActualizarOrdenEsperadoFamiliasInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ObtenerOrdenFamiliasColeccion\ObtenerOrdenFamiliasColeccionHandler;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Concerns\TraduceErroresPersistencia;

#[Layout('layouts.app', params: ['title' => 'Orden de familias'])]
final class OrdenFamiliasIndex extends Component
{
    use TraduceErroresPersistencia;

    /**
     * Secuencia editable de familias. Cada elemento:
     * ['familia' => string, 'subfamilias' => string[], 'enSecuencia' => bool, 'presente' => bool].
     *
     * @var array<int, array{familia: string, subfamilias: string[], enSecuencia: bool, presente: bool}>
     */
    public array $familias = [];

    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    public function mount(ObtenerOrdenFamiliasColeccionHandler $handler): void
    {
        $this->cargarProtegido(fn () => $this->cargar($handler));
    }

    public function subir(int $index): void
    {
        if ($index <= 0 || ! isset($this->familias[$index])) {
            return;
        }

        [$this->familias[$index - 1], $this->familias[$index]] = [$this->familias[$index], $this->familias[$index - 1]];
        $this->successMessage = null;
    }

    public function bajar(int $index): void
    {
        if ($index < 0 || $index >= count($this->familias) - 1) {
            return;
        }

        [$this->familias[$index + 1], $this->familias[$index]] = [$this->familias[$index], $this->familias[$index + 1]];
        $this->successMessage = null;
    }

    public function guardar(
        ActualizarOrdenEsperadoFamiliasHandler $actualizar,
        ObtenerOrdenFamiliasColeccionHandler $obtener,
    ): void {
        try {
            $actualizar->handle(new ActualizarOrdenEsperadoFamiliasInput(
                familias: array_column($this->familias, 'familia'),
            ));

            $this->cargar($obtener);
            $this->successMessage = 'Orden de familias guardado. Las alertas usarán esta secuencia como referencia.';
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
            $this->successMessage = null;
        }
    }

    private function cargar(ObtenerOrdenFamiliasColeccionHandler $handler): void
    {
        $this->familias = array_map(
            fn ($item) => [
                'familia' => $item->familia,
                'subfamilias' => $item->subfamilias,
                'enSecuencia' => $item->enSecuencia,
                'presente' => $item->presente,
            ],
            $handler->handle()->familias,
        );
    }

    public function render(): View
    {
        return view('inventariogestioncoleccion::admin.orden-familias.index');
    }
}
