<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador;

use App\Concerns\HandlesDomainExceptions;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Application\UseCases\EnviarActaPrestamo\EnviarActaPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\EnviarActaPrestamo\EnviarActaPrestamoInput;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\ActaPrestamoModel;

#[Layout('layouts.app', params: ['title' => 'Bandeja de actas'])]
final class BandejaActas extends Component
{
    use HandlesDomainExceptions;

    public string $successMessage = '';

    public function enviarActa(string $actaId, EnviarActaPrestamoHandler $handler): void
    {
        $handler->handle(new EnviarActaPrestamoInput(
            actaId: $actaId,
            curadorId: (string) auth()->id(),
        ));

        $this->successMessage = 'Acta enviada al investigador correctamente.';
    }

    public function render(): View
    {
        $actasPorEnviar = ActaPrestamoModel::query()
            ->where('estado', 'pendiente_envio')
            ->with('solicitud')
            ->orderByDesc('created_at')
            ->get();

        $actasPendienteFirma = ActaPrestamoModel::query()
            ->where('estado', 'pendiente_firma')
            ->with('solicitud')
            ->orderByDesc('created_at')
            ->get();

        $actasPorValidar = ActaPrestamoModel::query()
            ->where('estado', 'pendiente_validacion')
            ->with('solicitud')
            ->orderByDesc('created_at')
            ->get();

        return view('gestionprestamosrecepciones::curador.bandeja-actas', compact('actasPorEnviar', 'actasPendienteFirma', 'actasPorValidar'));
    }
}
