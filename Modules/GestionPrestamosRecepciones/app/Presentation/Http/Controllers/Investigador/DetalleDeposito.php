<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador;

use App\Enums\RolUsuario;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\View\View;
use Illuminate\Support\Facades\Notification;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDetalleRecepcion\ConsultarDetalleRecepcionHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDetalleRecepcion\ConsultarDetalleRecepcionInput;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\MatrizEspeciesRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Notifications\EntregaFisicaAnunciadaNotification;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;
use Modules\GestionPrestamosRecepciones\Presentation\Support\FechaEcuador;

/**
 * Componente Livewire para el detalle de un depósito.
 */
#[Layout('layouts.app', params: ['title' => 'Detalle de Depósito'])]
final class DetalleDeposito extends Component
{
    public string $id;

    public bool $mostrarEntrega = false;

    public string $entregaProgramadaPara = '';

    public string $entregaNota = '';

    /**
     * Inicializa el componente.
     */
    public function mount(string $id): void
    {
        $this->id = $id;

        $deposito = SolicitudDepositoEloquentModel::find($id);

        if ($deposito && $deposito->investigador_id !== (string) auth()->id()) {
            abort(403);
        }

        if ($deposito?->entrega_programada_para !== null) {
            $this->entregaProgramadaPara = FechaEcuador::formatear($deposito->entrega_programada_para, 'Y-m-d\\TH:i');
            $this->entregaNota = $deposito->entrega_nota ?? '';
        }
    }

    public function anunciarEntrega(): void
    {
        $deposito = SolicitudDepositoEloquentModel::query()
            ->whereKey($this->id)
            ->where('investigador_id', (string) auth()->id())
            ->where('estado', 'Aprobada Documentalmente')
            ->firstOrFail();

        $datos = $this->validate([
            'entregaProgramadaPara' => ['required', 'date_format:Y-m-d\\TH:i', 'after_or_equal:'.now(FechaEcuador::ZONA)->format('Y-m-d\\TH:i')],
            'entregaNota' => ['nullable', 'string', 'max:500'],
        ]);

        $deposito->update([
            'entrega_programada_para' => CarbonImmutable::createFromFormat('!Y-m-d\\TH:i', $datos['entregaProgramadaPara'], FechaEcuador::ZONA)->utc(),
            'entrega_notificada_en' => now(),
            'entrega_nota' => trim($datos['entregaNota']) !== '' ? trim($datos['entregaNota']) : null,
        ]);

        $receptores = User::query()->whereHas('roles', fn ($roles) => $roles->whereIn('rol', [
            RolUsuario::RECEPTOR->value,
            RolUsuario::ADMIN->value,
        ]))->get();

        if ($receptores->isNotEmpty()) {
            Notification::send($receptores, new EntregaFisicaAnunciadaNotification(
                solicitudId: $deposito->id,
                numero: $deposito->numero,
                programadaPara: $deposito->entrega_programada_para,
            ));
        }

        $this->mostrarEntrega = false;
        $this->entregaNota = '';
        $this->dispatch('toast', message: 'Entrega anunciada a Recepción EPN. Presenta el QR junto con el lote y sus documentos.');
    }

    /**
     * Renderiza el componente.
     */
    public function render(
        MatrizEspeciesRepositoryInterface $matrizRepo,
        ConsultarDetalleRecepcionHandler $recepcionHandler,
        AlmacenamientoDepositos $almacenamiento,
    ): View {
        $deposito = SolicitudDepositoEloquentModel::find($this->id);
        $matriz = $deposito ? $matrizRepo->buscarPorSolicitudId($this->id) : null;
        // Estado de la recepción física (para exponer la descarga del Acta de Recepción).
        $recepcion = $deposito ? $recepcionHandler->handle(new ConsultarDetalleRecepcionInput($this->id)) : null;
        $rutaActaTransferencia = $deposito?->acta_transferencia_dominio['ruta'] ?? null;
        $actaTransferenciaDisponible = is_string($rutaActaTransferencia)
            && trim($rutaActaTransferencia) !== ''
            && $almacenamiento->existe($rutaActaTransferencia);

        return view('gestionprestamosrecepciones::investigador.detalle-deposito', compact(
            'deposito',
            'matriz',
            'recepcion',
            'actaTransferenciaDisponible',
        ));
    }
}
