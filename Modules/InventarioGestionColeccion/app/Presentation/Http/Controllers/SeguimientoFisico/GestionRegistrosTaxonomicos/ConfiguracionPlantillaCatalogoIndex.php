<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos;

use App\Support\AccesoCatalogo;
use DomainException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\ConfiguracionPlantillaCatalogo;

#[Layout('layouts.portal', params: ['title' => 'Plantilla del catálogo'])]
final class ConfiguracionPlantillaCatalogoIndex extends Component
{
    use WithFileUploads;
    public $archivo = null;
    #[Locked] public string $huella = '';
    #[Locked] public ?string $campoEdicion = null;
    public string $cabecera = '';
    public string $descripcion = '';
    public string $requisito = '';
    public string $formato = '';
    public ?string $mensaje = null;
    public ?string $error = null;

    public function boot(): void { auth()->user()?->unsetRelation('roles'); AccesoCatalogo::autorizar(); }
    public function mount(): void { $this->huella = ConfiguracionPlantillaCatalogo::huella(app(ConfiguracionPlantillaCatalogo::class)->leer()); }

    public function guardar(): void
    {
        AccesoCatalogo::autorizar();
        $this->validate(['archivo' => 'required|file|mimes:xlsx|max:2048'], ['archivo.required' => 'Selecciona la plantilla modificada.', 'archivo.mimes' => 'Selecciona la plantilla en formato .xlsx.', 'archivo.max' => 'La plantilla de configuración no debe superar 2 MB.']);
        $this->mensaje = $this->error = null;
        try {
            $servicio = app(ConfiguracionPlantillaCatalogo::class);
            $definicion = $servicio->desdeExcel($this->archivo->getRealPath());
            $servicio->guardar($definicion, $this->huella, (string)auth()->id());
            $this->huella = ConfiguracionPlantillaCatalogo::huella($definicion);
            $this->archivo = null;
            $this->mensaje = 'Plantilla y guía actualizadas. Las siguientes descargas y validaciones usarán esta configuración; los registros del catálogo se conservan.';
        } catch (DomainException $e) { $this->error = $e->getMessage(); }
        catch (\Throwable $e) { report($e); $this->error = 'No se pudo leer la plantilla. La configuración anterior se conserva. Descarga la plantilla actual e intenta nuevamente.'; }
    }

    public function editarColumna(string $campo): void
    {
        $definicion = app(ConfiguracionPlantillaCatalogo::class)->leer();
        $columna = collect($definicion['columnas'])->firstWhere('campo', $campo);
        abort_unless($columna, 404);
        $this->campoEdicion = $columna['campo'];
        $this->cabecera = $columna['cabecera'];
        $this->descripcion = $columna['descripcion'];
        $this->requisito = $columna['requisito'];
        $this->formato = $columna['formato'];
        $this->huella = ConfiguracionPlantillaCatalogo::huella($definicion);
        $this->resetValidation();
        $this->modal('columna-plantilla-importacion')->show();
    }

    public function guardarColumna(): void
    {
        AccesoCatalogo::autorizar();
        abort_unless($this->campoEdicion !== null, 422);
        $this->cabecera = trim($this->cabecera);
        $this->descripcion = trim($this->descripcion);
        $cambios = $this->validate([
            'cabecera' => ['required', 'string', 'max:160'],
            'descripcion' => ['required', 'string', 'max:5000'],
            'requisito' => ['required', 'in:obligatorio,recomendado,opcional'],
            'formato' => ['required', 'in:texto,fecha'],
        ]);
        $servicio = app(ConfiguracionPlantillaCatalogo::class);
        $definicion = $servicio->leer();
        $indice = array_search($this->campoEdicion, array_column($definicion['columnas'], 'campo'), true);
        if ($indice === false) {
            $this->addError('cabecera', 'La columna cambió. Cierra el editor y vuelve a cargar la plantilla.');

            return;
        }
        $columna = $definicion['columnas'][$indice];
        if ($this->cabecera !== $columna['cabecera']) {
            $cambios['alias'] = array_values(array_unique([...($columna['alias'] ?? []), $columna['cabecera']]));
        }
        $definicion['columnas'][$indice] = $cambios + $columna;
        try {
            $servicio->guardar($definicion, $this->huella, (string) auth()->id());
        } catch (DomainException $e) {
            $this->addError('cabecera', $e->getMessage());

            return;
        }
        $this->huella = ConfiguracionPlantillaCatalogo::huella($definicion);
        $this->reset('campoEdicion', 'error');
        $this->mensaje = 'Transacción Efectuada';
        $this->modal('columna-plantilla-importacion')->close();
    }

    public function render() { return view('inventariogestioncoleccion::admin.taxonomia.importar.plantilla', ['definicion' => app(ConfiguracionPlantillaCatalogo::class)->leer()]); }
}
