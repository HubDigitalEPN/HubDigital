<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Admin;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarHorario\ActualizarHorarioHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarHorario\ActualizarHorarioInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ObtenerHorario\ObtenerHorarioHandler;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Concerns\TraduceErroresPersistencia;

/**
 * Formulario del curador para definir el horario hábil de la colección (hora de inicio
 * y de fin de la jornada). Ese horario es el que usa el componente IoT para decidir si
 * la ausencia prolongada de una caja cuenta como tiempo de extracción excedido. Es
 * presentación pura: delega la lectura y la actualización en sus casos de uso.
 */
#[Layout('layouts.app', params: ['title' => 'Horario'])]
final class HorarioSettingsForm extends Component
{
    use TraduceErroresPersistencia;

    #[Rule('required|integer|min:0|max:23')]
    public ?int $horaInicio = null;

    #[Rule('required|integer|min:0|max:23')]
    public ?int $horaFin = null;

    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    /** Carga el horario vigente para precargar el formulario. */
    public function mount(ObtenerHorarioHandler $handler): void
    {
        $this->cargarHorario($handler);
    }

    /**
     * Valida y guarda el nuevo horario hábil. Antes de delegar comprueba que la hora de
     * inicio sea menor que la de fin (invariante de rango) y, si no, muestra el error sin
     * llamar al caso de uso. Cualquier fallo de persistencia se traduce a un mensaje legible.
     */
    public function actualizar(ActualizarHorarioHandler $handler): void
    {
        $this->validate();

        if ($this->horaInicio >= $this->horaFin) {
            $this->errorMessage = 'La hora de inicio debe ser menor que la hora de fin.';
            $this->successMessage = null;

            return;
        }

        try {
            $handler->handle(new ActualizarHorarioInput(
                horaInicio: $this->horaInicio,
                horaFin: $this->horaFin,
            ));

            $this->successMessage = 'Horario actualizado correctamente.';
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
            $this->successMessage = null;
        }
    }

    /** Lee el horario vigente del caso de uso y vuelca sus horas en el formulario. */
    private function cargarHorario(ObtenerHorarioHandler $handler): void
    {
        try {
            $output = $handler->handle();
            $this->horaInicio = $output->horaInicio;
            $this->horaFin = $output->horaFin;
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    public function render(): View
    {
        return view('inventariogestioncoleccion::admin.horarios.settings');
    }
}
