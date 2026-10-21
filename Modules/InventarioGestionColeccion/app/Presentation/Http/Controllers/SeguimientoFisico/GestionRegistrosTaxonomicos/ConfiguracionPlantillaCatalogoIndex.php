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

    public function render() { return view('inventariogestioncoleccion::admin.taxonomia.importar.plantilla', ['definicion' => app(ConfiguracionPlantillaCatalogo::class)->leer()]); }
}
