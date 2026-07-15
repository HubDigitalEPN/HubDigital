<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\Contracts\FuenteCatalogoIterator;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\CsvFuenteCatalogo;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\ExcelFuenteCatalogo;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\ImportarCatalogoInvertebrados;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\ResultadoImport;

/**
 * Página del curador para cargar el catálogo de invertebrados desde un archivo
 * Excel (.xlsx/.xls) o CSV subido por la web. Es capa de presentación pura:
 * arma la `FuenteCatalogoIterator` adecuada según la extensión y delega TODO el
 * trabajo real al orchestrator `ImportarCatalogoInvertebrados` (el mismo que usa
 * el comando de consola), renderizando después el `ResultadoImport`.
 *
 * Ofrece dos acciones sobre el mismo archivo:
 *  - Validar (dry-run): cuenta y reporta sin escribir en la base.
 *  - Importar: persiste. Es idempotente (no duplica lo ya importado), así que
 *    reintentar tras un corte no genera basura.
 */
#[Layout('layouts.app', params: ['title' => 'Importar catálogo'])]
final class ImportarCatalogoIndex extends Component
{
    use WithFileUploads;

    /** Archivo subido (Excel o CSV). */
    public $archivo = null;

    public string $archivoNombre = '';

    /** Separador para archivos CSV (ignorado en Excel). */
    public string $delimitador = ',';

    /** Resultado de la última corrida, ya aplanado para la vista. */
    public ?array $resultado = null;

    public ?string $errorMessage = null;

    public ?string $successMessage = null;

    protected function rules(): array
    {
        return [
            'archivo' => 'required|file|mimes:xlsx,xls,csv,txt|max:51200',
            'delimitador' => 'required|string|max:2',
        ];
    }

    protected function messages(): array
    {
        return [
            'archivo.required' => 'Selecciona un archivo Excel o CSV para importar.',
            'archivo.mimes' => 'Solo se aceptan archivos .xlsx, .xls o .csv.',
            'archivo.max' => 'El archivo no debe superar los 50 MB.',
        ];
    }

    public function updatedArchivo(): void
    {
        $this->resultado = null;
        $this->errorMessage = null;
        $this->successMessage = null;

        if ($this->archivo === null) {
            $this->archivoNombre = '';

            return;
        }

        $this->validateOnly('archivo');
        $this->archivoNombre = $this->archivo->getClientOriginalName();
    }

    public function validar(): void
    {
        $this->ejecutar(dryRun: true);
    }

    public function importar(): void
    {
        $this->ejecutar(dryRun: false);
    }

    public function limpiar(): void
    {
        $this->reset('archivo', 'archivoNombre', 'resultado', 'errorMessage', 'successMessage');
        $this->resetValidation();
        $this->dispatch('import-limpiado');
    }

    /**
     * Ejecuta el importador sobre el archivo cargado. Envuelve todo en un try/catch
     * para que un archivo corrupto o ilegible se traduzca en un mensaje al usuario
     * en vez de una página de error.
     */
    private function ejecutar(bool $dryRun): void
    {
        $this->validate();

        // Importar un catálogo grande puede tardar y consumir memoria (parseo del
        // Excel + inserts por lotes). Se amplían los límites solo para esta petición.
        @set_time_limit(600);
        if ($this->limiteMemoriaEnBytes() < 1024 * 1024 * 1024) {
            @ini_set('memory_limit', '1024M');
        }

        try {
            $fuente = $this->construirFuente();

            /** @var ImportarCatalogoInvertebrados $importer */
            $importer = app(ImportarCatalogoInvertebrados::class);

            $resultado = $importer->ejecutar(fuente: $fuente, dryRun: $dryRun);

            $this->resultado = $this->aplanar($resultado);
            $this->errorMessage = null;

            $this->successMessage = $dryRun
                ? "Validación completa: {$resultado->filasLeidas} filas leídas. Revisa el resumen y presiona «Importar al catálogo» para guardar."
                : "Importación completa: {$resultado->especimenesPersistidos} especímenes guardados en el catálogo.";
        } catch (\Throwable $e) {
            $this->resultado = null;
            $this->successMessage = null;
            $this->errorMessage = 'No se pudo procesar el archivo: '.$e->getMessage();
        }
    }

    private function construirFuente(): FuenteCatalogoIterator
    {
        $ruta = (string) $this->archivo->getRealPath();
        $extension = strtolower((string) $this->archivo->getClientOriginalExtension());

        return in_array($extension, ['csv', 'txt'], true)
            ? new CsvFuenteCatalogo($ruta, $this->delimitador !== '' ? $this->delimitador : ',')
            : new ExcelFuenteCatalogo($ruta);
    }

    /** @return array<string, mixed> */
    private function aplanar(ResultadoImport $r): array
    {
        return [
            'filasLeidas' => $r->filasLeidas,
            'especimenesPersistidos' => $r->especimenesPersistidos,
            'muestrasCreadas' => $r->muestrasCreadas,
            'duplicadosSaltados' => $r->duplicadosSaltados,
            'marcadosParaRevision' => $r->marcadosParaRevision,
            'motivosRevision' => $r->motivosRevision,
            'erroresFatales' => $r->erroresFatales,
            'dryRun' => $r->dryRun,
            'resumen' => $r->resumenLinea(),
        ];
    }

    private function limiteMemoriaEnBytes(): int
    {
        $limite = trim((string) ini_get('memory_limit'));
        if ($limite === '' || $limite === '-1') {
            return PHP_INT_MAX;
        }

        $unidad = strtolower($limite[strlen($limite) - 1]);
        $valor = (int) $limite;

        return match ($unidad) {
            'g' => $valor * 1024 * 1024 * 1024,
            'm' => $valor * 1024 * 1024,
            'k' => $valor * 1024,
            default => (int) $limite,
        };
    }

    public function render(): View
    {
        return view('inventariogestioncoleccion::admin.taxonomia.importar.index');
    }
}
