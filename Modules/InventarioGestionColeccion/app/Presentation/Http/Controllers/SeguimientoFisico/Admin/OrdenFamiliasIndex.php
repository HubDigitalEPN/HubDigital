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

/**
 * Pantalla del curador para definir el orden taxonómico esperado de las familias dentro
 * de la colección. Esa secuencia es la referencia con la que el componente IoT decide si
 * una caja está fuera de lugar (alerta de orden taxonómico). Permite reordenar las
 * familias subiéndolas o bajándolas y guardar la secuencia resultante. Es presentación
 * pura: delega la lectura y la actualización en sus casos de uso.
 */
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

    /** Carga la secuencia de familias vigente para mostrarla en la pantalla. */
    public function mount(ObtenerOrdenFamiliasColeccionHandler $handler): void
    {
        $this->cargarProtegido(fn () => $this->cargar($handler));
    }

    /** Sube una familia una posición en la secuencia (intercambia con la anterior); no persiste hasta guardar. */
    public function subir(int $index): void
    {
        if ($index <= 0 || ! isset($this->familias[$index])) {
            return;
        }

        [$this->familias[$index - 1], $this->familias[$index]] = [$this->familias[$index], $this->familias[$index - 1]];
        $this->successMessage = null;
    }

    /** Baja una familia una posición en la secuencia (intercambia con la siguiente); no persiste hasta guardar. */
    public function bajar(int $index): void
    {
        if ($index < 0 || $index >= count($this->familias) - 1) {
            return;
        }

        [$this->familias[$index + 1], $this->familias[$index]] = [$this->familias[$index], $this->familias[$index + 1]];
        $this->successMessage = null;
    }

    /**
     * Persiste el orden actual de familias como la nueva secuencia esperada de la
     * colección y recarga la lista para reflejar el estado guardado. A partir de
     * entonces las alertas de orden taxonómico usarán esta secuencia como referencia.
     * Cualquier fallo se traduce a un mensaje legible.
     */
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

    /** Ejecuta el caso de uso y mapea cada familia a la estructura editable de la secuencia. */
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
