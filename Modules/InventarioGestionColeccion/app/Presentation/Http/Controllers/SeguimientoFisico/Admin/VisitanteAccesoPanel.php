<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Admin;

use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarVisitantes\ListarVisitantesHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegenerarAccesoVisitante\RegenerarAccesoVisitanteHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegenerarAccesoVisitante\RegenerarAccesoVisitanteInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarVisitante\RegistrarVisitanteHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarVisitante\RegistrarVisitanteInput;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Concerns\TraduceErroresPersistencia;

/**
 * Panel del curador para dar acceso temporal al mapa a los visitantes. Lista los
 * visitantes ya registrados para regenerarles un QR sin volver a registrarlos, y
 * permite dar de alta nuevos. El QR codifica una URL firmada y temporal (~5 min):
 * el visitante la escanea y entra directo al mapa, sin teclear nada.
 */
#[Layout('layouts.app', params: ['title' => 'Acceso de visitantes'])]
final class VisitanteAccesoPanel extends Component
{
    use TraduceErroresPersistencia;

    /**
     * Minutos de validez del enlace firmado del QR. Debe coincidir con la duración
     * de la sesión que abre AccesoVisitanteController.
     */
    private const MINUTOS_VALIDEZ_QR = 5;

    /** @var array<int, array<string, mixed>> */
    public array $visitantes = [];

    public bool $showModal = false;

    #[Rule('required|string|max:255')]
    public string $nombre = '';

    #[Rule('nullable|string|max:255')]
    public ?string $contacto = null;

    /** Datos del QR generado y mostrado actualmente (null = ninguno abierto). */
    public ?string $qrUrl = null;

    public ?string $qrVisitanteNombre = null;

    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    public function mount(ListarVisitantesHandler $handler): void
    {
        $this->cargarProtegido(fn () => $this->cargarVisitantes($handler));
    }

    public function abrirModal(): void
    {
        $this->reset('nombre', 'contacto', 'successMessage', 'errorMessage');
        $this->resetValidation();
        $this->showModal = true;
    }

    public function registrarVisitante(
        RegistrarVisitanteHandler $registrarHandler,
        ListarVisitantesHandler $listarHandler,
    ): void {
        $this->validateOnly('nombre');
        $this->validateOnly('contacto');

        try {
            $output = $registrarHandler->handle(new RegistrarVisitanteInput(
                nombre: $this->nombre,
                contacto: $this->contacto,
            ));

            $this->cargarVisitantes($listarHandler);
            $this->showModal = false;
            $this->successMessage = 'Visitante registrado correctamente.';
            $this->errorMessage = null;

            // Tras registrar, ofrece el QR de inmediato para agilizar la entrada.
            $this->mostrarQr((string) $output->id, $output->versionAcceso, $output->nombre);
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    /**
     * Emite un QR fresco para un visitante ya registrado. Cada generación invalida
     * los QR previos (incrementa la versión de acceso) y muestra el nuevo enlace.
     */
    public function generarQr(
        string $id,
        RegenerarAccesoVisitanteHandler $regenerarHandler,
        ListarVisitantesHandler $listarHandler,
    ): void {
        try {
            $output = $regenerarHandler->handle(new RegenerarAccesoVisitanteInput(visitanteId: $id));

            $this->cargarVisitantes($listarHandler);
            $this->mostrarQr((string) $output->id, $output->versionAcceso, $output->nombre);
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    public function cerrarQr(): void
    {
        $this->qrUrl = null;
        $this->qrVisitanteNombre = null;
    }

    private function mostrarQr(string $visitanteId, int $version, string $nombre): void
    {
        $this->qrUrl = URL::temporarySignedRoute(
            'inventario.visitante.acceso',
            now()->addMinutes(self::MINUTOS_VALIDEZ_QR),
            ['visitanteId' => $visitanteId, 'version' => $version],
        );
        $this->qrVisitanteNombre = $nombre;
    }

    private function cargarVisitantes(ListarVisitantesHandler $handler): void
    {
        $output = $handler->handle();
        $this->visitantes = array_map(
            fn ($v) => [
                'id' => $v->id,
                'nombre' => $v->nombre,
                'contacto' => $v->contacto,
                'versionAcceso' => $v->versionAcceso,
                'registradoEn' => $v->registradoEn->format('d/m/Y H:i'),
            ],
            $output->items,
        );
    }

    public function render(): View
    {
        return view('inventariogestioncoleccion::admin.visitantes');
    }
}
