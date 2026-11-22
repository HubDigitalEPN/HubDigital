<?php

declare(strict_types=1);

namespace App\Livewire\Administracion;

use App\Livewire\Concerns\WithInteractiveGrid;
use App\Support\OpcionesRelacionCatalogo;
use App\Support\TablasCatalogoDatos;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Tablas de catálogos')]
final class TablasCatalogosDatos extends Component
{
    use WithInteractiveGrid;
    use WithPagination;

    #[Url(history: true)]
    public string $tabla = 'instituciones';

    #[Url(as: 'q')]
    public string $busqueda = '';

    #[Locked]
    public ?string $editando = null;

    #[Locked]
    public string $huella = '';

    #[Locked]
    public bool $borrando = false;

    public array $datos = [];

    private array $metadatos = [];

    public function boot(): void
    {
        abort_unless(auth()->user()?->esCurador(), 403);
    }

    public function seleccionarTabla(string $tabla): void
    {
        abort_unless(isset($this->tablas()[$tabla]), 403);
        if ($tabla !== $this->tabla) {
            $this->tabla = $tabla;
            $this->updatedTabla();
        }
    }

    public function updatedTabla(): void
    {
        $this->reset('editando', 'huella', 'borrando', 'datos');
        $this->resetValidation();
        $this->restablecerGrilla();
    }

    public function updatedBusqueda(): void
    {
        $this->resetPage();
    }

    public function nuevo(TablasCatalogoDatos $servicio): void
    {
        $tabla = $this->definicion();
        $this->reset('editando', 'huella', 'datos', 'borrando');
        $this->datos = $servicio->booleanosIniciales($tabla, $this->columnas($servicio, $tabla));
        $this->resetValidation();
        $this->modal('registro-tabla-catalogo')->show();
    }

    public function editar(string $id, TablasCatalogoDatos $datos): void
    {
        $this->cargar($id, $datos);
        $this->borrando = false;
        $this->modal('registro-tabla-catalogo')->show();
    }

    public function buscarOpcionesRelacion(string $campo, string $consulta = '', int $pagina = 0): array
    {
        $tabla = $this->definicion()[1];
        abort_unless(str_starts_with($campo, 'datos.'), 404);

        return app(OpcionesRelacionCatalogo::class)->buscar($tabla, substr($campo, 6), $consulta, $pagina);
    }

    public function guardar(TablasCatalogoDatos $tablas, #[\SensitiveParameter] string $secreto = ''): void
    {
        $tabla = $this->definicion();
        if ($tablas->esCorreo($tabla)) {
            request()->attributes->set('administracion_sensible', true);
        }
        try {
            $tablas->crearOEditar($tabla, $this->editando, $this->huella, $this->datos, auth()->user(), $secreto);
        } catch (QueryException $e) {
            throw ValidationException::withMessages(['registro' => $this->mensajeBase($e)]);
        } catch (\DomainException $e) {
            throw ValidationException::withMessages(['registro' => $e->getMessage()]);
        }
        $this->modal('registro-tabla-catalogo')->close();
        $this->finalizar();
    }

    public function solicitarEliminar(string $id, TablasCatalogoDatos $datos): void
    {
        $this->cargar($id, $datos);
        $this->borrando = true;
        $this->modal('eliminar-registro-tabla')->show();
    }

    public function eliminar(TablasCatalogoDatos $tablas): void
    {
        abort_unless($this->borrando && $this->editando !== null, 422);
        try {
            $tablas->eliminar($this->definicion(), $this->editando, $this->huella);
        } catch (QueryException $e) {
            throw ValidationException::withMessages(['registro' => $this->mensajeBase($e)]);
        }
        $this->modal('eliminar-registro-tabla')->close();
        $this->finalizar();
    }

    public function render(TablasCatalogoDatos $servicio, OpcionesRelacionCatalogo $referencias): View
    {
        $tablas = $this->tablas();
        $definicion = $this->definicion();
        $metadatos = $this->columnas($servicio, $definicion);
        $columnas = $servicio->columnasVisibles($definicion, $metadatos);
        $query = $servicio->listar($definicion, $columnas, mb_substr($this->busqueda, 0, 150));
        $filas = $this->paginarGrilla($this->ordenarConsultaGrilla($query, $definicion[2])->orderBy($definicion[2]));
        $campos = $servicio->editables($definicion, $metadatos, $this->editando === null);
        $opciones = $servicio->opcionesEdicion($definicion[1]);
        $relaciones = [];
        foreach (array_keys($campos) as $campo) {
            if ($referencias->tiene($definicion[1], $campo)) {
                $valor = (string) ($this->datos[$campo] ?? '');
                $relaciones[$campo] = $valor === '' ? [] : [
                    $valor => $referencias->etiqueta($definicion[1], $campo, $valor) ?? $valor,
                ];
            }
        }
        $correo = $servicio->esCorreo($definicion);
        $unica = isset($definicion[3]);
        $puedeCrear = !$unica || !$servicio->listar($definicion, $columnas, '')->exists();

        return view('livewire.administracion.tablas-catalogos-datos', compact(
            'tablas', 'definicion', 'columnas', 'filas', 'campos', 'opciones', 'relaciones', 'correo', 'unica', 'puedeCrear',
        ));
    }

    protected function gridColumns(): array
    {
        $servicio = app(TablasCatalogoDatos::class);
        $definicion = $this->definicion();
        $campos = array_keys($servicio->columnasVisibles($definicion, $this->columnas($servicio, $definicion)));
        if ($servicio->esCorreo($definicion)) {
            $campos = array_values(array_diff($campos, ['password']));
        }

        return array_combine($campos, $campos);
    }

    private function columnas(TablasCatalogoDatos $servicio, array $definicion): array
    {
        return $this->metadatos[$this->tabla] ??= $servicio->columnas($definicion);
    }

    private function tablas(): array
    {
        return app(TablasCatalogoDatos::class)->permitidas(auth()->user());
    }

    private function definicion(): array
    {
        return $this->tablas()[$this->tabla] ?? abort(403);
    }

    private function cargar(string $id, TablasCatalogoDatos $servicio): void
    {
        abort_if($id === '' || mb_strlen($id) > 255, 404);
        [$this->datos, $this->huella] = $servicio->editar($this->definicion(), $id);
        $this->editando = $id;
        $this->resetValidation();
    }

    private function finalizar(): void
    {
        $this->reset('editando', 'huella', 'borrando', 'datos');
        $this->resetValidation();
        $this->resetPage();
        session()->flash('catalogo-actualizado', 'Transacción Efectuada');
    }

    private function mensajeBase(QueryException $e): string
    {
        return match ((string) $e->getCode()) {
            '23505' => 'Ya existe un registro con ese valor único.',
            '23503' => 'El registro está en uso o una de sus referencias es inválida.',
            '55P03', '40001' => 'La tabla está ocupada. Vuelve a abrir el registro y reintenta.',
            default => 'La base rechazó los valores de este registro. Comprueba los campos y sus restricciones.',
        };
    }
}
