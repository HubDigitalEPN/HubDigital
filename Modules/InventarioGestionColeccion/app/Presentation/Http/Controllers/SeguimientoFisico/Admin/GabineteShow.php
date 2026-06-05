<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Admin;

use Illuminate\View\View;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarRanura\ActualizarRanuraHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarRanura\ActualizarRanuraInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarCajas\ListarCajasHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarGabinetes\ListarGabineteHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarRanurasGabinete\ListarRanurasGabineteHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarRanurasGabinete\ListarRanurasGabineteInput;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Concerns\TraduceErroresPersistencia;

#[Layout('layouts.app', params: ['title' => 'Gabinete'])]
final class GabineteShow extends Component
{
    use TraduceErroresPersistencia;

    public string $gabineteId = '';

    public array $gabinete = [];

    public array $ranuras = [];

    public bool $showEditRanura = false;

    public string $editandoRanuraId = '';

    public int $editActiva = 1;

    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    public ?string $tokenGenerado = null;

    public bool $tieneToken = false;

    public function mount(
        string $id,
        ListarGabineteHandler $listarGabinetes,
        ListarRanurasGabineteHandler $listarRanuras,
        ListarCajasHandler $listarCajas,
    ): void {
        $this->gabineteId = $id;
        $this->successMessage = session('successMessage');
        $this->cargarProtegido(function () use ($id, $listarGabinetes, $listarRanuras, $listarCajas) {
            $this->cargarGabinete($listarGabinetes);
            $cajasPorId = $this->buildCajasPorId($listarCajas);
            $this->cargarRanuras($listarRanuras, $cajasPorId);
            $this->tieneToken = PersonalAccessToken::where('name', "esp32-{$id}")->exists();
        });
    }

    public function abrirEditRanura(string $ranuraId): void
    {
        $ranura = collect($this->ranuras)->firstWhere('id', $ranuraId);

        if ($ranura === null) {
            return;
        }

        $this->editandoRanuraId = $ranuraId;
        $this->editActiva = $ranura['activa'] ? 1 : 0;
        $this->errorMessage = null;
        $this->showEditRanura = true;
    }

    public function actualizarRanura(
        ActualizarRanuraHandler $actualizarHandler,
        ListarRanurasGabineteHandler $listarHandler,
        ListarCajasHandler $cajasHandler,
    ): void {
        try {
            $actualizarHandler->handle(new ActualizarRanuraInput(
                ranuraId: $this->editandoRanuraId,
                activa: (bool) $this->editActiva,
            ));

            $this->cargarRanuras($listarHandler, $this->buildCajasPorId($cajasHandler));
            $this->showEditRanura = false;
            $this->successMessage = 'Ranura actualizada correctamente.';
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    public function generarToken(): void
    {
        PersonalAccessToken::where('name', "esp32-{$this->gabineteId}")->delete();

        $user = auth()->user();
        $newToken = $user->createToken("esp32-{$this->gabineteId}", ['esp32']);

        $this->tokenGenerado = $newToken->plainTextToken;
        $this->tieneToken = true;
        $this->successMessage = 'Token generado. Cópialo ahora — no se volverá a mostrar.';
    }

    private function cargarGabinete(ListarGabineteHandler $handler): void
    {
        foreach ($handler->handle()->items as $g) {
            if ($g->id === $this->gabineteId) {
                $this->gabinete = [
                    'id' => $g->id,
                    'codigo' => $g->codigo,
                    'nombre' => $g->nombre,
                    'totalRanuras' => $g->totalRanuras,
                    'activo' => $g->activo,
                ];
                break;
            }
        }
    }

    private function buildCajasPorId(ListarCajasHandler $handler): array
    {
        $map = [];
        foreach ($handler->handle()->items as $c) {
            $map[$c->id] = ['id' => $c->id, 'codigo' => $c->codigo, 'estado' => $c->estado];
        }

        return $map;
    }

    private function cargarRanuras(ListarRanurasGabineteHandler $handler, array $cajasPorId = []): void
    {
        $output = $handler->handle(new ListarRanurasGabineteInput($this->gabineteId));
        $this->ranuras = array_map(
            fn ($r) => [
                'id' => $r->id,
                'gabineteId' => $r->gabineteId,
                'numeroRanura' => $r->numeroRanura,
                'activa' => $r->activa,
                'cajaActual' => $r->cajaActualId ? ($cajasPorId[$r->cajaActualId] ?? null) : null,
            ],
            $output->items,
        );
    }

    public function render(): View
    {
        return view('inventariogestioncoleccion::admin.gabinetes.show');
    }
}
