<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos;

use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ObtenerFichaEspecimen\ObtenerFichaEspecimenHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ObtenerFichaEspecimen\ObtenerFichaEspecimenInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services\RegistroColumnasEspecimen;

/**
 * Modal reutilizable con la ficha COMPLETA (todas las columnas) de un espécimen.
 *
 * Se monta UNA vez por página de control de calidad y escucha el evento Livewire
 * `ver-ficha-especimen` (con el id del espécimen). Así cada bandeja de revisión
 * ofrece "Ver ficha" en cada espécimen sin duplicar el modal ni el mapeo de
 * columnas. Registrado con alias `inventario-ficha-especimen` en el ServiceProvider.
 */
final class FichaEspecimenModal extends Component
{
    public bool $abierto = false;

    /** @var array<string, mixed> */
    public array $ficha = [];

    public ?string $codigo = null;

    public bool $noEncontrado = false;

    public ?string $errorMessage = null;

    #[On('ver-ficha-especimen')]
    public function abrir(string $id): void
    {
        $this->reset('ficha', 'codigo', 'noEncontrado', 'errorMessage');

        try {
            $output = app(ObtenerFichaEspecimenHandler::class)
                ->handle(new ObtenerFichaEspecimenInput(especimenId: $id));

            if (! $output->encontrado) {
                $this->noEncontrado = true;
                $this->abierto = true;

                return;
            }

            $this->ficha = $output->ficha;
            $this->codigo = $output->ficha['codigoCatalogo'] ?? $id;
            $this->abierto = true;
        } catch (\Throwable $e) {
            $this->errorMessage = 'No se pudo cargar la ficha del espécimen.';
            $this->abierto = true;
        }
    }

    public function cerrar(): void
    {
        $this->abierto = false;
    }

    public function render(): View
    {
        return view('inventariogestioncoleccion::admin.taxonomia.especimenes.ficha-modal', [
            'grupos' => RegistroColumnasEspecimen::todas(),
            'etiquetasGrupo' => [
                RegistroColumnasEspecimen::GRUPO_IDENTIFICACION => 'Identificación',
                RegistroColumnasEspecimen::GRUPO_TAXONOMIA => 'Taxonomía',
                RegistroColumnasEspecimen::GRUPO_LOCALIDAD => 'Localidad',
                RegistroColumnasEspecimen::GRUPO_FECHA => 'Fecha',
                RegistroColumnasEspecimen::GRUPO_REGISTRO => 'Registro',
                RegistroColumnasEspecimen::GRUPO_ATRIBUTOS => 'Atributos',
                RegistroColumnasEspecimen::GRUPO_REVISION => 'Revisión',
            ],
        ]);
    }
}
