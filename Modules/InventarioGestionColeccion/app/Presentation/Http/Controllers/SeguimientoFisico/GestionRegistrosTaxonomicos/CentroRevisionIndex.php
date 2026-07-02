<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ResumirEstadoRevision\ResumirEstadoRevisionHandler;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Concerns\TraduceErroresPersistencia;

#[Layout('layouts.app', params: ['title' => 'Centro de revisión'])]
final class CentroRevisionIndex extends Component
{
    use TraduceErroresPersistencia;

    public int $totalEspecimenes = 0;

    public int $publicablesGbif = 0;

    public float $porcentajePublicables = 0.0;

    public int $pendientesRevision = 0;

    public int $localidadesVerbatimPendientes = 0;

    public int $taxonVerbatimPendientes = 0;

    public int $duplicadosCatalogNumberGrupos = 0;

    public int $fechaVerbatimPendientes = 0;

    public int $muestrasPendientes = 0;

    public ?string $errorMessage = null;

    public function mount(ResumirEstadoRevisionHandler $handler): void
    {
        $this->cargar($handler);
    }

    /** Vuelve a leer los conteos: el tablero es una foto, y esto la refresca a demanda. */
    public function actualizar(ResumirEstadoRevisionHandler $handler): void
    {
        $this->cargar($handler);
    }

    private function cargar(ResumirEstadoRevisionHandler $handler): void
    {
        $this->cargarProtegido(function () use ($handler) {
            $out = $handler->handle();
            $this->totalEspecimenes = $out->totalEspecimenes;
            $this->publicablesGbif = $out->publicablesGbif;
            $this->porcentajePublicables = $out->porcentajePublicables();
            $this->pendientesRevision = $out->pendientesRevision;
            $this->localidadesVerbatimPendientes = $out->localidadesVerbatimPendientes;
            $this->taxonVerbatimPendientes = $out->taxonVerbatimPendientes;
            $this->duplicadosCatalogNumberGrupos = $out->duplicadosCatalogNumberGrupos;
            $this->fechaVerbatimPendientes = $out->fechaVerbatimPendientes;
            $this->muestrasPendientes = $out->muestrasPendientes;
        });
    }

    public function render(): View
    {
        return view('inventariogestioncoleccion::admin.taxonomia.revision.centro');
    }
}
