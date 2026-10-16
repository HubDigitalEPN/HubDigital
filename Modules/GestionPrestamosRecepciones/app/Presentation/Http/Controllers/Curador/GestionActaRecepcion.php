<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Application\Ports\UsuarioNombrePort;
use Modules\GestionPrestamosRecepciones\Application\Ports\OriginalActaRecepcionPort;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDetalleRecepcion\ConsultarDetalleRecepcionHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDetalleRecepcion\ConsultarDetalleRecepcionInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\GenerarActaRecepcion\GenerarActaRecepcionHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\GenerarActaRecepcion\GenerarActaRecepcionInput;

#[Layout('layouts.app', params: ['title' => 'Acta final de recepción'])]
final class GestionActaRecepcion extends Component
{
    public string $id;

    public function mount(string $id): void
    {
        $this->id = $id;
    }

    public function generar(
        GenerarActaRecepcionHandler $handler,
        ConsultarDetalleRecepcionHandler $consultar,
        OriginalActaRecepcionPort $originales,
    ): void
    {
        $resultado = ($handler)(new GenerarActaRecepcionInput(
            solicitudId: $this->id,
            curadorId: (string) auth()->id(),
        ));
        $recepcion = $consultar->handle(new ConsultarDetalleRecepcionInput($this->id));
        abort_if($recepcion === null || $resultado->ruta === '', 409, 'No fue posible preparar el original oficial del acta.');
        $originales->materializar($this->id, (string) auth()->id(), $recepcion);
        $this->dispatch('toast', message: 'Acta final generada. Ya puede revisarla y firmarla.');
    }

    public function reemitirOriginal(
        int $versionEsperada,
        ConsultarDetalleRecepcionHandler $consultar,
        OriginalActaRecepcionPort $originales,
    ): void {
        $recepcion = $consultar->handle(new ConsultarDetalleRecepcionInput($this->id));
        abort_if($recepcion === null || ! $recepcion->actaEmitida || $recepcion->actaFirmada, 409);
        try {
            $originales->obtenerVerificado($this->id);
            abort(409, 'El original oficial todavía está disponible.');
        } catch (\DomainException) {
            $originales->materializar($this->id, (string) auth()->id(), $recepcion, true, $versionEsperada);
        }
        $this->dispatch('toast', message: 'Se emitió una nueva versión del original. Revísela completa antes de firmar.');
    }

    public function render(
        ConsultarDetalleRecepcionHandler $consultar,
        UsuarioNombrePort $usuarios,
        OriginalActaRecepcionPort $originales,
    ): View
    {
        $recepcion = $consultar->handle(new ConsultarDetalleRecepcionInput($this->id));
        abort_if($recepcion === null, 404);

        $original = null;
        if ($recepcion->actaEmitida) {
            try {
                $original = $originales->obtenerVerificado($this->id);
            } catch (\DomainException) {
                // La vista muestra una reemisión explícita únicamente si el error es documental.
            }
        }

        return view('gestionprestamosrecepciones::curador.gestion-acta-recepcion', [
            'recepcion' => $recepcion,
            'depositante' => $usuarios->obtenerNombre($recepcion->investigadorId),
            'receptor' => $recepcion->recibidoPor !== null ? $usuarios->obtenerNombre($recepcion->recibidoPor) : null,
            'originalDisponible' => $original !== null,
            'originalReferencia' => $original['referencia'] ?? null,
            'originalSha256' => $original['sha256'] ?? null,
            'originalVersion' => $original['version'] ?? $originales->versionActual($this->id),
        ]);
    }
}
