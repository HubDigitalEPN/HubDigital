<?php

declare(strict_types=1);

namespace App\Livewire\Administracion;

use App\Livewire\Concerns\WithInteractiveGrid;
use App\Support\AccesoCatalogo;
use App\Support\ConfiguracionIpt;
use App\Support\EditorMatrizDepositos;
use App\Support\EditorMatrizIpt;
use App\Support\PaginadorGrillaContinua;
use App\Support\RegistrosAdministracionCatalogos as Matrices;
use DomainException;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Modules\GestionPrestamosRecepciones\Infrastructure\Files\ImportadorPlantillaDepositos;
use Modules\GestionPrestamosRecepciones\Infrastructure\Files\PlantillaMatrizDepositante;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\ConfiguracionPlantillaCatalogo;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\EditorMatrizImportacion;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app')]
#[Title('Matrices')]
final class TablasCatalogos extends Component
{
    use WithFileUploads;
    use WithInteractiveGrid;
    use WithPagination;

    #[Url(history: true)]
    public string $tabla = Matrices::DEPOSITOS;

    #[Url(as: 'q')]
    public string $busqueda = '';

    #[Locked]
    public ?string $campoEdicion = null;

    #[Locked]
    public string $huella = '';

    #[Locked]
    public string $modo = '';

    public array $datos = [];

    public $archivo = null;

    public function mount(): void
    {
        if ($this->tabla === '') {
            $this->tabla = Matrices::DEPOSITOS;
        }
    }

    public function boot(): void
    {
        abort_unless(auth()->user()?->esCurador(), 403);
    }

    public function updatedTabla(): void
    {
        $this->seleccion();
        $this->cancelar();
        $this->restablecerGrilla();
    }

    public function seleccionarTabla(string $tabla): void
    {
        abort_unless(isset($this->matrices()[$tabla]), 403);
        if ($tabla !== $this->tabla) {
            $this->tabla = $tabla;
            $this->updatedTabla();
        }
    }

    public function updatedBusqueda(): void
    {
        $this->resetPage();
    }

    public function nuevo(): void
    {
        $definicion = $this->preparar('columna');
        $this->datos = ['orden' => count($definicion['columnas']) + 1, 'formato' => 'texto'] + match ($this->tabla) {
            Matrices::DEPOSITOS => ['requisito' => 'opcional', 'plantilla_depositante' => '1'],
            Matrices::IMPORTACION => ['requisito' => 'opcional', 'plantilla_catalogo' => '1'],
            Matrices::IPT => ['ancho' => 10, 'origen' => ''],
        };
        $this->modal('editar-columna-matriz')->show();
    }

    public function editarColumna(string $campo): void
    {
        $definicion = $this->preparar('columna', $campo);
        $indice = array_search($campo, array_column($definicion['columnas'], 'campo'), true);
        abort_if($indice === false, 404);
        $columna = $definicion['columnas'][$indice];
        $columna['alias'] = implode($this->tabla === Matrices::IMPORTACION ? "\n" : ', ', $columna['alias'] ?? []);
        if ($this->tabla === Matrices::DEPOSITOS) {
            $columna['plantilla_depositante'] = ($columna['plantilla_depositante'] ?? true) ? '1' : '0';
        } elseif ($this->tabla === Matrices::IMPORTACION) {
            $columna['plantilla_catalogo'] = ($columna['plantilla_catalogo'] ?? true) ? '1' : '0';
        }
        $this->datos = ['orden' => $indice + 1] + $columna;
        $this->modal('editar-columna-matriz')->show();
    }

    public function guardarColumna(): void
    {
        abort_unless($this->modo === 'columna', 422);
        $this->seleccion();
        $this->resetValidation();
        try {
            $this->editor()->guardarFila($this->campoEdicion, $this->datos, $this->huella);
        } catch (ValidationException $error) {
            $mensajes = [];
            foreach ($error->errors() as $campo => $errores) {
                $mensajes[str_starts_with($campo, 'datos.') ? $campo : 'datos.'.$campo] = $errores;
            }
            throw ValidationException::withMessages($mensajes);
        } catch (DomainException $error) {
            $this->addError('matriz', $error->getMessage());
            return;
        }
        $this->finalizar('editar-columna-matriz');
    }

    public function solicitarEliminar(string $campo): void
    {
        $definicion = $this->preparar('eliminar', $campo);
        abort_unless(in_array($campo, array_column($definicion['columnas'], 'campo'), true), 404);
        $this->modal('confirmar-cambio-matriz')->show();
    }

    public function solicitarRestaurar(): void
    {
        abort_unless($this->tabla === Matrices::IPT, 403);
        $this->preparar('restaurar');
        $this->modal('confirmar-cambio-matriz')->show();
    }

