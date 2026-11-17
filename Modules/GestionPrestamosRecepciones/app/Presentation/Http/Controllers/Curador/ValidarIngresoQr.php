<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador;

use App\Enums\RolUsuario;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel as Solicitud;
use Modules\GestionPrestamosRecepciones\Infrastructure\Services\IngresoPendienteDeposito;
use Modules\GestionPrestamosRecepciones\Infrastructure\Services\RecepcionQrDeposito;

#[Layout('layouts.app', params: ['title' => 'Validar ingreso por QR'])]
final class ValidarIngresoQr extends Component
{
    #[Locked]
    public string $id;

    #[Locked]
    public string $codigo;

    public string $motivo = '';

    public bool $mostrarRechazo = false;

    public string $motivoReintento = '';

    public bool $aceptarPosibleDuplicado = false;

    public function reintentarAviso(IngresoPendienteDeposito $pendientes): void
    {
        $this->validate(['motivoReintento' => 'required|string|max:3000']);
        $pendientes->reintentar($this->id, auth()->user(), $this->motivoReintento, $this->aceptarPosibleDuplicado);
        $this->motivoReintento = '';
        $this->aceptarPosibleDuplicado = false;
    }

    public function boot(): void
    {
        abort_unless(auth()->user()?->esReceptor(), 403);
    }

    public function mount(string $id, string $codigo): void
    {
        $this->id = $id;
        $this->codigo = $codigo;
        $this->solicitud();
    }

    public function aprobar(RecepcionQrDeposito $recepcion): void
    {
        $recepcion->decidir($this->id, $this->codigo, auth()->user(), true);
        $this->dispatch('toast', message: 'Material recibido. Continúa con el registro y la validación del contenido.');
    }

    public function rechazar(RecepcionQrDeposito $recepcion): void
    {
        $this->validate(['motivo' => 'required|string|max:3000']);
        $recepcion->decidir($this->id, $this->codigo, auth()->user(), false, $this->motivo);
        $this->mostrarRechazo = false;
    }

    public function autorizarNuevoIntento(RecepcionQrDeposito $recepcion): void
    {
        abort_unless(auth()->user()?->tieneAlgunRol(RolUsuario::CURADOR, RolUsuario::ADMIN), 403);
        $this->validate(['motivo' => 'required|string|max:3000']);
        $recepcion->autorizarNuevoIntento($this->id, auth()->user(), $this->motivo);
        $this->motivo = '';
        $this->dispatch('toast', message: 'Nuevo intento autorizado. Se conserva el historial de la entrega anterior.');
    }

    private function solicitud(): Solicitud
    {
        $solicitud = Solicitud::findOrFail($this->id);
        abort_unless(hash_equals((string) $solicitud->codigo_qr, $this->codigo), 403);

        return $solicitud;
    }

    public function render()
    {
        return view('gestionprestamosrecepciones::curador.validar-ingreso-qr', [
            'solicitud' => $this->solicitud(),
            'aviso' => DB::table(IngresoPendienteDeposito::TABLA)->where('solicitud_id', $this->id)->first(),
        ]);
    }
}
