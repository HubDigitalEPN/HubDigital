<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador;

use App\Concerns\HandlesDomainExceptions;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ActualizarConfiguracionGlobalRecordatorios\ActualizarConfiguracionGlobalRecordatoriosHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ActualizarConfiguracionGlobalRecordatorios\ActualizarConfiguracionGlobalRecordatoriosInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\DefinirConfiguracionGlobalRecordatorios\DefinirConfiguracionGlobalRecordatoriosHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\DefinirConfiguracionGlobalRecordatorios\DefinirConfiguracionGlobalRecordatoriosInput;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\ConfiguracionGlobalRecordatoriosRepositoryInterface;

#[Layout('layouts.app', params: ['title' => 'Configuración de recordatorios'])]
final class ConfiguracionRecordatorios extends Component
{
    use HandlesDomainExceptions;

    public bool $modoEdicion = false;

    public string $configuracionId = '';

    public array $diasAntes = [30, 15, 7, 1];

    public string $nuevoDia = '';

    public ?string $mensajeExito = null;

    public function mount(ConfiguracionGlobalRecordatoriosRepositoryInterface $repo): void
    {
        $configuracion = $repo->obtenerUnica();

        if ($configuracion !== null) {
            $this->configuracionId = (string) $configuracion->id();
            $this->diasAntes = $configuracion->diasAntes();
        }
    }

    public function habilitarEdicion(): void
    {
        $this->modoEdicion = true;
        $this->mensajeExito = null;
    }

    public function cancelarEdicion(ConfiguracionGlobalRecordatoriosRepositoryInterface $repo): void
    {
        $configuracion = $repo->obtenerUnica();
        $this->diasAntes = $configuracion !== null ? $configuracion->diasAntes() : [30, 15, 7, 1];
        $this->modoEdicion = false;
        $this->nuevoDia = '';
    }

    public function toggleDia(int $dia): void
    {
        if (in_array($dia, array_map('intval', $this->diasAntes), true)) {
            $this->diasAntes = array_values(
                array_filter($this->diasAntes, fn (mixed $d) => (int) $d !== $dia)
            );
        } else {
            $this->diasAntes[] = $dia;
            rsort($this->diasAntes);
        }
    }

    public function agregarDia(): void
    {
        $dia = (int) $this->nuevoDia;

        if ($dia < 1 || in_array($dia, array_map('intval', $this->diasAntes), true)) {
            $this->nuevoDia = '';

            return;
        }

        $this->diasAntes[] = $dia;
        rsort($this->diasAntes);
        $this->nuevoDia = '';
    }

    public function quitarDia(int $dia): void
    {
        $this->diasAntes = array_values(
            array_filter($this->diasAntes, fn (mixed $d) => (int) $d !== $dia)
        );
    }

    public function guardar(
        DefinirConfiguracionGlobalRecordatoriosHandler $definirHandler,
        ActualizarConfiguracionGlobalRecordatoriosHandler $actualizarHandler,
    ): void {
        $this->validate([
            'diasAntes' => ['required', 'array', 'min:1'],
            'diasAntes.*' => ['integer', 'min:1'],
        ]);

        try {
            $diasNormalizados = array_values(array_map('intval', $this->diasAntes));

            if ($this->configuracionId === '') {
                $output = $definirHandler->handle(new DefinirConfiguracionGlobalRecordatoriosInput(
                    curadorId: (string) auth()->id(),
                    diasAntes: $diasNormalizados,
                ));
                $this->configuracionId = $output->configuracionId;
                $this->diasAntes = $output->diasAntes;
            } else {
                $output = $actualizarHandler->handle(new ActualizarConfiguracionGlobalRecordatoriosInput(
                    configuracionId: $this->configuracionId,
                    curadorId: (string) auth()->id(),
                    diasAntes: $diasNormalizados,
                ));
                $this->diasAntes = $output->diasAntes;
            }

            $this->modoEdicion = false;
            $this->mensajeExito = 'Configuración guardada correctamente.';
        } catch (\Throwable $e) {
            $this->dispatch('domain-error', message: $e->getMessage());
        }
    }

    public function render(): View
    {
        return view('gestionprestamosrecepciones::curador.configuracion-recordatorios');
    }
}