    public function confirmarCambio(): void
    {
        $this->seleccion();
        try {
            if ($this->modo === 'eliminar' && $this->campoEdicion !== null) {
                $this->editor()->eliminarFila($this->campoEdicion, $this->huella);
            } else {
                abort_unless($this->modo === 'restaurar' && $this->tabla === Matrices::IPT, 422);
                app(EditorMatrizIpt::class)->restaurar($this->huella);
            }
        } catch (DomainException $error) {
            $this->addError('matriz', $error->getMessage());
            return;
        }
        $this->finalizar('confirmar-cambio-matriz');
    }

    public function mostrarCarga(): void
    {
        abort_unless(in_array($this->tabla, [Matrices::IMPORTACION, Matrices::DEPOSITOS], true), 403);
        $this->preparar('carga');
        $this->modal('cargar-plantilla-matriz')->show();
    }

    #[Locked]
    public array $vistaPlantilla = [];

    #[Locked]
    public string $huellaArchivoPlantilla = '';

    public function updatedArchivo(): void
    {
        $this->reset('vistaPlantilla', 'huellaArchivoPlantilla');
        $this->resetValidation('archivo');
    }

    public function descargarPlantillaDepositos(PlantillaMatrizDepositante $plantilla): StreamedResponse
    {
        $this->seleccion();
        abort_unless($this->tabla === Matrices::DEPOSITOS, 403);

        return $plantilla->descargar();
    }

    public function subirPlantilla(ConfiguracionPlantillaCatalogo $servicio, ImportadorPlantillaDepositos $importador): void
    {
        $this->seleccion();
        abort_unless($this->modo === 'carga' && in_array($this->tabla, [Matrices::DEPOSITOS, Matrices::IMPORTACION], true), 422);
        $this->validate(['archivo' => 'required|file|mimes:xlsx|max:2048'], [
            'archivo.required' => 'Selecciona la plantilla modificada.',
            'archivo.mimes' => 'Selecciona la plantilla en formato .xlsx.',
            'archivo.max' => 'La plantilla de configuración no debe superar 2 MB.',
        ]);
        try {
            $ruta = $this->archivo->getRealPath();
            if ($this->tabla === Matrices::DEPOSITOS) {
                $huellaArchivo = hash_file('sha256', $ruta);
                if ($this->huellaArchivoPlantilla === '' || ! hash_equals($this->huellaArchivoPlantilla, $huellaArchivo)) {
                    $actual = app(Matrices::class)->definicion($this->tabla);
                    if (! hash_equals($this->huella, ConfiguracionPlantillaCatalogo::huella($actual))) {
                        throw new DomainException('Otra persona cambió la matriz. Vuelve a abrir la carga para revisar el Excel.');
                    }
                    $resultado = $importador->leer($ruta, $actual);
                    $this->vistaPlantilla = array_intersect_key($resultado, array_flip(['visibles', 'nuevas', 'ocultas']));
                    $this->huellaArchivoPlantilla = $huellaArchivo;

                    return;
                }
                app(EditorMatrizDepositos::class)->importarPlantilla($ruta, $this->huella, $importador);
            } else {
                AccesoCatalogo::autorizar();
                $definicion = $servicio->desdeExcel($ruta);
                $servicio->guardar($definicion, $this->huella, (string) auth()->id());
            }
        } catch (DomainException $error) {
            $this->addError('archivo', $error->getMessage());
            return;
        } catch (\Throwable $error) {
            report($error);
            $this->addError('archivo', 'No se pudo actualizar la plantilla. Comprueba el archivo e intenta nuevamente.');
            return;
        }
        $this->finalizar('cargar-plantilla-matriz');
    }

    public function cancelar(): void
    {
        $this->reset('campoEdicion', 'huella', 'modo', 'datos', 'archivo', 'vistaPlantilla', 'huellaArchivoPlantilla');
        $this->resetValidation();
    }

