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

#[Layout('layouts.app', params: ['title' => 'Horario'])]
final class HorarioSettingsForm extends Component
{
    #[Rule('required|integer|min:0|max:23')]
    public ?int $horaInicio = null;

    #[Rule('required|integer|min:0|max:23')]
    public ?int $horaFin = null;

    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    public function mount(ObtenerHorarioHandler $handler): void
    {
        $this->cargarHorario($handler);
    }

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
        } catch (\Exception $e) {
            $this->errorMessage = 'Error al actualizar horario: '.$e->getMessage();
            $this->successMessage = null;
        }
    }

    private function cargarHorario(ObtenerHorarioHandler $handler): void
    {
        try {
            $output = $handler->handle();
            $this->horaInicio = $output->horaInicio;
            $this->horaFin = $output->horaFin;
        } catch (\Exception $e) {
            $this->errorMessage = 'Error al cargar horario: '.$e->getMessage();
        }
    }

    public function render(): View
    {
        return view('inventariogestioncoleccion::admin.horarios.settings');
    }
}
