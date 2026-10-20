<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos;

use App\Support\AccesoCatalogo;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\ActualizacionCatalogo;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Jobs\ProcesarActualizacionCatalogo;

#[Layout('layouts.portal', params: ['title' => 'Importar y actualizar catálogo'])]
class ActualizarCatalogoIndex extends Component
{
    use WithFileUploads;

    public $archivo = null;
    public string $delimitador = ',';
    public string $modo = 'completar';
    #[Locked] public ?array $resultado = null;
    #[Locked] public ?string $archivoSha256 = null;
    #[Locked] public ?string $parametrosValidados = null;
    #[Locked] public ?string $importacionId = null;
    public ?string $errorMessage = null;
    public ?string $successMessage = null;
    public bool $detalleAbierto = false;
    #[Locked] public ?array $detalle = null;
    public int $paginaDetalle = 1;

    public function mount(): void { $this->autorizar(); }

    private function autorizar(): void
    {
        auth()->user()?->unsetRelation('roles');
        AccesoCatalogo::autorizar();
    }

    protected function rules(): array
    {
        return ['archivo' => 'required|file|mimes:xlsx,xls,csv,txt|max:20480', 'delimitador' => ['required', \Illuminate\Validation\Rule::in([',', ';', 'tab'])], 'modo' => 'required|in:completar,actualizar'];
    }

    protected function messages(): array
    {
        return ['archivo.required' => 'Selecciona la matriz que deseas cargar.', 'archivo.mimes' => 'Selecciona un archivo Excel o CSV.', 'archivo.max' => 'La matriz no debe superar los 20 MB.'];
    }

    public function updatedArchivo(): void { $this->invalidar(); $this->validateOnly('archivo'); }
    public function updatedModo(): void { $this->invalidar(); }
    public function updatedDelimitador(): void { $this->invalidar(); }

    private function invalidar(): void
    {
        $this->autorizar();
        $this->reset('resultado', 'archivoSha256', 'parametrosValidados', 'importacionId', 'errorMessage', 'successMessage');
    }

    public function validar(): void
    {
        $this->autorizar();
        $this->validate();
        $this->errorMessage = null;
        $this->successMessage = null;
        try {
            $this->resultado = app(ActualizacionCatalogo::class)->analizar((string)$this->archivo->getRealPath(), strtolower($this->archivo->getClientOriginalExtension()), $this->delimitador === 'tab' ? "\t" : $this->delimitador, $this->modo);
            $this->archivoSha256 = hash_file('sha256', $this->archivo->getRealPath());
            $this->parametrosValidados = $this->modo.'|'.$this->delimitador;
            $this->successMessage = 'Validación completa. Revisa las altas y actualizaciones antes de guardar.';
        } catch (\Throwable $error) { $this->mostrarError($error); }
    }

    public function importar(): void
    {
        $this->autorizar();
        $this->validate();
        if (!$this->resultado || !$this->archivoSha256 || !hash_equals($this->archivoSha256, hash_file('sha256', $this->archivo->getRealPath())) || $this->parametrosValidados !== $this->modo.'|'.$this->delimitador) {
            $this->errorMessage = 'Valida primero este archivo y revisa el resumen.';
            return;
        }
        try {
            $this->importacionId = DB::transaction(function (): string {
            DB::statement("SET LOCAL lock_timeout = '10s'");
            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [$this->archivoSha256.'|'.$this->modo]);
            $existente = DB::table('taxonomia.importaciones_catalogo')->where('fuente_sha256', $this->archivoSha256)->where('modo', $this->modo)->lockForUpdate()->first();
            if ($existente && in_array($existente->estado, ['en_cola', 'procesando', 'completada'], true)) {
                $this->successMessage = $existente->estado === 'completada' ? 'Este archivo ya fue importado. Se conserva el resultado anterior, sin duplicar fichas.' : 'Este archivo ya se está procesando. Puedes consultar aquí su resultado.';
                return $existente->id;
            }
            $id = $existente?->id ?? (string)Str::uuid();
            $extension = strtolower($this->archivo->getClientOriginalExtension());
            $ruta = 'catalogo/importaciones/'.$id.'/original.'.$extension;
            $sha = app(AlmacenamientoDepositos::class)->guardarContenido($ruta, file_get_contents($this->archivo->getRealPath()), $this->archivo->getMimeType() ?: 'application/octet-stream');
            if (!hash_equals($this->archivoSha256, $sha)) throw new DomainException('No se pudo conservar íntegro el archivo original.');
            $datos = ['archivo_nombre' => basename($this->archivo->getClientOriginalName()), 'archivo_ruta' => $ruta, 'extension' => $extension, 'delimitador' => $this->delimitador === 'tab' ? "\t" : $this->delimitador, 'modo' => $this->modo,
                'creado_por_id' => (string)auth()->id(), 'estado' => 'en_cola', 'plan_sha256' => $this->resultado['huella'], 'resumen' => json_encode($this->resultado + ['modo' => $this->modo], JSON_THROW_ON_ERROR), 'error' => null, 'updated_at' => now()];
            if ($existente) DB::table('taxonomia.importaciones_catalogo')->where('id', $id)->update($datos);
            else DB::table('taxonomia.importaciones_catalogo')->insert($datos + ['id' => $id, 'fuente_sha256' => $this->archivoSha256, 'created_at' => now()]);
            ProcesarActualizacionCatalogo::dispatch($id)->onConnection('database')
                ->onQueue(config('hubdigital.validation_mode') ? (string)config('hubdigital.validation_queue', 'validation') : 'default')->afterCommit();
            $this->errorMessage = null;
            $this->successMessage = 'Archivo recibido. La carga continúa en segundo plano; el resultado se actualizará aquí.';
            return $id;
            });
        } catch (\Throwable $error) { $this->mostrarError($error); }
    }

    private function mostrarError(\Throwable $error): void
    {
        $this->resultado = null;
        $this->archivoSha256 = null;
        $this->successMessage = null;
        if (!$error instanceof DomainException) report($error);
        $this->errorMessage = $error instanceof DomainException ? $error->getMessage() : 'No se pudo procesar la matriz. El catálogo se conserva sin cambios. Revisa el archivo y vuelve a intentar.';
    }

    public function consultar(): void { $this->autorizar(); }

    public function verDetalle(string $id): void
    {
        $this->autorizar();
        $carga = DB::table('taxonomia.importaciones_catalogo')->where('id', $id)->first();
        abort_unless($carga, 404);
        $this->detalle = ['id' => $carga->id, 'archivo' => $carga->archivo_nombre];
        $this->paginaDetalle = 1;
        $this->detalleAbierto = true;
    }

    public function moverPaginaDetalle(int $delta): void
    {
        $this->autorizar();
        $this->paginaDetalle = max(1, min(2500, $this->paginaDetalle + ($delta < 0 ? -1 : 1)));
    }

    public function limpiar(): void { $this->invalidar(); $this->reset('archivo'); $this->resetValidation(); }

    public function render(): View
    {
        $this->autorizar();
        $carga = $this->importacionId ? DB::table('taxonomia.importaciones_catalogo')->where('id', $this->importacionId)->first() : null;
        $historial = DB::table('taxonomia.importaciones_catalogo')->orderByDesc('created_at')->limit(10)->get();
        $filas = $this->detalle ? DB::table('taxonomia.importacion_filas_catalogo')->where('importacion_id', $this->detalle['id'])->orderBy('fila')->offset(($this->paginaDetalle - 1) * 20)->limit(21)->get() : collect();
        return view('inventariogestioncoleccion::admin.taxonomia.importar.actualizacion', compact('carga', 'historial', 'filas'));
    }
}