    public function render(Matrices $registros): View
    {
        $matrices = $this->matrices();
        $seleccion = $this->seleccion();
        $fuente = $registros->fuente($this->tabla);
        $columnas = $registros->columnas($this->tabla);
        $filas = $this->filasMatriz($registros, $fuente);
        $opciones = $this->modo === 'columna' ? $registros->etiquetas($this->tabla) : [];
        $puedeCrear = true;
        if ($this->tabla === Matrices::IMPORTACION) {
            $estandares = array_column(ConfiguracionPlantillaCatalogo::predeterminada()['columnas'], 'campo');
            $esAdministrador = auth()->user()->esAdministrador();
            $puedeCrear = $esAdministrador || !$registros->contieneCampos($fuente, $estandares);
            if ($this->modo === 'columna' && $this->campoEdicion === null) {
                $configuradas = $registros->consultar($this->tabla, $fuente, '')->whereIn('campo', $estandares)->pluck('campo');
                $estandares = array_diff($estandares, $configuradas->all());
                $opciones['campo'] = ($esAdministrador ? ['' => 'Columna personalizada'] : []) + array_combine($estandares, $estandares);
            }
        }
        if ($this->modo === 'columna' && $this->tabla === Matrices::IPT) {
            $origenes = app(ConfiguracionIpt::class)->origenes();
            $opciones['origen'] = ['' => 'Sin origen'] + array_combine($origenes, $origenes);
            if ($this->campoEdicion === null) {
                $terminos = array_diff(ConfiguracionIpt::terminos(), $registros->consultar($this->tabla, $fuente, '')->pluck('campo')->all());
                $opciones['campo'] = array_combine($terminos, $terminos);
            }
        }
        $columnasProtegidas = match ($this->tabla) {
            Matrices::DEPOSITOS => $registros->proteccionDepositos($fuente),
            Matrices::IMPORTACION => ['occurrenceID'],
            Matrices::IPT => ['occurrenceID', 'basisOfRecord', 'scientificName', 'eventDate'],
        };

        return view('livewire.administracion.tablas-catalogos', compact('matrices', 'seleccion', 'columnas', 'filas',
            'opciones', 'puedeCrear', 'columnasProtegidas'));
    }

    private function filasMatriz(Matrices $registros, Builder $fuente): PaginadorGrillaContinua
    {
        $busqueda = mb_substr($this->busqueda, 0, 150);
        if (trim($busqueda) !== '' || $this->gridColumnFilters !== [] || !in_array($this->gridSort, ['', 'orden'], true)) {
            $consulta = $registros->consultar($this->tabla, $fuente, $busqueda);

            return $this->paginarGrilla($this->ordenarConsultaGrilla($consulta, 'orden')->orderBy('campo'));
        }

        $porPagina = $this->filasGrilla();
        $pagina = $this->getPage();
        $total = $registros->cantidad($fuente);
        $direccion = $this->gridDirection === 'desc' ? 'desc' : 'asc';
        [$offset, $ventana] = $this->rangoGrillaContinua($this->tabla.'|orden|'.$direccion, $total, $porPagina, $pagina);
        $cantidad = min($ventana, max(0, $total - $offset));
        $desde = $direccion === 'desc' ? $total - $offset - $cantidad : $offset;
        $filas = $cantidad > 0 ? $registros->lote($this->tabla, $fuente, $desde, $cantidad, $direccion)->get() : collect();

        return new PaginadorGrillaContinua($filas, $total, $porPagina, $pagina, $offset, $this->gridVentana);
    }

    protected function gridColumns(): array
    {
        $this->seleccion();
        $campos = array_keys(app(Matrices::class)->columnas($this->tabla));
        return array_combine($campos, $campos);
    }

    private function preparar(string $modo, ?string $campo = null): array
    {
        $this->seleccion();
        abort_if($campo !== null && ($campo === '' || mb_strlen($campo) > 160), 404);
        $this->cancelar();
        $definicion = app(Matrices::class)->definicion($this->tabla);
        $this->huella = ConfiguracionPlantillaCatalogo::huella($definicion);
        $this->modo = $modo;
        $this->campoEdicion = $campo;
        return $definicion;
    }

    private function finalizar(string $modal): void
    {
        $this->modal($modal)->close();
        $this->cancelar();
        $this->reset('gridVentana');
        $this->resetPage();
        session()->flash('catalogo-actualizado', 'Transacción Efectuada');
    }

    private function editor(): EditorMatrizDepositos|EditorMatrizImportacion|EditorMatrizIpt
    {
        return match ($this->tabla) {
            Matrices::DEPOSITOS => app(EditorMatrizDepositos::class),
            Matrices::IMPORTACION => app(EditorMatrizImportacion::class),
            Matrices::IPT => app(EditorMatrizIpt::class),
        };
    }

    private function seleccion(): array
    {
        return $this->matrices()[$this->tabla] ?? abort(403);
    }

    private function matrices(): array
    {
        $matrices = [
            Matrices::DEPOSITOS => [
                'nombre' => 'Matriz de depósitos',
                'uso' => 'Define las columnas de la plantilla que usan los depositantes y las reglas para validar sus archivos.',
            ],
        ];
        if (AccesoCatalogo::completo()) {
            $matrices[Matrices::IMPORTACION] = [
                'nombre' => 'Plantilla de importación del catálogo',
                'uso' => 'Define las cabeceras, la guía y las reglas para importar especímenes al catálogo.',
            ];
        }
        if (auth()->user()->esAdministrador()) {
            $matrices[Matrices::IPT] = [
                'nombre' => 'Formato IPT (GBIF)',
                'uso' => 'Define las columnas, el orden y el formato de las descargas destinadas a GBIF.',
            ];
        }

        return $matrices;
    }
}
